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
                            <h1 style="color:#ffffff; font-size: 1.3rem; margin: 0 0 16px;">Votre compte est activé 🎉</h1>
                            <p style="color: rgba(255,255,255,0.75); font-size: 0.95rem; line-height: 1.6; margin: 0 0 20px;">
                                Bonjour {{ $user->name }},
                            </p>
                            <p style="color: rgba(255,255,255,0.75); font-size: 0.95rem; line-height: 1.6; margin: 0 0 20px;">
                                Votre demande d'adhésion a été validée. Vous pouvez dès à présent accéder à votre espace professionnel.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                                <tr>
                                    <td style="color: rgba(255,255,255,0.5); font-size: 0.8rem; padding-bottom: 4px;">Identifiant</td>
                                </tr>
                                <tr>
                                    <td style="color: #ffffff; font-size: 0.95rem; font-family: monospace; padding-bottom: 12px;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="color: rgba(255,255,255,0.5); font-size: 0.8rem; padding-bottom: 4px;">Mot de passe temporaire</td>
                                </tr>
                                <tr>
                                    <td style="color: #d97706; font-size: 0.95rem; font-family: monospace; font-weight: 700;">{{ $motDePasseTemporaire }}</td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color:#d97706; border-radius: 8px;">
                                        <a href="{{ $lienConnexion }}" style="display:inline-block; padding: 12px 24px; color:#ffffff; text-decoration:none; font-size: 0.9rem; font-weight: 600;">
                                            Me connecter
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem; line-height: 1.6; margin: 24px 0 0;">
                                Pour votre sécurité, nous vous recommandons de changer ce mot de passe dès votre première connexion.
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
