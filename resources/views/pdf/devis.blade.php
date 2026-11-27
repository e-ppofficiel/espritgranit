<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30px 35px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .entete { width: 100%; margin-bottom: 20px; }
        .entete td { vertical-align: top; }
        .marque { color: #d97706; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
        .titre { font-size: 22px; font-weight: bold; margin: 4px 0; }
        .statut {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            color: #fff;
            background-color: #6b7280;
        }
        .meta-droite { text-align: right; font-size: 10px; color: #555; }

        .section-titre {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #d97706;
            font-weight: bold;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 18px 0 8px;
        }

        .note-visuel {
            font-size: 9px;
            color: #9ca3af;
            font-style: italic;
            margin: -4px 0 8px;
        }

        table.infos { width: 100%; border-collapse: collapse; }
        table.infos td { width: 50%; vertical-align: top; padding-right: 20px; font-size: 11px; line-height: 1.6; }
        table.infos .label { color: #6b7280; font-size: 9px; text-transform: uppercase; }

        table.prix { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.prix th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        table.prix td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        table.prix .montant { text-align: right; }
        table.prix .ligne-finale td { font-weight: bold; font-size: 13px; border-top: 2px solid #1a1a1a; border-bottom: none; padding-top: 10px; }

        table.visuels-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.visuels-table td.visuel-cellule { width: 50%; padding: 0 6px 12px 0; vertical-align: top; }
        table.visuels-table td.visuel-cellule img {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            display: block;
        }

        .commentaire {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            font-size: 11px;
            line-height: 1.5;
            margin-top: 6px;
        }

        .pied {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td>
                <div class="marque">ESPRIT GRANIT</div>
                <div class="titre">Devis {{ $devis->numero }}</div>
                <span class="statut">
                    @php
                        $libellesStatut = [
                            'brouillon' => 'Brouillon',
                            'envoye' => 'Envoyé',
                            'en_attente' => 'En attente',
                            'accepte' => 'Accepté',
                            'refuse' => 'Refusé',
                            'expire' => 'Expiré',
                            'transforme_commande' => 'Transformé en commande',
                        ];
                    @endphp
                    {{ $libellesStatut[$devis->statut] ?? $devis->statut }}
                </span>
            </td>
            <td class="meta-droite">
                Émis le {{ $devis->created_at->format('d/m/Y') }}
                @if($devis->reference_client)
                    <br>Réf. client : {{ $devis->reference_client }}
                @endif
                @if($devis->reference_interne)
                    <br>Réf. interne : {{ $devis->reference_interne }}
                @endif
            </td>
        </tr>
    </table>

    <div class="section-titre">Client</div>
    <table class="infos">
        <tr>
            <td>
                <div class="label">Société</div>
                {{ $devis->societe->enseigne ?? '—' }}<br>
                @if($devis->societe?->raison_sociale)
                    {{ $devis->societe->raison_sociale }}<br>
                @endif
                @if($devis->societe?->siret)
                    SIRET : {{ $devis->societe->siret }}<br>
                @endif
                @if($devis->societe?->adresse_siege)
                    {{ $devis->societe->adresse_siege }}
                @endif
            </td>
            <td>
                <div class="label">Agence</div>
                {{ $devis->agence->nom ?? '—' }}<br>
                @if($devis->agence?->adresse)
                    {{ $devis->agence->adresse }}
                    @if($devis->agence?->code_postal || $devis->agence?->commune)
                        <br>{{ $devis->agence->code_postal }} {{ $devis->agence->commune }}
                    @endif
                    <br>
                @endif
                @if($devis->agence?->contact)
                    Contact : {{ $devis->agence->contact }}<br>
                @endif
                @if($devis->agence?->telephone)
                    Tél : {{ $devis->agence->telephone }}
                @endif
            </td>
        </tr>
    </table>

    <div class="section-titre">Configuration</div>
    <table class="infos">
        <tr>
            <td>
                <div class="label">Monument</div>
                {{ $devis->monument->nom ?? '—' }}
                @if($devis->monument?->reference_interne)
                    ({{ $devis->monument->reference_interne }})
                @endif
                <br><br>
                <div class="label">Granit</div>
                {{ $devis->granit->nom_commercial ?? '—' }}
                @if($devis->semelle_reference || $devis->semelle_libelle)
                    <br><br>
                    <div class="label">Semelle</div>
                    {{ $devis->semelle_libelle ?: $devis->semelle_reference }}
                    @if($devis->semelle_reference)
                        <br><span style="color:#6b7280; font-size:9px;">Réf. {{ $devis->semelle_reference }}</span>
                    @endif
                @endif
            </td>
            <td>
                <div class="label">Soubassement noir</div>
                {{ $devis->soubassement_noir ? 'Oui' : 'Non' }}
                <br><br>
                <div class="label">Département de livraison</div>
                @if($devis->departementLivraison)
                    {{ $devis->departementLivraison->code }} - {{ $devis->departementLivraison->nom }}
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    @if(count($visuelsParPaires) > 0)
        <div class="section-titre">
            @if($visuelParDefaut)
                Aperçu du modèle
            @else
                Visuel du monument configuré
            @endif
        </div>
        @if($visuelParDefaut)
            <div class="note-visuel">Photo générique du modèle — aucun visuel personnalisé n'a été capturé pour ce devis.</div>
        @endif
        <table class="visuels-table">
            @foreach($visuelsParPaires as $paire)
                <tr>
                    @foreach($paire as $image)
                        <td class="visuel-cellule"><img src="{{ $image }}"></td>
                    @endforeach
                    @if(count($paire) === 1)
                        <td class="visuel-cellule"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    <div class="section-titre">Prix</div>
    <table class="prix">
        <tr>
            <th>Détail</th>
            <th class="montant">Montant</th>
        </tr>
        <tr>
            <td>Prix granit (base)</td>
            <td class="montant">{{ $devis->prix_granit_base !== null ? number_format($devis->prix_granit_base, 2, ',', ' ') . ' €' : '—' }}</td>
        </tr>
        @if($devis->semelle_reference || $devis->semelle_libelle)
        <tr>
            <td>Prix semelle</td>
            <td class="montant">{{ $devis->prix_semelle !== null ? number_format($devis->prix_semelle, 2, ',', ' ') . ' €' : '—' }}</td>
        </tr>
        @endif
        <tr>
            <td>Prix transport</td>
            <td class="montant">{{ $devis->prix_transport !== null ? number_format($devis->prix_transport, 2, ',', ' ') . ' €' : '—' }}</td>
        </tr>
        <tr>
            <td>Prix total (avant coefficient)</td>
            <td class="montant">{{ $devis->prix_total !== null ? number_format($devis->prix_total, 2, ',', ' ') . ' €' : '—' }}</td>
        </tr>
        <tr>
            <td>Coefficient appliqué</td>
            <td class="montant">{{ $devis->coefficient_applique ?? '—' }}</td>
        </tr>
        <tr class="ligne-finale">
            <td>Prix final</td>
            <td class="montant">{{ $devis->prix_final !== null ? number_format($devis->prix_final, 2, ',', ' ') . ' €' : '—' }}</td>
        </tr>
    </table>

    @if($devis->commentaire)
        <div class="section-titre">Commentaire</div>
        <div class="commentaire">{{ $devis->commentaire }}</div>
    @endif

    <div class="pied">
        Devis généré le {{ now()->format('d/m/Y à H:i') }} — Esprit Granit, plateforme professionnelle réservée aux entreprises de pompes funèbres.
    </div>
</body>
</html>
