<?php

namespace App\Services;

use App\Mail\NotificationAdmin;
use App\Models\Commande;
use App\Models\DemandeAdhesion;
use App\Models\Devis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications e-mail envoyées à l'équipe Esprit Granit (en copie cachée) lors des événements métier :
 *   - nouveau devis créé par une agence ;
 *   - devis passé à « Accepté » par l'agence (commande à créer) ;
 *   - nouvelle commande ;
 *   - nouvelle demande d'adhésion.
 *
 * Les écouteurs sont posés sur les modèles : ils couvrent tous les écrans (admin, agence, formulaire public)
 * sans toucher aux ressources Filament. Un échec d'envoi (SMTP, Mailjet…) est journalisé mais ne bloque
 * JAMAIS l'enregistrement du devis, de la commande ou de la demande.
 *
 * Destinataires : variable .env NOTIFICATION_EMAILS (adresses séparées par des virgules). Vide = aucun envoi.
 */
class NotificationsAdmin
{
    public static function enregistrer(): void
    {
        Devis::created(function (Devis $devis) {
            if ($devis->origine === 'agence') {
                static::apresValidation(fn () => static::devisCree($devis));
            }
        });

        Devis::updated(function (Devis $devis) {
            if ($devis->origine === 'agence' && $devis->wasChanged('statut') && $devis->statut === 'accepte') {
                static::apresValidation(fn () => static::devisAccepte($devis));
            }
        });

        Commande::created(function (Commande $commande) {
            static::apresValidation(fn () => static::commandeCreee($commande));
        });

        DemandeAdhesion::created(function (DemandeAdhesion $demande) {
            static::apresValidation(fn () => static::adhesionRecue($demande));
        });
    }

