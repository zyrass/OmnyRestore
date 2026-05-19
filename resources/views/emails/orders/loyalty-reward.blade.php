<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre cadeau de fidélité — OmnyRestore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #F5F0E8; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        
        /* Header avec dégradé subtil or/sombre */
        .header { background: linear-gradient(135deg, #1A1510 0%, #2A2216 100%); padding: 50px 40px 40px; border-bottom: 1px solid rgba(201,168,76,0.2); text-align: center; }
        .logo { margin-bottom: 24px; }
        
        /* Badge cadeau */
        .badge { display: inline-block; background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.3); color: #C9A84C; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 24px; }
        
        .header h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; }
        
        .body { padding: 40px; }
        .greeting { font-family: Georgia, 'Times New Roman', serif; font-size: 18px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 15px; color: #9E9085; line-height: 1.7; margin-bottom: 24px; }
        
        /* Boîte de ticket cadeau style billet d'or */
        .ticket-box { background: #0F0C08; border: 1px solid rgba(201,168,76,0.3); border-radius: 4px; padding: 30px; margin: 32px 0; text-align: center; position: relative; }
        .ticket-title { color: #C9A84C; font-size: 11px; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; margin-bottom: 15px; }
        .ticket-value { font-size: 42px; font-weight: bold; color: #F5F0E8; font-family: Georgia, serif; line-height: 1; margin-bottom: 10px; }
        .ticket-code { display: inline-block; background: rgba(201,168,76,0.1); border: 1px dashed #C9A84C; color: #C9A84C; font-family: monospace; font-size: 20px; font-weight: bold; padding: 10px 24px; letter-spacing: 2px; margin: 15px 0; }
        .ticket-date { color: #7A6E5E; font-size: 13px; margin-top: 10px; }

        /* Boîte d'info utilisation */
        .info-box { background: rgba(201,168,76,0.03); border: 1px solid rgba(201,168,76,0.1); border-left: 3px solid #C9A84C; padding: 20px 24px; margin: 32px 0; }
        .info-title { color: #C9A84C; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .info-text { font-size: 14px; color: #9E9085; line-height: 1.6; }

        /* Bouton d'action */
        .btn-container { text-align: center; margin: 36px 0 16px; }
        .btn-gold { display: inline-block; background: #C9A84C; color: #0D0B08; font-weight: bold; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 2px; letter-spacing: 1px; text-transform: uppercase; transition: background 0.2s; }
        .btn-gold:hover { background: #E8C97A; }

        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 32px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.8; }
        .footer a { color: #7A6E5E; text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #C9A84C; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 45px; width: auto;">
        </div>
        <div class="badge">Cadeau Fidélité</div>
        <h1>Merci pour votre<br>fidélité !</h1>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $user->name }},</p>

        <p class="text">
            Vous avez restauré vos précieux souvenirs à nos côtés à 3 reprises (commandes éligibles d'un minimum de 10 € TTC). Pour vous remercier chaleureusement de votre confiance continue, nous sommes ravis de vous offrir un cadeau exclusif :
        </p>

        <div class="ticket-box">
            <div class="ticket-title">Votre Bon Privilège</div>
            <div class="ticket-value">−50%</div>
            <div class="text" style="margin-bottom: 0; color: #F5F0E8; font-size: 14px;">sur votre prochaine commande de restauration</div>
            <div class="ticket-code">{{ $coupon->code }}</div>
            <div class="ticket-date">
                Valable jusqu'au <strong>{{ $coupon->expires_at->format('d/m/Y') }}</strong> (pendant 30 jours)
            </div>
        </div>

        <div class="info-box">
            <div class="info-title">💡 Comment l'utiliser ?</div>
            <div class="info-text">
                Ce bon a été **crédité directement sur votre compte client**. 
                Lors de votre prochaine commande, vous pourrez l'appliquer d'un simple clic depuis votre récapitulatif de paiement, sans aucune saisie fastidieuse !
            </div>
        </div>

        <div class="btn-container">
            <a href="{{ route('client.orders.create') }}" class="btn-gold">Déposer de nouvelles photos</a>
        </div>

        <p class="text" style="text-align: center; margin-top: 32px; font-style: italic; font-size: 13px; color: #7A6E5E;">
            À très bientôt dans notre atelier.
        </p>
    </div>

    <div class="footer">
        <p>
            <strong style="color: #7A6E5E;">OmnyRestore</strong> — Atelier de Restauration Photographique<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">Conditions Générales</a>
        </p>
        <p style="margin-top:16px;">
            Cet email vous a été envoyé automatiquement suite à votre 3ème commande validée.<br>
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
