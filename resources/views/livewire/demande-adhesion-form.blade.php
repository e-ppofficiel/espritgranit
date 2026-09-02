<div style="display: flex; justify-content: center; padding: 40px 16px;">
    <style>
        .demande-adhesion * { box-sizing: border-box; }
        .demande-adhesion .conteneur { width: 100%; max-width: 640px; }
        .demande-adhesion h1 { font-size: 1.6rem; margin-bottom: 4px; color: #fff; }
        .demande-adhesion .sous-titre { color: rgba(255,255,255,0.5); margin-bottom: 32px; font-size: 0.9rem; }
        .demande-adhesion .section {
            background: #111827;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .demande-adhesion .section h2 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #d97706;
            margin: 0 0 16px;
        }
        .demande-adhesion .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .demande-adhesion .grille .pleine-largeur { grid-column: 1 / -1; }
        .demande-adhesion label { display: block; font-size: 0.8rem; color: rgba(255,255,255,0.7); margin-bottom: 6px; }
        .demande-adhesion input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .demande-adhesion input:focus { outline: none; border-color: #d97706; }
        .demande-adhesion .erreur { color: #f87171; font-size: 0.75rem; margin-top: 4px; }
        .demande-adhesion button {
            width: 100%;
            background: #d97706;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
        }
        .demande-adhesion button:hover { background: #b45309; }
        .demande-adhesion .confirmation { text-align: center; padding: 60px 24px; }
        .demande-adhesion .confirmation .icone { font-size: 3rem; margin-bottom: 16px; }
        .demande-adhesion .confirmation h2 { font-size: 1.3rem; margin-bottom: 12px; color: #fff; }
        .demande-adhesion .confirmation p { color: rgba(255,255,255,0.6); line-height: 1.6; }
    </style>

    <div class="demande-adhesion">
        <div class="conteneur">
            @if($envoyee)
                <div class="section confirmation">
                    <div class="icone">✓</div>
                    <h2>Demande envoyée</h2>
                    <p>
                        Merci pour votre demande d'adhésion. Notre équipe va l'examiner et vous recontactera
                        par e-mail dès qu'elle sera validée.
                    </p>
                </div>
            @else
                <h1>Demande d'adhésion</h1>
                <p class="sous-titre">Réservé aux professionnels des pompes funèbres — Esprit Granit</p>

                <form wire:submit="envoyer">
                    <div class="section">
                        <h2>Votre société</h2>
                        <div class="grille">
                            <div class="champ">
                                <label>Enseigne</label>
                                <input type="text" wire:model="enseigne">
                                @error('enseigne') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <div class="champ">
                                <label>Raison sociale</label>
                                <input type="text" wire:model="raison_sociale">
                                @error('raison_sociale') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <div class="champ pleine-largeur">
                                <label>SIRET</label>
                                <input type="text" wire:model="siret" maxlength="14">
                                @error('siret') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h2>Adresse</h2>
                        <div class="grille">
                            <div class="champ pleine-largeur">
                                <label>Adresse</label>
                                <input type="text" wire:model="adresse">
                                @error('adresse') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <div class="champ">
                                <label>Code postal</label>
                                <input type="text" wire:model="code_postal">
                            </div>
                            <div class="champ">
                                <label>Commune</label>
                                <input type="text" wire:model="commune">
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h2>Adresse de livraison (si différente)</h2>
                        <div class="grille">
                            <div class="champ pleine-largeur">
                                <label>Adresse de livraison</label>
                                <input type="text" wire:model="adresse_livraison">
                            </div>
                            <div class="champ">
                                <label>Code postal</label>
                                <input type="text" wire:model="code_postal_livraison">
                            </div>
                            <div class="champ">
                                <label>Commune</label>
                                <input type="text" wire:model="commune_livraison">
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h2>Contact</h2>
                        <div class="grille">
                            <div class="champ pleine-largeur">
                                <label>Nom du contact</label>
                                <input type="text" wire:model="nom_contact">
                                @error('nom_contact') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <div class="champ">
                                <label>E-mail</label>
                                <input type="email" wire:model="email">
                                @error('email') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <div class="champ">
                                <label>Téléphone</label>
                                <input type="text" wire:model="telephone">
                                @error('telephone') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <button type="submit">Envoyer ma demande d'adhésion</button>
                </form>
            @endif
        </div>
    </div>
</div>
