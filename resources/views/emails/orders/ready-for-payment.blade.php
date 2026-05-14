<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos photos restaurées sont prêtes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: Georgia, 'Times New Roman', serif; color: #F5F0E8; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        .header { background: linear-gradient(135deg, #1A1510 0%, #2A1F12 100%); padding: 40px 40px 32px; border-bottom: 1px solid rgba(201,168,76,0.3); text-align: center; }
        .logo { font-size: 11px; letter-spacing: 4px; color: #C9A84C; text-transform: uppercase; margin-bottom: 24px; }
        .header h1 { font-size: 22px; color: #F5F0E8; font-weight: normal; line-height: 1.4; }
        .header p { color: #C9A84C; font-size: 13px; margin-top: 8px; letter-spacing: 1px; }
        .body { padding: 40px; }
        .greeting { font-size: 16px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 14px; color: #9E9085; line-height: 1.7; margin-bottom: 16px; }
        .order-box { background: #0F0C08; border: 1px solid rgba(201,168,76,0.2); border-radius: 2px; padding: 20px 24px; margin: 28px 0; }
        .order-box-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(201,168,76,0.08); font-size: 13px; }
        .order-box-row:last-child { border-bottom: none; }
        .order-box-label { color: #7A6E5E; }
        .order-box-value { color: #F5F0E8; font-weight: bold; }
        .order-box-price { color: #C9A84C; font-size: 18px; font-weight: bold; }
        .order-box-free { color: #34d399; font-size: 18px; font-weight: bold; }
        .cta-wrapper { text-align: center; margin: 32px 0; }
        .cta { display: inline-block; background: linear-gradient(135deg, #C9A84C, #E8C97A); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 40px; border-radius: 1px; }
        .cta-free { display: inline-block; background: linear-gradient(135deg, #059669, #34d399); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 40px; border-radius: 1px; }
        .note { background: rgba(201,168,76,0.06); border-left: 3px solid rgba(201,168,76,0.4); padding: 14px 18px; margin: 24px 0; font-size: 12px; color: #9E9085; line-height: 1.6; }
        .note-free { background: rgba(52,211,153,0.06); border-left: 3px solid rgba(52,211,153,0.4); padding: 14px 18px; margin: 24px 0; font-size: 12px; color: #9E9085; line-height: 1.6; }
        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 28px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.7; }
        .footer a { color: #C9A84C; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    @php
        $ttcCents = $order->getAmountTtcCents();
        $isFree   = $ttcCents === 0;
        $photoCount = $order->getActivePhotosCount();
    @endphp

    <div class="header">
        <div class="logo">
            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="OmnyRestore" style="height: 60px; width: auto;">
        </div>
        <h1>Vos photos restaurées<br>sont prêtes</h1>
        <p>{{ $isFree ? 'Votre coupon a été appliqué — téléchargement disponible' : 'Aperçu disponible — paiement requis pour télécharger' }}</p>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        @if ($isFree)
        <p class="text">
            Notre équipe a terminé la restauration de vos photos. Grâce à votre coupon,
            votre commande est <strong>entièrement offerte</strong> ! Vous pouvez télécharger
            vos photos en haute résolution directement depuis votre espace client.
        </p>
        @else
        <p class="text">
            Notre équipe a terminé la restauration de vos photos. Vous pouvez maintenant
            consulter un <strong>aperçu filigrané</strong> du résultat directement dans votre espace client.
        </p>

        <p class="text">
            Si le rendu vous satisfait, procédez au paiement pour recevoir vos photos
            en haute résolution, sans filigrane.
        </p>
        @endif

        <div class="order-box">
            <div class="order-box-row">
                <span class="order-box-label">Référence</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Nombre de photos</span>
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
                <span class="order-box-label">Montant TTC</span>
                <span class="order-box-price">
                    {{ number_format($ttcCents / 100, 2, ',', ' ') . ' €' }}
                </span>
            </div>
        </div>

        <div class="cta-wrapper">
            <a href="{{ $signedUrl }}" class="{{ $isFree ? 'cta-free' : 'cta' }}">
                {{ $isFree ? '↓ Télécharger mes photos' : "Voir l'aperçu & Payer" }}
            </a>
        </div>

        @if (!$isFree)
        <div class="note">
            <strong>Ce lien est votre clé d'accès :</strong> il déverrouille l'aperçu filigrané de vos
            photos dans votre espace client. Il est valable <strong>7 jours</strong> et ne fonctionne
            qu'une seule fois. Si vous en avez besoin d'un nouveau, connectez-vous et cliquez sur
            « Je n'ai pas reçu l'email » depuis votre page commande.
        </div>
        @else
        <div class="note-free">
            <strong>Archive en cours de préparation :</strong> Votre ZIP sera disponible dans quelques minutes.
            Un email vous sera envoyé dès que le téléchargement sera prêt.
        </div>
        @endif


        <p class="text">
            Une question ? Répondez directement à cet email ou contactez-nous à
            <a href="mailto:contact@omnyrestore.fr" style="color:#C9A84C">contact@omnyrestore.fr</a>
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
            Vous recevez cet email car vous avez passé une commande sur OmnyRestore.<br>
            © {{ date('Y') }} OmnyRestore. Tous droits réservés.
        </p>
    </div>
</div>
</body>
</html>
