<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos photos sont prêtes — Téléchargez maintenant</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0F0C08; font-family: Georgia, 'Times New Roman', serif; color: #F5F0E8; }
        .container { max-width: 600px; margin: 0 auto; background: #1A1510; }
        .header { background: linear-gradient(135deg, #0a1f12 0%, #1A2D1A 100%); padding: 40px 40px 32px; border-bottom: 1px solid rgba(52,211,153,0.3); text-align: center; }
        .logo { font-size: 11px; letter-spacing: 4px; color: #C9A84C; text-transform: uppercase; margin-bottom: 24px; }
        .header h1 { font-size: 22px; color: #F5F0E8; font-weight: normal; line-height: 1.4; }
        .badge { display: inline-block; background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.3); color: #34d399; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; margin-top: 12px; }
        .body { padding: 40px; }
        .greeting { font-size: 16px; color: #F5F0E8; margin-bottom: 20px; }
        .text { font-size: 14px; color: #9E9085; line-height: 1.7; margin-bottom: 16px; }
        .order-box { background: #0F0C08; border: 1px solid rgba(52,211,153,0.2); border-radius: 2px; padding: 20px 24px; margin: 28px 0; }
        .order-box-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(201,168,76,0.08); font-size: 13px; }
        .order-box-row:last-child { border-bottom: none; }
        .order-box-label { color: #7A6E5E; }
        .order-box-value { color: #F5F0E8; font-weight: bold; }
        .order-box-price { color: #34d399; font-size: 16px; font-weight: bold; }
        /* Boutons d'action */
        .actions { display: table; width: 100%; border-collapse: separate; border-spacing: 0; margin: 32px 0; }
        .action-cell { display: table-cell; text-align: center; padding: 0 8px; }
        .cta-primary { display: inline-block; background: linear-gradient(135deg, #059669, #34d399); color: #0F0C08; text-decoration: none; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 16px 32px; border-radius: 1px; }
        .cta-secondary { display: inline-block; background: transparent; border: 1px solid rgba(201,168,76,0.5); color: #C9A84C; text-decoration: none; font-size: 12px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 15px 28px; border-radius: 1px; }
        .divider-label { text-align: center; margin: 20px 0 8px; color: #4A3E2E; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; }
        .note { background: rgba(52,211,153,0.06); border-left: 3px solid rgba(52,211,153,0.4); padding: 14px 18px; margin: 24px 0; font-size: 12px; color: #9E9085; line-height: 1.6; }
        .expiry { text-align: center; color: #4A3E2E; font-size: 11px; margin-top: 8px; }
        .footer { background: #0F0C08; border-top: 1px solid rgba(201,168,76,0.1); padding: 28px 40px; text-align: center; }
        .footer p { font-size: 11px; color: #4A3E2E; line-height: 1.7; }
        .footer a { color: #C9A84C; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    @php
        $htCents  = (int) ($order->total_price_cents ?? $order->base_price_cents ?? 0);
        $ttcCents = $htCents + (int) round($htCents * 0.20);
        $isFree   = $ttcCents === 0;
        $photoCount = $order->getMedia('retouched')
            ->filter(fn($m) => !$m->getCustomProperty('is_rejected', false))
            ->count();
        $photoCount = $photoCount ?: ($order->photo_count ?? 0);
    @endphp

    <div class="header">
        <div class="logo">OmnyRestore</div>
        <h1>Vos photos sont prêtes<br>à télécharger ✨</h1>
        <div class="badge">Livraison disponible</div>
    </div>

    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }},</p>

        <p class="text">
            Bonne nouvelle ! Votre commande <strong>{{ $order->reference }}</strong>
            est finalisée. Vos {{ $photoCount }} photo{{ $photoCount > 1 ? 's' : '' }}
            restaurée{{ $photoCount > 1 ? 's' : '' }} sont disponibles en téléchargement.
        </p>

        <div class="order-box">
            <div class="order-box-row">
                <span class="order-box-label">Référence</span>
                <span class="order-box-value">{{ $order->reference }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Photos livrées</span>
                <span class="order-box-value">{{ $photoCount }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">Date de livraison</span>
                <span class="order-box-value">{{ $order->delivered_at?->format('d/m/Y à H:i') ?? now()->format('d/m/Y à H:i') }}</span>
            </div>
            <div class="order-box-row">
                <span class="order-box-label">{{ $isFree ? 'Montant' : 'Montant réglé TTC' }}</span>
                <span class="order-box-price">
                    {{ $isFree ? 'Offert ✓' : number_format($ttcCents / 100, 2, ',', ' ') . ' €' }}
                </span>
            </div>
        </div>

        {{-- Boutons d'action principaux --}}
        <div class="divider-label">Télécharger</div>
        <div class="actions">
            <div class="action-cell">
                <a href="{{ route('client.orders.download', $order) }}" class="cta-primary">
                    ⬇ Archive ZIP
                </a>
            </div>
            <div class="action-cell">
                <a href="{{ route('client.orders.invoice', $order) }}" class="cta-secondary">
                    📄 Facture PDF
                </a>
            </div>
        </div>

        <div class="note">
            <strong>Important :</strong> Votre archive ZIP sera disponible pendant
            <strong>90 jours</strong> à compter d'aujourd'hui.
            Pensez à sauvegarder vos photos sur un support personnel (disque dur externe, cloud…).
        </div>

        <p class="expiry">
            Lien d'accès expirant le
            {{ $order->zip_expires_at?->format('d/m/Y') ?? now()->addDays(90)->format('d/m/Y') }}
        </p>

        <p class="text" style="margin-top: 24px;">
            Une question sur votre commande ? Répondez directement à cet email ou écrivez à
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
