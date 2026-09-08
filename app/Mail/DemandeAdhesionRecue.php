<?php

namespace App\Mail;

use App\Models\DemandeAdhesion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeAdhesionRecue extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemandeAdhesion $demande)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Votre demande d\'adhésion a bien été reçue — Esprit Granit')
            ->view('emails.demande-adhesion-recue');
    }
}
