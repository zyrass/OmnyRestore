<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
    .page { padding: 40px 50px; }

    /* En-tête */
    .header { border-bottom: 2px solid #1a1a1a; padding-bottom: 20px; margin-bottom: 30px; }
    .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }
    .brand-sub { font-size: 9px; letter-spacing: 2px; color: #666; margin-top: 2px; }
    .invoice-meta { text-align: right; }
    .invoice-meta .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; }
    .invoice-meta .value { font-size: 13px; font-weight: bold; }
    .invoice-meta .date { font-size: 10px; color: #555; margin-top: 4px; }

    /* Parties */
    .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
    .party { width: 48%; }
    .party-label { font-size: 8px; text-transform: uppercase; letter-spacing: 1.5px; color: #999; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
    .party-name { font-size: 12px; font-weight: bold; margin-bottom: 2px; }
    .party-detail { font-size: 10px; color: #555; line-height: 1.5; }

    /* Tableau */
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    thead th { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; }
    thead th:last-child, td:last-child { text-align: right; }
    tbody td { padding: 10px; border-bottom: 1px solid #f0f0f0; font-size: 10px; line-height: 1.5; }
    tbody tr:last-child td { border-bottom: none; }

    /* Totaux */
    .totals { margin-left: auto; width: 280px; }
    .total-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 10px; color: #555; }
    .total-row.ht { border-top: 1px solid #eee; padding-top: 8px; }
    .total-row.tva { color: #777; }
    .total-row.ttc { border-top: 2px solid #1a1a1a; margin-top: 4px; padding-top: 8px; font-size: 14px; font-weight: bold; color: #1a1a1a; }
    .total-row.discount { color: #1a7a3f; }

    /* Pied */
    .footer { margin-top: 50px; padding-top: 15px; border-top: 1px solid #eee; font-size: 9px; color: #999; text-align: center; line-height: 1.6; }
    .paid-stamp { display: inline-block; border: 2px solid #1a7a3f; color: #1a7a3f; padding: 4px 14px; font-weight: bold; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px; }
    .ai-note { background: #f9f9f9; border-left: 3px solid #ddd; padding: 8px 12px; font-size: 9px; color: #888; margin-bottom: 20px; line-height: 1.5; }
</style>
</head>
<body>
<div class="page">

    {{-- En-tête --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="brand">OmnyRestore</div>
                <div class="brand-sub">Restauration photographique professionnelle</div>
            </div>
            <div class="invoice-meta">
                <div class="label">Facture</div>
                <div class="value">{{ $order->reference }}</div>
                <div class="date">Émise le {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="parties">
        <div class="party">
            <div class="party-label">Prestataire</div>
            <div class="party-name">OmnyRestore</div>
            <div class="party-detail">
                Service de restauration photographique IA<br>
                contact@omnyrestore.fr
            </div>
        </div>
        <div class="party">
            <div class="party-label">Client</div>
            <div class="party-name">{{ $order->user->name }}</div>
            <div class="party-detail">
                {{ $order->user->email }}
            </div>
        </div>
    </div>

    {{-- Tampon payé --}}
    <div style="text-align:center; margin-bottom: 20px;">
        <span class="paid-stamp">✓ Payée</span>
    </div>

    {{-- Tableau des prestations --}}
    @php
        $baseHt   = ($order->base_price_cents ?? 0);
        $discount = ($order->discount_cents ?? 0);
        $ht       = max(0, $baseHt - $discount);
        $tvaRate  = $order->tva_rate ?? 20;
        $tva      = round($ht * $tvaRate / 100);
        $ttc      = $ht + $tva;
        $nPhotos  = $order->photo_count ?? 1;
        $aiCost   = $nPhotos * 1; // 0,01 € HT par photo estimé
        $level    = $order->damage_level ?? 'standard';
        $levelLabel = match($level) {
            'light'  => 'Standard (légère dégradation)',
            'medium' => 'Avancée (dégradation modérée)',
            'heavy'  => 'Complète (dégradation importante)',
            default  => 'Standard',
        };
    @endphp

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qté</th>
                <th>Prix unitaire HT</th>
                <th>Total HT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Restauration photographique — Niveau {{ $levelLabel }}</strong><br>
                    <span style="color:#888;">Analyse IA + restauration + upscale haute résolution</span>
                </td>
                <td>{{ $nPhotos }}</td>
                <td>{{ number_format($baseHt / $nPhotos / 100, 2, ',', ' ') }} €</td>
                <td>{{ number_format($baseHt / 100, 2, ',', ' ') }} €</td>
            </tr>
            @if ($discount > 0)
            <tr>
                <td>
                    <strong style="color:#1a7a3f;">Code de réduction</strong>
                    @if ($order->coupon_code)
                    <span style="color:#888;"> — {{ $order->coupon_code }}</span>
                    @endif
                </td>
                <td>—</td>
                <td>—</td>
                <td style="color:#1a7a3f;">-{{ number_format($discount / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- Note IA --}}
    <div class="ai-note">
        <strong>À propos du coût IA :</strong> Chaque photo bénéficie d'une analyse par intelligence artificielle (GPT-4o Vision)
        pour déterminer le niveau de restauration optimal. Ce coût (~0,01 € HT/photo) est inclus dans le tarif de restauration.
        Il garantit une évaluation précise et un rendu adapté à l'état réel de votre photo.
    </div>

    {{-- Totaux --}}
    <div class="totals">
        @if ($discount > 0)
        <div class="total-row discount">
            <span>Sous-total HT avant remise</span>
            <span>{{ number_format($baseHt / 100, 2, ',', ' ') }} €</span>
        </div>
        <div class="total-row discount">
            <span>Réduction appliquée</span>
            <span>-{{ number_format($discount / 100, 2, ',', ' ') }} €</span>
        </div>
        @endif
        <div class="total-row ht">
            <span>Total HT</span>
            <span>{{ number_format($ht / 100, 2, ',', ' ') }} €</span>
        </div>
        <div class="total-row tva">
            <span>TVA {{ $tvaRate }}%</span>
            <span>{{ number_format($tva / 100, 2, ',', ' ') }} €</span>
        </div>
        <div class="total-row ttc">
            <span>Total TTC</span>
            <span>{{ number_format($ttc / 100, 2, ',', ' ') }} €</span>
        </div>
    </div>

    {{-- Pied de page --}}
    <div class="footer">
        OmnyRestore · contact@omnyrestore.fr<br>
        Facture générée automatiquement le {{ now()->format('d/m/Y à H:i') }}<br>
        Cette facture fait foi de règlement complet de la commande {{ $order->reference }}.
    </div>

</div>
</body>
</html>
