<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>

/* ══════════════════════════════════════════════════════════
   RESET
   ══════════════════════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }

/* ══════════════════════════════════════════════════════════
   BASE — taille volontairement grande pour remplir l'A4
   ══════════════════════════════════════════════════════════ */
body {
    /* DejaVu Sans est bundlé avec DomPDF et supporte l'Unicode étendu (✓, ·, −, ©) */
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 16px;
    color: #1A1208;
    background: #FFFFFF;
    line-height: 1.65;
}

/* ══════════════════════════════════════════════════════════
   FOOTER FIXE EN BAS DE PAGE
   ══════════════════════════════════════════════════════════ */
.footer-fixed {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px 60px;
    border-top: 1px solid #DDD8CE;
    background: #FFFFFF;
    font-size: 11px;
    color: #A09080;
    text-align: center;
    line-height: 1.6;
}
.footer-fixed strong { color: #7A6E5E; }

/* ══════════════════════════════════════════════════════════
   CONTENU — padding-bottom pour ne pas masquer le footer
   ══════════════════════════════════════════════════════════ */
.page { padding-bottom: 80px; }

/* ══════════════════════════════════════════════════════════
   HEADER — blanc, bordure dorée en haut, pas d'encre noire
   ══════════════════════════════════════════════════════════ */
.header-bg {
    border-top: 6px solid #C9A84C;
    background-color: #FDFAF4;
    padding: 40px 60px 34px;
    border-bottom: 1px solid #E8E2D4;
}
.clearfix::after { content: ''; display: table; clear: both; }

.header-logo { float: left; width: 55%; }
.brand {
    font-size: 30px;
    font-weight: bold;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: #1A1208;
}
.brand-accent { color: #C9A84C; }
.brand-sub {
    font-size: 11px;
    letter-spacing: 2px;
    color: #9E9085;
    margin-top: 4px;
    text-transform: uppercase;
}

.header-right { float: right; width: 40%; text-align: right; }
.inv-label {
    font-size: 11px;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #B0A090;
}
.inv-ref {
    font-size: 22px;
    font-weight: bold;
    color: #1A1208;
    margin-top: 4px;
}
.inv-date {
    font-size: 12px;
    color: #9E9085;
    margin-top: 6px;
    line-height: 1.7;
}

/* ══════════════════════════════════════════════════════════
   CORPS
   ══════════════════════════════════════════════════════════ */
.body { padding: 40px 60px; }

/* ── Section label ─────────────────────────────────────── */
.section-label {
    font-size: 10px;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #B0A090;
    padding-bottom: 6px;
    border-bottom: 1px solid #EDE8E0;
    margin-bottom: 14px;
}

/* ── Parties prestataire / client ─────────────────────── */
.parties-wrap {
    margin-bottom: 36px;
}
.party-block {
    display: inline-block;
    width: 48%;
    vertical-align: top;
}
.party-block.right { text-align: right; }
.party-name {
    font-size: 17px;
    font-weight: bold;
    color: #1A1208;
    margin-bottom: 5px;
}
.party-detail {
    font-size: 13px;
    color: #7A6E5E;
    line-height: 1.7;
}

/* ── Tampon PAYÉE ─────────────────────────────────────── */
.stamp-wrap { text-align: center; margin: 10px 0 34px; }
.stamp {
    display: inline-block;
    border: 2.5px solid #1a7a3f;
    color: #1a7a3f;
    padding: 10px 28px 10px;
    font-size: 15px;
    font-weight: bold;
    letter-spacing: 3px;
    text-transform: uppercase;
    line-height: 1;
    vertical-align: middle;
}

/* ── Tableau prestations ──────────────────────────────── */
table.items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
table.items thead tr { background-color: #F5F1E8; }
table.items thead th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9E9085;
    padding: 12px 14px;
    text-align: left;
    border-bottom: 2px solid #DDD8CE;
}
table.items thead th.r { text-align: right; }
table.items tbody td {
    padding: 16px 14px;
    font-size: 14px;
    border-bottom: 1px solid #F0EDE6;
    vertical-align: top;
    color: #1A1208;
}
table.items tbody td.r { text-align: right; }
.desc-main { font-weight: bold; font-size: 15px; }
.desc-sub { color: #9E9085; font-size: 12px; margin-top: 4px; }
.coupon-row td { color: #1a7a3f !important; background-color: #f0faf5; }
.coupon-label { font-weight: bold; }

/* ── Totaux ───────────────────────────────────────────── */
.totals-wrap { text-align: right; margin-top: 10px; }
table.totals {
    display: inline-table;
    width: 340px;
    border-collapse: collapse;
}
table.totals td {
    padding: 7px 0;
    font-size: 14px;
    color: #7A6E5E;
}
table.totals td.amount { text-align: right; padding-left: 28px; }
table.totals tr.sep td { border-top: 1px solid #EDE8E0; padding-top: 12px; }
table.totals tr.sep-bold td { border-top: 2.5px solid #1A1208; padding-top: 16px; }
table.totals tr.ttc-row td {
    font-size: 22px;
    font-weight: bold;
    color: #1A1208;
}
table.totals tr.free-row td {
    font-size: 20px;
    font-weight: bold;
    color: #1a7a3f;
}

/* ── Note IA ──────────────────────────────────────────── */
.note-ia {
    background: #FDFAF4;
    border-left: 4px solid #C9A84C;
    padding: 16px 20px;
    font-size: 12px;
    color: #9E9085;
    line-height: 1.75;
    margin-top: 36px;
}

</style>
</head>
<body>
<div class="page">

@php
    use App\Services\PhotoDamageAnalyzer;

    // Prix HT par niveau (en centimes)
    $LEVEL_PRICES = PhotoDamageAnalyzer::PRICES; // ['light'=>83, 'medium'=>167, 'heavy'=>250]
    $LEVEL_LABELS = [
        'light'  => 'Restauration Standard',
        'medium' => 'Restauration Avancée',
        'heavy'  => 'Restauration Complète',
    ];
    $LEVEL_DETAIL = [
        'light'  => 'Jaunissement, poussière légère, légères décolorations',
        'medium' => 'Rayures, décoloration forte, grain important, pliures',
        'heavy'  => 'Déchirures, dégâts eau, zones manquantes, moisissures',
    ];

    // ── Calcul des totaux depuis les custom_properties par photo ────────────
    // On regroupe les photos originals (non rejetées) par niveau d'IA
    $lineItems = $order->getMedia('originals')
        ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false))
        ->groupBy(fn($m) => $m->getCustomProperty('ai_level', 'light'))
        ->map(fn($photos, $lvl) => [
            'level'      => $lvl,
            'label'      => $LEVEL_LABELS[$lvl] ?? ucfirst($lvl),
            'detail'     => $LEVEL_DETAIL[$lvl] ?? '',
            'count'      => $photos->count(),
            'unit_ht_c'  => $LEVEL_PRICES[$lvl] ?? 83,
            'total_ht_c' => $photos->count() * ($LEVEL_PRICES[$lvl] ?? 83),
        ])
        ->sortBy('level') // light → medium → heavy
        ->values();

    // Si aucune donnée par photo (ancienne commande sans ai_level), fallback
    if ($lineItems->isEmpty()) {
        $fallbackLevel = $order->damage_level ?? 'light';
        $nPhotos       = (int) ($order->photo_count ?? 1);
        $lineItems = collect([[
            'level'      => $fallbackLevel,
            'label'      => $LEVEL_LABELS[$fallbackLevel] ?? 'Restauration',
            'detail'     => $LEVEL_DETAIL[$fallbackLevel] ?? '',
            'count'      => $nPhotos,
            'unit_ht_c'  => $LEVEL_PRICES[$fallbackLevel] ?? 83,
            'total_ht_c' => (int) ($order->base_price_cents ?? $nPhotos * 83),
        ]]);
    }

    // Totaux
    $baseHtC   = (int) $lineItems->sum('total_ht_c');
    $discountC = (int) ($order->discount_cents ?? 0);
    $htNetC    = max(0, $baseHtC - $discountC);
    $tvaRate   = 20;
    $tvaC      = (int) round($htNetC * $tvaRate / 100);
    $ttcC      = $htNetC + $tvaC;
    $isFree    = $ttcC === 0;

    $year       = $order->paid_at?->format('Y') ?? now()->format('Y');
    $seq        = str_pad(substr($order->reference, -4), 4, '0', STR_PAD_LEFT);
    $invoiceNum = "FAC-{$year}-{$seq}";
@endphp

{{-- ══════════════════════════════════════════════════
     FOOTER FIXE
     ══════════════════════════════════════════════════ --}}
<div class="footer-fixed">
    <strong>OmnyRestore</strong> &mdash; contact@omnyrestore.fr &mdash; omnyrestore.fr &nbsp;|&nbsp;
    Facture <strong>{{ $invoiceNum }}</strong> &mdash; Commande {{ $order->reference }}<br>
    G&eacute;n&eacute;r&eacute;e le {{ now()->format('d/m/Y') }} &mdash; Document officiel de r&egrave;glement &mdash; &copy; {{ date('Y') }} OmnyRestore
</div>

{{-- ══════════════════════════════════════════════════
     HEADER — blanc / crème, bordure dorée en haut
     ══════════════════════════════════════════════════ --}}
<div class="header-bg clearfix">
    <div class="header-logo">
        <div class="brand">OMNY<span class="brand-accent">RESTORE</span></div>
        <div class="brand-sub">Restauration photographique par IA</div>
    </div>
    <div class="header-right">
        <div class="inv-label">Facture</div>
        <div class="inv-ref">{{ $invoiceNum }}</div>
        <div class="inv-date">
            Émise le {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
            Réf. commande : <strong>{{ $order->reference }}</strong>
        </div>
    </div>
</div>

<div class="body">

    {{-- ══════════════════════════════════════════════
         PARTIES
         ══════════════════════════════════════════════ --}}
    <div class="parties-wrap clearfix">
        <div class="party-block">
            <div class="section-label">Prestataire</div>
            <div class="party-name">OmnyRestore</div>
            <div class="party-detail">
                Restauration photographique par intelligence artificielle<br>
                contact@omnyrestore.fr<br>
                omnyrestore.fr
            </div>
        </div>
        <div class="party-block right">
            <div class="section-label">Facturé à</div>
            <div class="party-name">{{ $order->user->name }}</div>
            <div class="party-detail">
                {{ $order->user->email }}<br>
                Client depuis {{ $order->user->created_at->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         TAMPON PAYÉE — table pour centrage fiable DomPDF
         ══════════════════════════════════════════════ --}}
    <table width="100%" style="margin: 0 0 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span class="stamp">&#10003; Pay&eacute;e &mdash; {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════════════
         TABLEAU PRESTATIONS
         ══════════════════════════════════════════════ --}}
    <div class="section-label">Détail de la prestation</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:50%">Description</th>
                <th style="width:10%">Qté</th>
                <th class="r" style="width:20%">Prix unit. HT</th>
                <th class="r" style="width:20%">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineItems as $line)
            <tr>
                <td>
                    <div class="desc-main">{{ $line['label'] }}</div>
                    <div class="desc-sub">
                        {{ $line['detail'] }}<br>
                        Analyse IA · retouche · amélioration résolution
                    </div>
                </td>
                <td>{{ $line['count'] }}</td>
                <td class="r">{{ number_format($line['unit_ht_c'] / 100, 2, ',', ' ') }} €</td>
                <td class="r">{{ number_format($line['total_ht_c'] / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endforeach
            @if ($discountC > 0)
            <tr class="coupon-row">
                <td class="coupon-label">
                    Code de réduction{{ $order->coupon_code ? ' · ' . strtoupper($order->coupon_code) : '' }}
                    <div class="desc-sub">Remise appliquée sur la prestation</div>
                </td>
                <td>—</td>
                <td class="r">—</td>
                <td class="r">-{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- ══════════════════════════════════════════════
         TOTAUX
         ══════════════════════════════════════════════ --}}
    <div class="totals-wrap">
        <table class="totals">
            @if ($discountC > 0)
            <tr>
                <td>Sous-total HT</td>
                <td class="amount">{{ number_format($baseHtC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td style="color:#1a7a3f">Remise</td>
                <td class="amount" style="color:#1a7a3f">-{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endif
            <tr class="{{ $discountC > 0 ? 'sep' : '' }}">
                <td>Total HT net</td>
                <td class="amount">{{ number_format($htNetC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td style="color:#B0A090">TVA {{ $tvaRate }}%</td>
                <td class="amount" style="color:#B0A090">{{ number_format($tvaC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="sep-bold {{ $isFree ? 'free-row' : 'ttc-row' }}">
                <td>Total TTC</td>
                <td class="amount">
                    @if ($isFree) OFFERT
                    @else {{ number_format($ttcC / 100, 2, ',', ' ') }} &euro;
                    @endif
                </td>
            </tr>
            @if ($order->payment_intent_id && !str_starts_with($order->payment_intent_id, 'coupon_free_'))
            <tr>
                <td style="color:#B0A090; font-size:11px">Réf. paiement</td>
                <td class="amount" style="color:#B0A090; font-size:11px">{{ $order->payment_intent_id }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ══════════════════════════════════════════════
         NOTE IA
         ══════════════════════════════════════════════ --}}
    <div class="note-ia">
        <strong>Transparence tarifaire :</strong> Le tarif inclut l'analyse de chaque photo par intelligence artificielle
        (GPT-4o Vision) afin d'évaluer précisément l'état de dégradation et d'adapter le traitement.
        Ce coût d'analyse (~0,01 € HT/photo) est intégré au tarif global et non facturé séparément.
    </div>

</div>{{-- /.body --}}
</div>{{-- /.page --}}
</body>
</html>
