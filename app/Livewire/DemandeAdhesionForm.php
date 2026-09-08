<?php

namespace App\Livewire;

use App\Mail\DemandeAdhesionRecue;
use App\Models\DemandeAdhesion;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class DemandeAdhesionForm extends Component
{
    public string $enseigne = '';
    public string $raison_sociale = '';
    public string $siret = '';
    public string $adresse = '';
    public string $code_postal = '';
    public string $commune = '';
    public string $adresse_livraison = '';
    public string $code_postal_livraison = '';
    public string $commune_livraison = '';
    public string $email = '';
    public string $telephone = '';
    public string $nom_contact = '';

    public bool $envoyee = false;

    protected function rules(): array
    {
        return [
            'enseigne' => ['required', 'string', 'max:255'],
            'raison_sociale' => ['required', 'string', 'max:255'],
            'siret' => ['required', 'string', 'size:14'],
            'adresse' => ['required', 'string', 'max:255'],
            'code_postal' => ['nullable', 'string', 'max:10'],
            'commune' => ['nullable', 'string', 'max:255'],
            'adresse_livraison' => ['nullable', 'string', 'max:255'],
            'code_postal_livraison' => ['nullable', 'string', 'max:10'],
            'commune_livraison' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:30'],
            'nom_contact' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'siret.size' => 'Le SIRET doit comporter exactement 14 chiffres.',
        ];
    }

    public function envoyer(): void
    {
        $donnees = $this->validate();

        $demande = DemandeAdhesion::create($donnees);

        // L'échec de l'envoi d'e-mail (SMTP mal configuré, etc.) ne doit jamais faire
        // perdre la demande elle-même — elle est déjà enregistrée en base à ce stade.
        try {
            Mail::to($demande->email)->send(new DemandeAdhesionRecue($demande));
        } catch (\Throwable $e) {
            report($e);
        }

        $this->envoyee = true;
    }

    public function render()
    {
        return view('livewire.demande-adhesion-form');
    }
}
