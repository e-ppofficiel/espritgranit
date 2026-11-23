<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de notification interne (devis, commande, adhésion) envoyé à l'équipe Esprit Granit.
 * Voir App\Services\NotificationsAdmin.
 */
class NotificationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, ?string>  $lignes  libellé => valeur (les valeurs vides sont ignorées)
     */
    public function __construct(
        public string $sujet,
        public string $titre,
        public string $introduction,
        public array $lignes,
        public ?string $lien = null,
        public string $libelleLien = 'Ouvrir',
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->sujet)
            ->view('emails.notification-admin');
    }
}
