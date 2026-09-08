<?php

namespace App\Models;

use App\Mail\DemandeAdhesionAcceptee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class DemandeAdhesion extends Model
{
    protected $fillable = [
        'enseigne',
        'raison_sociale',
        'siret',
        'adresse',
        'code_postal',
        'commune',
        'adresse_livraison',
        'code_postal_livraison',
        'commune_livraison',
        'email',
        'telephone',
        'nom_contact',
        'statut',
        'motif_refus',
        'societe_id',
        'agence_id',
        'user_id',
        'traitee_le',
    ];

    protected $casts = [
        'traitee_le' => 'datetime',
    ];

    public function societe()
    {
        return $this->belongsTo(Societe::class);
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accepte la demande : crée la Société, l'Agence et le compte Utilisateur associés,
     * envoie les identifiants par e-mail, puis marque la demande comme traitée.
     *
     * @throws RuntimeException si un compte existe déjà avec cet e-mail (doublon de
     *         demande, ou client déjà adhérent) — évite un plantage brut en base et
     *         laisse l'appelant décider quoi afficher à l'admin.
     *
     * @return array{societe: Societe, agence: Agence, user: User, motDePasseTemporaire: string, emailEnvoye: bool}
     */
    public function accepter(): array
    {
        if (User::where('email', $this->email)->exists()) {
            throw new RuntimeException(
                "Un compte existe déjà avec l'adresse {$this->email}. " .
                "Si c'est un doublon de demande, refuse plutôt celle-ci. " .
                "Si le client a besoin d'une nouvelle agence sur un compte existant, ça se fait manuellement depuis Agences."
            );
        }

        $societe = Societe::create([
            'enseigne' => $this->enseigne,
            'raison_sociale' => $this->raison_sociale,
            'siret' => $this->siret,
            'adresse_siege' => $this->adresse,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'contact_principal' => $this->nom_contact,
            'statut_compte' => 'actif',
        ]);

        $agence = Agence::create([
            'societe_id' => $societe->id,
            'nom' => $this->enseigne,
            'adresse' => $this->adresse,
            'code_postal' => $this->code_postal,
            'commune' => $this->commune,
            'adresse_livraison' => $this->adresse_livraison ?: $this->adresse,
            'code_postal_livraison' => $this->code_postal_livraison ?: $this->code_postal,
            'commune_livraison' => $this->commune_livraison ?: $this->commune,
            'contact' => $this->nom_contact,
            'email' => $this->email,
            'telephone' => $this->telephone,
        ]);

        $motDePasseTemporaire = Str::password(12);

        $user = User::create([
            'name' => $this->nom_contact,
            'email' => $this->email,
            'password' => Hash::make($motDePasseTemporaire),
            'societe_id' => $societe->id,
            'agence_id' => $agence->id,
        ]);

        $user->assignRole('utilisateur_agence');

        $this->update([
            'statut' => 'acceptee',
            'societe_id' => $societe->id,
            'agence_id' => $agence->id,
            'user_id' => $user->id,
            'traitee_le' => now(),
        ]);

        $emailEnvoye = true;
        try {
            Mail::to($user->email)->send(new DemandeAdhesionAcceptee($user, $motDePasseTemporaire));
        } catch (\Throwable $e) {
            report($e);
            $emailEnvoye = false;
        }

        return [
            'societe' => $societe,
            'agence' => $agence,
            'user' => $user,
            'motDePasseTemporaire' => $motDePasseTemporaire,
            'emailEnvoye' => $emailEnvoye,
        ];
    }

    public function refuser(?string $motif = null): void
    {
        $this->update([
            'statut' => 'refusee',
            'motif_refus' => $motif,
            'traitee_le' => now(),
        ]);
    }
}
