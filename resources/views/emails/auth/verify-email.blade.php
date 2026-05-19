<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifiez votre adresse email — OmnyRestore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: Georgia, 'Times New Roman', serif; color: #F5F0E8; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        .header { background: linear-gradient(135deg, #1A1510 0%, #2A2520 100%); padding: 40px 40px 32px; border-bottom: 1px solid rgba(201,168,76,0.2); text-align: center; }
        .logo { font-size: 11px; letter-spacing: 4px; color: #C9A84C; text-transform: uppercase; margin-bottom: 24px; }
        .header h1 { font-size: 22px; color: #F5F0E8; font-weight: normal; line-height: 1.4; }
        .icon { font-size: 40px; margin-bottom: 16px; color: #C9A84C; }
        .header p { color: #C9A84C; font-size: 13px; margin-top: 8px; letter-spacing: 1px; opacity: 0.8; }
        .body { padding: 40px; }
        .greeting { font-size: 16px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 14px; color: #9E9085; line-height: 1.7; margin-bottom: 16px; }
        .cta-wrapper { text-align: center; margin: 36px 0; }
        .cta { display: inline-block; background: linear-gradient(135deg, #C9A84C, #E8C97A); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 40px; border-radius: 1px; }
        .note { background: rgba(201,168,76,0.05); border-left: 3px solid rgba(201,168,76,0.4); padding: 14px 18px; margin: 24px 0; font-size: 12px; color: #9E9085; line-height: 1.6; }
        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 28px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.7; }
        .footer a { color: #C9A84C; text-decoration: none; }
        .raw-link { word-break: break-all; font-size: 11px; color: #7A6E5E; background: #0F0C08; padding: 12px; border: 1px dashed rgba(201,168,76,0.2); margin-top: 24px; text-align: left; }
    </style>
</head>
<body>
<div class="container">
    <div class="header" style="text-align: center;">
        <div class="logo" style="text-align: center; font-size: 11px; letter-spacing: 4px; color: #C9A84C; text-transform: uppercase; margin-bottom: 24px;">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 60px; width: auto; display: inline-block; margin: 0 auto;">
        </div>
        <div class="icon" style="font-size: 40px; margin-bottom: 16px; color: #C9A84C; text-align: center;">✉️</div>
        <h1 style="text-align: center; font-size: 22px; color: #F5F0E8; font-weight: normal; line-height: 1.4; margin-top: 0; margin-bottom: 12px; font-family: Georgia, 'Times New Roman', serif;">Bienvenue sur OmnyRestore !<br>Vérifiez votre adresse email</h1>
        <p style="color: #C9A84C; font-size: 13px; margin-top: 8px; letter-spacing: 1px; opacity: 0.8; text-align: center;">Plus qu'une étape avant de restaurer vos photos</p>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $user->name }},</p>

        <p class="text">
            Merci d'avoir créé un compte sur OmnyRestore. Pour finaliser votre inscription et garantir la sécurité de votre compte, veuillez confirmer votre adresse email.
        </p>

        <div class="cta-wrapper">
            <a href="{{ $url }}" class="cta">
                Vérifier mon email
            </a>
        </div>

        <div class="note">
            <strong>Pourquoi vérifier mon email ?</strong><br>
            C'est indispensable pour accéder à votre espace client, déposer vos photos anciennes à restaurer, et recevoir les notifications lorsque la restauration est terminée.
        </div>

        <p class="text">
            Si vous n'avez pas créé de compte OmnyRestore, aucune action n'est requise de votre part et vous pouvez ignorer cet email.
        </p>

        <div class="raw-link">
            Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
            <a href="{{ $url }}" style="color:#C9A84C;">{{ $url }}</a>
        </div>
    </div>

    <div class="footer">
        <p>
            OmnyRestore — Restauration photographique<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">CGV</a>
        </p>
        <p style="margin-top:12px">
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
