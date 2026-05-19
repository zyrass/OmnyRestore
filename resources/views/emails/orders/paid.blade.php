<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirmé — OmnyRestore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #0F0C08; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #F5F0E8; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 0 auto; background-color: #1A1510; border: 1px solid rgba(201, 168, 76, 0.15); }
        
        /* Header avec dégradé subtil or/sombre */
        .header { background: linear-gradient(135deg, #16120E 0%, #251D13 100%); padding: 50px 40px 40px; border-bottom: 1px solid rgba(201,168,76,0.15); text-align: center; }
        .logo { margin-bottom: 24px; }
        
        /* Badge succès */
        .badge { display: inline-block; background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.25); color: #34D399; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 24px; }
        
        .header h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; }
        
        .email-body { padding: 40px; background-color: #1A1510; }
        .greeting { font-family: Georgia, 'Times New Roman', serif; font-size: 19px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 15px; color: #C4B5A9; line-height: 1.7; margin-bottom: 24px; }
        
        /* Boîte de transaction style facture */
        .order-box { background-color: #0F0C08; border: 1px solid rgba(201,168,76,0.2); border-radius: 4px; padding: 30px; margin: 32px 0; }
        .order-box-header { display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
        .order-box-title { color: #C9A84C; font-size: 11px; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; text-align: center; }
        .order-box-row { display: flex; justify-content: space-between; padding: 14px 0; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .order-box-row:last-child { border-bottom: none; padding-bottom: 0; }
        .order-box-label { color: #8F8073; }
        .order-box-value { color: #F5F0E8; font-weight: 500; }
        .order-box-total { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; margin-top: 12px; border-top: 1px dashed rgba(201,168,76,0.3); }
        .order-box-total-label { color: #F5F0E8; font-size: 15px; font-weight: bold; }
        .order-box-total-value { color: #34D399; font-size: 22px; font-weight: bold; }

        /* Boîte d'information sur la préparation */
        .prep-box { background-color: rgba(201,168,76,0.03); border: 1px solid rgba(201,168,76,0.15); border-left: 3px solid #C9A84C; padding: 20px 24px; margin: 32px 0; }
        .prep-title { color: #C9A84C; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .prep-text { font-size: 14px; color: #C4B5A9; line-height: 1.6; }

        .footer { background-color: #0F0C08; border-top: 1px solid rgba(201,168,76,0.15); padding: 32px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #6E6152; line-height: 1.8; }
        .footer a { color: #8F8073; text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #C9A84C; }
    </style>
</head>
<body>
<div class="container">
    <div class="header" style="text-align: center;">
        <div class="logo" style="text-align: center; margin-bottom: 24px;">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 45px; width: auto; display: inline-block; margin: 0 auto;">
        </div>
        <div class="badge" style="display: inline-block; background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.25); color: #34D399; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 24px; text-align: center;">Paiement Validé</div>
        <h1 style="text-align: center; font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; margin-top: 0;">Merci pour votre<br>confiance !</h1>
    </div>

    <div class="email-body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Nous vous confirmons la bonne réception de votre paiement pour la commande <strong>{{ $order->reference }}</strong>. Toute l'équipe d'OmnyRestore vous remercie chaleureusement !
        </p>

        <div class="order-box">
            <div class="order-box-header">
                <div class="order-box-title">Détails de la transaction</div>
            </div>
            
            <div class="order-box-row">
                <span class="order-box-label">Référence commande</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Date du paiement</span>
                <span class="order-box-value">{{ now()->format('d/m/Y à H:i') }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Photos restaurées</span>
                <span class="order-box-value">{{ $order->getActivePhotosCount() }} photo(s)</span>
            </div>
            
            <div class="order-box-total">
                <span class="order-box-total-label">Montant réglé</span>
                <span class="order-box-total-value">
                    {{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }} € <span style="font-size: 12px; color: #8F8073; font-weight: normal;">TTC</span>
                </span>
            </div>
        </div>

        <div class="prep-box">
            <div class="prep-title">⚙️ Préparation en cours</div>
            <div class="prep-text">
                Notre système génère actuellement votre archive sécurisée contenant vos photos en Haute Résolution (HD) et sans filigrane.<br><br>
                <strong>Vous recevrez un second email d'ici quelques instants</strong> avec votre lien de téléchargement exclusif et votre facture.
            </div>
        </div>

        <p class="text" style="text-align: center; margin-top: 32px; font-style: italic; font-size: 13px; color: #8F8073;">
            À tout de suite pour la livraison de vos souvenirs.
        </p>
    </div>

    <div class="footer">
        <p>
            <strong style="color: #8F8073;">OmnyRestore</strong> — Atelier de Restauration Photographique<br>
            <a href="{{ route('legal.mentions') }}">Mentions légales</a> ·
            <a href="{{ route('legal.privacy') }}">Confidentialité</a> ·
            <a href="{{ route('legal.cgv') }}">Conditions Générales</a>
        </p>
        <p style="margin-top:16px;">
            Cet email confirme votre paiement. Aucune action n'est requise de votre part.<br>
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
