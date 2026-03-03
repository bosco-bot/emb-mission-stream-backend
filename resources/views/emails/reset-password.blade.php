<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #1a73e8; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">EMB Mission</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px;">Radio et Télévision Chrétienne</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333333; margin: 0 0 20px 0;">Bonjour {{ $name }}!</h2>
                            <p style="color: #666666; line-height: 1.6; margin: 0 0 20px 0;">
                                Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.
                            </p>
                            <p style="text-align: center; margin: 30px 0;">
                                <a href="{{ $url }}" style="background-color: #1a73e8; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                                    Réinitialiser le mot de passe
                                </a>
                            </p>
                            <p style="color: #999999; font-size: 12px; margin: 20px 0 0 0;">
                                Ce lien de réinitialisation expirera dans 60 minutes.
                            </p>
                            <p style="color: #666666; line-height: 1.6; margin: 20px 0 0 0;">
                                Si vous n'avez pas demandé de réinitialisation de mot de passe, aucune action n'est requise.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center;">
                            <p style="color: #666666; margin: 0; font-size: 12px;">
                                Cordialement, l'équipe EMB Mission
                            </p>
                            <p style="color: #999999; margin: 10px 0 0 0; font-size: 11px;">
                                © {{ date('Y') }} EMB Mission. Tous droits réservés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
