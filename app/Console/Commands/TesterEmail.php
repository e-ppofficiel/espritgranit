<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TesterEmail extends Command
{
    protected $signature = 'mail:test {destinataire : Adresse e-mail qui recevra le test}';

    protected $description = 'Envoie un e-mail de test basique pour vérifier la configuration SMTP actuelle';

    public function handle(): int
    {
        $destinataire = $this->argument('destinataire');

        $this->info('Configuration actuelle (lue depuis .env) :');
        $this->line('  MAIL_MAILER      : ' . config('mail.default'));
        $this->line('  MAIL_HOST        : ' . config('mail.mailers.smtp.host'));
        $this->line('  MAIL_PORT        : ' . config('mail.mailers.smtp.port'));
        $this->line('  MAIL_USERNAME    : ' . config('mail.mailers.smtp.username'));
        $this->line('  MAIL_ENCRYPTION  : ' . config('mail.mailers.smtp.encryption'));
        $this->line('  MAIL_FROM        : ' . config('mail.from.address') . ' (' . config('mail.from.name') . ')');
        $this->newLine();

        $this->info("Envoi d'un e-mail de test vers : {$destinataire}...");

        try {
            Mail::raw(
                "Ceci est un e-mail de test envoyé depuis EspritGranit.com le " . now()->format('d/m/Y à H:i') . ".\n\nSi tu reçois ce message, la configuration SMTP fonctionne correctement.",
                function ($message) use ($destinataire) {
                    $message->to($destinataire)
                        ->subject('Test SMTP - Esprit Granit');
                }
            );

            $this->newLine();
            $this->info('✅ E-mail envoyé sans erreur signalée.');
            $this->comment('Vérifie la boîte de réception (et les spams) de ' . $destinataire . ' pour confirmer qu\'il est bien arrivé.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Échec de l\'envoi.');
            $this->line('Message d\'erreur : ' . $e->getMessage());
            $this->newLine();
            $this->comment('Vérifie les identifiants SMTP dans .env, et que le port n\'est pas bloqué par le pare-feu du serveur.');

            return self::FAILURE;
        }
    }
}
