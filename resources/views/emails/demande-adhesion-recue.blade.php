<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background-color:#0a0a0a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 560px;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-bottom: 24px;">
                            <span style="color:#d97706; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.5px;">ESPRIT GRANIT</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#111827; border-radius: 12px; padding: 32px; border: 1px solid rgba(255,255,255,0.08);">
                            <h1 style="color:#ffffff; font-size: 1.3rem; margin: 0 0 16px;">Demande bien reçue</h1>
                            <p style="color: rgba(255,255,255,0.75); font-size: 0.95rem; line-height: 1.6; margin: 0 0 12px;">
                                Bonjour {{ $demande->nom_contact }},
                            </p>
                            <p style="color: rgba(255,255,255,0.75); font-size: 0.95rem; line-height: 1.6; margin: 0 0 12px;">
                                Nous avons bien reçu votre demande d'adhésion pour <strong style="color:#fff;">{{ $demande->enseigne }}</strong>.
                                Notre équipe va l'examiner et vous recontactera par e-mail dès qu'elle sera validée.
                            </p>
                            <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.6; margin: 24px 0 0;">
                                Vous n'avez rien d'autre à faire pour le moment.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 20px;">
                            <p style="color: rgba(255,255,255,0.35); font-size: 0.75rem; margin: 0;">
                                Esprit Granit — plateforme professionnelle réservée aux entreprises de pompes funèbres.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
