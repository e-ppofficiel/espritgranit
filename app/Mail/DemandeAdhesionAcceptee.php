<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeAdhesionAcceptee extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $motDePasseTemporaire,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Votre compte Esprit Granit est activé')
            ->view('emails.demande-adhesion-acceptee')
            ->with([
                'lienConnexion' => url('/agence/login'),
            ]);
    }
}
