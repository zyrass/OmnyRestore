<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirmé — Téléchargez vos photos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: Georgia, 'Times New Roman', serif; color: #F5F0E8; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        .header { background: linear-gradient(135deg, #0a1f12 0%, #1A2D1A 100%); padding: 40px 40px 32px; border-bottom: 1px solid rgba(52,211,153,0.3); text-align: center; }
        .logo { font-size: 11px; letter-spacing: 4px; color: #C9A84C; text-transform: uppercase; margin-bottom: 24px; }
        .header h1 { font-size: 22px; color: #F5F0E8; font-weight: normal; line-height: 1.4; }
        .check { font-size: 48px; margin-bottom: 12px; }
        .header p { color: #34d399; font-size: 13px; margin-top: 8px; letter-spacing: 1px; }
        .body { padding: 40px; }
        .greeting { font-size: 16px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 14px; color: #9E9085; line-height: 1.7; margin-bottom: 16px; }
        .order-box { background: #0F0C08; border: 1px solid rgba(52,211,153,0.2); border-radius: 2px; padding: 20px 24px; margin: 28px 0; }
        .order-box-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(201,168,76,0.08); font-size: 13px; }
        .order-box-row:last-child { border-bottom: none; }
        .order-box-label { color: #7A6E5E; }
        .order-box-value { color: #F5F0E8; font-weight: bold; }
        .order-box-price { color: #34d399; font-size: 18px; font-weight: bold; }
        .cta-wrapper { text-align: center; margin: 32px 0; }
        .cta { display: inline-block; background: linear-gradient(135deg, #059669, #34d399); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 40px; border-radius: 1px; }
        .note { background: rgba(52,211,153,0.06); border-left: 3px solid rgba(52,211,153,0.4); padding: 14px 18px; margin: 24px 0; font-size: 12px; color: #9E9085; line-height: 1.6; }
        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 28px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.7; }
        .footer a { color: #C9A84C; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">OmnyRestore</div>
        <div class="check">✅</div>
        <h1>Paiement confirmé !<br>Vos photos vous attendent</h1>
        <p>Téléchargement disponible immédiatement</p>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Votre paiement a bien été reçu. Merci pour votre confiance !
            Vos photos restaurées en haute résolution sont désormais disponibles au téléchargement.
        </p>

        <div class="order-box">
            <div class="order-box-row">
                <span class="order-box-label">Référence</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Photos restaurées</span>
                <span class="order-box-value">{{ $order->photo_count }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Montant réglé</span>
                <span class="order-box-price">
                    {{ number_format(($order->total_price_cents ?? $order->base_price_cents ?? 0) / 100, 2, ',', ' ') }} €
                </span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Date de paiement</span>
                <span class="order-box-value">{{ $order->paid_at?->format('d/m/Y à H:i') }}</span>
            </div>
        </div>

        <div class="cta-wrapper">
            <a href="{{ route('client.orders.show', $order) }}" class="cta">
                ⬇ Télécharger mes photos
            </a>
        </div>

        <div class="note">
            <strong>Important :</strong> Votre archive ZIP sera disponible pendant <strong>90 jours</strong>.
            Pensez à sauvegarder vos photos restaurées sur un support personnel (disque dur externe, cloud personnel…).
        </div>

        <p class="text">
            Une question ? Répondez directement à cet email ou contactez-nous à
            <a href="mailto:contact@omnyrestore.fr" style="color:#C9A84C">contact@omnyrestore.fr</a>
        </p>
    </div>

    <div class="footer">
        <p>
            OmnyRestore — Restauration photographique artisanale<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">CGV</a>
        </p>
        <p style="margin-top:12px">
            Vous recevez cet email car vous avez effectué un paiement sur OmnyRestore.<br>
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
