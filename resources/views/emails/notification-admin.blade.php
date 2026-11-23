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
                            <h1 style="color:#ffffff; font-size: 1.3rem; margin: 0 0 12px;">{{ $titre }}</h1>
                            <p style="color: rgba(255,255,255,0.75); font-size: 0.95rem; line-height: 1.6; margin: 0 0 20px;">
                                {{ $introduction }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid rgba(255,255,255,0.08);">
                                @foreach ($lignes as $libelle => $valeur)
                                    @if (filled($valeur))
                                        <tr>
                                            <td style="padding: 9px 12px 9px 0; border-bottom: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); font-size: 0.8rem; vertical-align: top; width: 38%;">{{ $libelle }}</td>
                                            <td style="padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,0.08); color:#ffffff; font-size: 0.9rem; vertical-align: top;">{!! nl2br(e($valeur)) !!}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>

                            @if ($lien)
                                <p style="margin: 28px 0 0;">
                                    <a href="{{ $lien }}" style="display:inline-block; background-color:#d97706; color:#ffffff; text-decoration:none; font-size: 0.9rem; font-weight: 600; padding: 12px 22px; border-radius: 8px;">{{ $libelleLien }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 20px;">
                            <p style="color: rgba(255,255,255,0.35); font-size: 0.75rem; margin: 0;">
                                Notification automatique Esprit Granit — vous recevez ce message en copie cachée.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
