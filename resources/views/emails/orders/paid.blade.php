<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirmé — OmnyRestore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #080705; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #F5F0E8; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 0 auto; background: #1C1814; }
        .header { background: linear-gradient(135deg, #1A1510 0%, #2A1F12 100%); padding: 60px 40px 40px; border-bottom: 1px solid rgba(201,168,76,0.2); text-align: center; }
        .logo { margin-bottom: 32px; }
        .header h1 { font-family: Georgia, serif; font-size: 26px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; }
        .header p { color: #C9A84C; font-size: 12px; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; }
        .body { padding: 50px 40px; }
        .greeting { font-family: Georgia, serif; font-size: 18px; color: #F5F0E8; margin-bottom: 24px; }
        .text { font-size: 15px; color: #9E9085; line-height: 1.8; margin-bottom: 20px; }
        .note-success { background: rgba(52,211,153,0.04); border-left: 2px solid #34D399; padding: 20px 24px; margin: 32px 0; font-size: 13px; color: #A7F3D0; line-height: 1.6; }
        .order-box { background: #0F0C08; border: 1px solid rgba(201,168,76,0.15); border-radius: 4px; padding: 24px 30px; margin: 35px 0; }
        .order-box-title { color: #7A6E5E; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px; border-bottom: 1px solid rgba(201,168,76,0.1); padding-bottom: 8px; }
        .order-box-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; }
        .order-box-label { color: #7A6E5E; }
        .order-box-value { color: #F5F0E8; font-weight: 500; }
        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.8; }
        .footer a { color: #C9A84C; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 50px; width: auto;">
        </div>
        <h1>Paiement Confirmé</h1>
        <p>Préparation de vos fichiers HD</p>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Nous avons le plaisir de vous confirmer la réception de votre règlement pour la commande <strong>{{ $order->reference }}</strong>.
        </p>

        <div class="note-success">
            <strong>Génération de l'archive :</strong> Notre système prépare actuellement votre dossier 
            sécurisé contenant l'intégralité de vos photos restaurées en haute résolution, sans filigrane.
        </div>

        <p class="text">
            Dès que l'archive sera prête, un <strong>second email</strong> vous sera envoyé avec votre lien 
            de téléchargement exclusif et votre facture.
        </p>

        <div class="order-box">
            <div class="order-box-title">Détails de la transaction</div>
            <div class="order-box-row">
                <span class="order-box-label">Référence</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Date</span>
                <span class="order-box-value">{{ now()->format('d/m/Y H:i') }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Total réglé</span>
                <span class="order-box-value" style="color: #C9A84C; font-size: 18px; font-weight: bold;">
                    {{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }} € TTC
                </span>
            </div>
        </div>

        <p class="text" style="text-align: center; margin-top: 40px; font-style: italic; font-size: 13px;">
            Merci d'avoir choisi l'expertise OmnyRestore pour vos souvenirs.
        </p>
    </div>

    <div class="footer">
        <p>
            <strong>OmnyRestore</strong> — Atelier de Restauration Photographique<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">CGV</a>
        </p>
        <p style="margin-top:20px; opacity: 0.5;">
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
