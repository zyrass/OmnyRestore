<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement refusé — OmnyRestore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #F5F0E8; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        
        /* Header avec dégradé subtil sombre/rouge */
        .header { background: linear-gradient(135deg, #1A1510 0%, #2A1616 100%); padding: 50px 40px 40px; border-bottom: 1px solid rgba(239,68,68,0.2); text-align: center; }
        .logo { margin-bottom: 24px; }
        
        /* Badge erreur */
        .badge { display: inline-block; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #F87171; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 24px; }
        
        .header h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; }
        
        .body { padding: 40px; }
        .greeting { font-family: Georgia, 'Times New Roman', serif; font-size: 18px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 15px; color: #9E9085; line-height: 1.7; margin-bottom: 24px; }
        
        /* Boîte de transaction */
        .order-box { background: #0F0C08; border: 1px solid rgba(239,68,68,0.15); border-radius: 4px; padding: 30px; margin: 32px 0; }
        .order-box-header { display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
        .order-box-title { color: #F87171; font-size: 11px; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; text-align: center; }
        .order-box-row { display: flex; justify-content: space-between; padding: 12px 0; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .order-box-row:last-child { border-bottom: none; padding-bottom: 0; }
        .order-box-label { color: #7A6E5E; }
        .order-box-value { color: #F5F0E8; font-weight: 500; }

        /* Boîte des causes */
        .causes-box { background: rgba(239,68,68,0.03); border: 1px solid rgba(239,68,68,0.1); border-left: 3px solid #F87171; padding: 20px 24px; margin: 32px 0; }
        .causes-title { color: #F87171; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .causes-text { font-size: 14px; color: #9E9085; line-height: 1.6; }
        .causes-list { margin-top: 12px; padding-left: 16px; font-size: 13px; color: #F5F0E8; }
        .causes-list li { margin-bottom: 6px; }

        .cta-wrapper { text-align: center; margin: 40px 0 24px; }
        .cta { display: inline-block; background: linear-gradient(135deg, #C9A84C, #E8C97A); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 40px; border-radius: 2px; transition: transform 0.2s; }
        .cta:hover { transform: scale(1.02); }

        .note { text-align: center; font-size: 12px; color: #7A6E5E; line-height: 1.6; margin-top: 16px; }

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
        <div class="badge">Paiement Refusé</div>
        <h1>Une erreur est survenue<br>lors du paiement</h1>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Nous avons tenté de traiter le règlement de votre commande <strong>{{ $order->reference }}</strong>, mais votre établissement bancaire a refusé la transaction.
        </p>

        <div class="order-box">
            <div class="order-box-header">
                <div class="order-box-title">Détails de la tentative</div>
            </div>
            
            <div class="order-box-row">
                <span class="order-box-label">Référence commande</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Montant de la tentative</span>
                <span class="order-box-value">{{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }} €</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Statut de l'opération</span>
                <span class="order-box-value" style="color: #F87171;">Échouée</span>
            </div>
            @if ($failureReason)
            <div class="order-box-row" style="margin-top: 8px; padding-top: 12px; border-top: 1px dashed rgba(239,68,68,0.2);">
                <span class="order-box-label">Motif communiqué</span>
                <span class="order-box-value" style="font-size: 12px; text-align: right; max-width: 200px;">{{ $failureReason }}</span>
            </div>
            @endif
        </div>

        <div class="causes-box">
            <div class="causes-title">💡 Causes fréquentes d'un refus</div>
            <div class="causes-text">
                Aucun montant n'a été débité de votre compte. Les refus sont généralement liés à :
                <ul class="causes-list">
                    <li>Une authentification 3D Secure non validée</li>
                    <li>Un plafond journalier ou hebdomadaire atteint</li>
                    <li>Des fonds insuffisants sur le compte</li>
                    <li>Des informations de carte incorrectes</li>
                </ul>
            </div>
        </div>

        <div class="cta-wrapper">
            <a href="{{ route('client.orders.show', $order) }}" class="cta">
                Réessayer le paiement
            </a>
        </div>

        <div class="note">
            Rassurez-vous, vos aperçus filigranés sont toujours sauvegardés dans votre espace client. Vous pouvez réessayer avec une autre carte à tout moment.
        </div>
    </div>

    <div class="footer">
        <p>
            <strong style="color: #7A6E5E;">OmnyRestore</strong> — Atelier de Restauration Photographique<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">Conditions Générales</a>
        </p>
        <p style="margin-top:16px;">
            Cet email est généré automatiquement suite à une tentative de paiement.<br>
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
