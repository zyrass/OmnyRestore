<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos photos sont prêtes — Téléchargez maintenant</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #0F0C08; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #F5F0E8; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 0 auto; background-color: #1A1510; border: 1px solid rgba(201, 168, 76, 0.15); }
        
        /* Header avec dégradé subtil vert/sombre */
        .header { background: linear-gradient(135deg, #0d1a11 0%, #1A2D1A 100%); padding: 50px 40px 40px; border-bottom: 1px solid rgba(52,211,153,0.25); text-align: center; }
        .logo { margin-bottom: 24px; }
        
        /* Badge livraison */
        .badge { display: inline-block; background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.25); color: #34D399; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; margin-bottom: 24px; }
        
        .header h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 28px; color: #F5F0E8; font-weight: normal; line-height: 1.3; margin-bottom: 12px; }
        
        .email-body { padding: 40px; background-color: #1A1510; }
        .greeting { font-family: Georgia, 'Times New Roman', serif; font-size: 19px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 15px; color: #C4B5A9; line-height: 1.7; margin-bottom: 24px; }
        
        /* Boîte de transaction style facture */
        .order-box { background-color: #0F0C08; border: 1px solid rgba(52,211,153,0.2); border-radius: 4px; padding: 30px; margin: 32px 0; }
        .order-box-row { display: flex; justify-content: space-between; padding: 14px 0; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .order-box-row:last-child { border-bottom: none; padding-bottom: 0; }
        .order-box-label { color: #8F8073; }
        .order-box-value { color: #F5F0E8; font-weight: bold; }
        .order-box-price { color: #34D399; font-size: 18px; font-weight: bold; }

        /* Boutons d'action */
        .cta-primary { display: inline-block; background: linear-gradient(135deg, #059669, #34d399); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 32px; border-radius: 2px; text-align: center; }
        .cta-secondary { display: inline-block; background: transparent; border: 1px solid rgba(201,168,76,0.4); color: #C9A84C; text-decoration: none; font-size: 12px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 15px 28px; border-radius: 2px; text-align: center; }
        
        .divider-label { text-align: center; margin: 20px 0 8px; color: #8F8073; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; }
        
        /* Note importante */
        .note { background-color: rgba(52,211,153,0.03); border: 1px solid rgba(52,211,153,0.15); border-left: 3px solid #34D399; padding: 20px 24px; margin: 32px 0; font-size: 13px; color: #C4B5A9; line-height: 1.6; }
        .expiry { text-align: center; color: #8F8073; font-size: 11px; margin-top: 8px; }
        
        .footer { background-color: #0F0C08; border-top: 1px solid rgba(201,168,76,0.15); padding: 32px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #6E6152; line-height: 1.8; }
        .footer a { color: #8F8073; text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #C9A84C; }
    </style>
</head>
<body>
<div class="container">
    @php
        $ttcCents   = $order->getAmountTtcCents();
        $isFree     = $ttcCents === 0;
        $photoCount = $order->getActivePhotosCount();
    @endphp

    <div class="header">
        <div class="logo">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 45px; width: auto;">
        </div>
        <div class="badge">Livraison Disponible</div>
        <h1>Vos photos sont prêtes<br>à télécharger ✨</h1>
    </div>

    <div class="email-body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Excellente nouvelle ! Votre commande <strong>{{ $order->reference }}</strong> est finalisée. Vos {{ $photoCount }} photo{{ $photoCount > 1 ? 's' : '' }} restaurée{{ $photoCount > 1 ? 's' : '' }} sont dès maintenant disponibles en téléchargement.
        </p>

        <div class="order-box">
            <div class="order-box-row">
                <span class="order-box-label">Référence commande</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Photos livrées</span>
                <span class="order-box-value">{{ $photoCount }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Détails restauration</span>
                <span class="order-box-value">
                    @php
                        $breakdown = $order->getDamageBreakdown();
                        if (count($breakdown) > 1) {
                            $labels = [];
                            if (isset($breakdown['heavy']))  $labels[] = $breakdown['heavy'] . ' Compl.';
                            if (isset($breakdown['medium'])) $labels[] = $breakdown['medium'] . ' Avanc.';
                            if (isset($breakdown['light']))  $labels[] = $breakdown['light'] . ' Std';
                            echo 'Mixte (' . implode(', ', $labels) . ')';
                        } else {
                            echo match($order->damage_level) {
                                'heavy'  => 'Complète',
                                'medium' => 'Avancée',
                                default  => 'Standard',
                            };
                        }
                    @endphp
                </span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Date de livraison</span>
                <span class="order-box-value">{{ $order->delivered_at?->format('d/m/Y à H:i') ?? now()->format('d/m/Y à H:i') }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Montant réglé</span>
                <span class="order-box-price">
                    {{ number_format($ttcCents / 100, 2, ',', ' ') . ' €' }} <span style="font-size: 11px; color: #8F8073; font-weight: normal;">TTC</span>
                </span>
            </div>
        </div>

        <div class="divider-label">Accéder à vos photos</div>
        <div style="text-align:center; margin: 28px 0;">
            <a href="{{ route('client.orders.show', $order) }}" class="cta-primary" style="display:inline-block;">
                ✨ Télécharger mes photos
            </a>
            <p style="font-size:11px; color:#8F8073; margin-top:10px;">
                Connectez-vous à votre espace client pour accéder à votre archive HD.
            </p>
        </div>

        <div style="text-align:center; margin: 16px 0 28px;">
            <a href="{{ route('client.orders.invoice', $order) }}" class="cta-secondary">
                📄 Télécharger la facture PDF
            </a>
        </div>

        <div class="note">
            <strong>Important :</strong> Votre archive ZIP sera disponible pendant <strong>90 jours</strong> à compter d'aujourd'hui. Pensez à sauvegarder vos photos sur un support personnel (disque dur externe, cloud…).
        </div>

        <p class="expiry">
            Archive disponible jusqu'au {{ $order->zip_expires_at?->format('d/m/Y') ?? now()->addDays(90)->format('d/m/Y') }}
        </p>

        <p class="text" style="margin-top: 24px; text-align: center; font-size: 13px; color: #8F8073;">
            Une question ? Notre équipe support est à votre disposition directement depuis votre espace client.
        </p>
    </div>

    <div style="background-color:#0F0C08; border-top:1px solid rgba(201,168,76,0.1); padding:24px 40px; font-size:10px; color:#6E6152; line-height:1.8; text-align:center;">
        <p><strong style="color:#8F8073; letter-spacing:1px; text-transform:uppercase; font-size:9px;">Informations sur la conservation de vos données (RGPD)</strong></p>
        <p style="margin-top:6px">
            Vos photos originales et restaurées sont conservées <strong style="color:#8F8073">6 mois</strong> après livraison, puis supprimées automatiquement.<br>
            Votre facture est conservée <strong style="color:#8F8073">10 ans</strong> conformément aux obligations comptables françaises (art. L. 123-22 C. com.).<br>
            Pour exercer votre droit à l'effacement, accédez à <a href="{{ config('app.url') }}/client/account/delete" style="color:#8F8073; text-decoration: underline;">Supprimer mon compte</a>.
        </p>
    </div>

    <div class="footer">
        <p>
            OmnyRestore — Restauration photographique<br>
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