    /** @return array<int, string> adresses valides, sans doublon */
    public static function destinataires(): array
    {
        $brut = (string) config('mail.notifications_admin', '');
        $liste = preg_split('/[\s,;]+/', $brut, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $liste,
            fn (string $adresse) => filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    /** Attend la fin de la transaction éventuelle : pas d'e-mail pour un enregistrement finalement annulé. */
    protected static function apresValidation(callable $envoi): void
    {
        try {
            DB::afterCommit(function () use ($envoi) {
                try {
                    $envoi();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function devisCree(Devis $devis): void
    {
        $devis->loadMissing(['societe', 'agence', 'user', 'monument', 'granit', 'departementLivraison']);

        static::envoyer(new NotificationAdmin(
            sujet: "Nouveau devis {$devis->numero} — " . static::nomAgence($devis),
            titre: 'Nouveau devis',
            introduction: 'Une agence vient de créer un devis.',
            lignes: static::lignesDevis($devis),
            lien: static::urlAdmin(\App\Filament\Resources\DevisResource::class, 'edit', $devis->id),
            libelleLien: 'Ouvrir le devis',
        ));
    }

    public static function devisAccepte(Devis $devis): void
    {
        $devis->loadMissing(['societe', 'agence', 'user', 'monument', 'granit', 'departementLivraison']);

        static::envoyer(new NotificationAdmin(
            sujet: "Devis accepté {$devis->numero} — " . static::nomAgence($devis),
            titre: 'Devis accepté',
            introduction: 'L\'agence a accepté ce devis : il peut être transformé en commande.',
            lignes: static::lignesDevis($devis),
            lien: static::urlAdmin(\App\Filament\Resources\DevisResource::class, 'edit', $devis->id),
            libelleLien: 'Ouvrir le devis',
        ));
    }

    public static function commandeCreee(Commande $commande): void
    {
        $commande->loadMissing(['societe', 'agence', 'devis', 'monument', 'granit', 'departementLivraison']);

        $lignes = [
            'Commande' => $commande->numero,
            'Devis d\'origine' => $commande->devis?->numero,
            'Agence' => static::nomAgence($commande),
            'Monument' => $commande->monument?->nom,
            'Granit' => $commande->granit?->nom_commercial,
            'Livraison' => static::departement($commande),
            'Prix final' => static::prix($commande->prix_final),
            'Statut' => $commande->statut,
            'Créée le' => $commande->created_at?->format('d/m/Y à H:i'),
        ];

        static::envoyer(new NotificationAdmin(
            sujet: "Nouvelle commande {$commande->numero} — " . static::nomAgence($commande),
            titre: 'Nouvelle commande',
            introduction: 'Une commande vient d\'être créée.',
            lignes: $lignes,
            lien: static::urlAdmin(\App\Filament\Resources\CommandeResource::class, 'edit', $commande->id),
            libelleLien: 'Ouvrir la commande',
        ));
    }

    public static function adhesionRecue(DemandeAdhesion $demande): void
    {
        $adresse = trim(implode(' ', array_filter([$demande->adresse, $demande->code_postal, $demande->commune])));

        $lignes = [
            'Enseigne' => $demande->enseigne,
            'Raison sociale' => $demande->raison_sociale,
            'SIRET' => $demande->siret,
            'Contact' => $demande->nom_contact,
            'E-mail' => $demande->email,
            'Téléphone' => $demande->telephone,
            'Adresse' => $adresse,
            'Reçue le' => $demande->created_at?->format('d/m/Y à H:i'),
        ];

        static::envoyer(new NotificationAdmin(
            sujet: "Nouvelle demande d'adhésion — {$demande->enseigne}",
            titre: 'Nouvelle demande d\'adhésion',
            introduction: 'Une entreprise vient de demander à rejoindre la plateforme.',
            lignes: $lignes,
            lien: static::urlAdmin(\App\Filament\Resources\DemandeAdhesionResource::class, 'view', $demande->id),
            libelleLien: 'Traiter la demande',
        ));
    }

    /**
     * Envoi en copie cachée : chaque destinataire ne voit jamais les autres.
     * Le « À » est l'adresse expéditrice du site (MAIL_FROM_ADDRESS) ; à défaut, le premier destinataire.
     */
    protected static function envoyer(NotificationAdmin $mail): void
    {
        $destinataires = static::destinataires();
        if ($destinataires === []) {
            return;
        }

        $expediteur = (string) config('mail.from.address');
        $expediteurValide = filter_var($expediteur, FILTER_VALIDATE_EMAIL) && ! str_ends_with($expediteur, '@example.com');

        if ($expediteurValide) {
            $to = $expediteur;
            $bcc = $destinataires;
        } else {
            $to = array_shift($destinataires);
            $bcc = $destinataires;
        }

        $message = Mail::to($to);
        if ($bcc !== []) {
            $message->bcc($bcc);
        }
        $message->send($mail);
    }

    /** @return array<string, ?string> */
    protected static function lignesDevis(Devis $devis): array
    {
        return [
            'Devis' => $devis->numero,
            'Agence' => static::nomAgence($devis),
            'Contact' => $devis->user?->name,
            'Monument' => $devis->monument?->nom,
            'Granit' => $devis->granit?->nom_commercial,
            'Semelle' => $devis->semelle_libelle ?: $devis->semelle_reference,
            'Livraison' => static::departement($devis),
            'Prix final' => static::prix($devis->prix_final),
            'Statut' => $devis->statut,
            'Commentaire' => $devis->commentaire,
            'Créé le' => $devis->created_at?->format('d/m/Y à H:i'),
        ];
    }

    protected static function nomAgence(Devis|Commande $enregistrement): string
    {
        $agence = $enregistrement->agence?->nom;
        $societe = $enregistrement->societe?->enseigne;

        if ($agence && $societe && $agence !== $societe) {
            return "{$societe} ({$agence})";
        }

        return $agence ?: ($societe ?: 'agence inconnue');
    }

    protected static function departement(Devis|Commande $enregistrement): ?string
    {
        $departement = $enregistrement->departementLivraison;

        return $departement ? trim("{$departement->code} - {$departement->nom}", ' -') : null;
    }

    protected static function prix(mixed $montant): ?string
    {
        return is_numeric($montant) ? number_format((float) $montant, 2, ',', ' ') . ' €' : null;
    }

    /** Lien vers la fiche dans le panneau ADMIN, quel que soit le panneau depuis lequel l'événement est déclenché. */
    protected static function urlAdmin(string $ressource, string $page, int|string $id): string
    {
        try {
            return $ressource::getUrl($page, ['record' => $id], true, 'adminIkksPov');
        } catch (\Throwable $e) {
            report($e);

            return url('/adminIkksPov');
        }
    }
}
