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
    padding: 60px 60px 60px;
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
    padding: 12px 6px;
    text-align: left;
    border-bottom: 2px solid #DDD8CE;
}
table.items thead th.r { text-align: right; }
table.items tbody td {
    padding: 16px 6px;
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

    $LEVEL_PRICES = PhotoDamageAnalyzer::PRICES;       // HT en centimes par niveau
    $LEVEL_PRICES_TTC = PhotoDamageAnalyzer::PRICES_TTC; // TTC exact en centimes par niveau
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

    // ── Source de vérité : photos RETOUCHÉES non rejetées par le client ──────
    // IMPORTANT : is_rejected est marqué sur la collection 'retouched', PAS sur 'originals'.
    // La facture doit refléter exactement ce qui est livré (et ce qui a été payé).
    $activeRetouched = $order->getMedia('retouched')
        ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

    // Regrouper par niveau IA (ai_level propagé depuis les originals à l'upload admin)
    $lineItems = $activeRetouched
        ->groupBy(fn($m) => $m->getCustomProperty('ai_level', $order->damage_level ?? 'light'))
        ->map(fn($photos, $lvl) => [
            'level'       => $lvl,
            'label'       => $LEVEL_LABELS[$lvl] ?? ucfirst($lvl),
            'detail'      => $LEVEL_DETAIL[$lvl] ?? '',
            'count'       => $photos->count(),
            'unit_ht_c'   => $LEVEL_PRICES[$lvl] ?? 83,
            'unit_ttc_c'  => $LEVEL_PRICES_TTC[$lvl] ?? 100,
            'total_ht_c'  => $photos->count() * ($LEVEL_PRICES[$lvl] ?? 83),
            'total_ttc_c' => $photos->count() * ($LEVEL_PRICES_TTC[$lvl] ?? 100),
        ])
        ->sortBy('level')
        ->values();

    // Fallback : pas de photos retouchées analysées (ancienne commande sans ai_level)
    if ($lineItems->isEmpty()) {
        $fallbackLevel = $order->damage_level ?? 'light';
        // Utiliser le nombre de photos actives retouchées, sinon photo_count
        $nPhotos = max(1, $activeRetouched->count() ?: (int) ($order->photo_count ?? 1));
        $lineItems = collect([[
            'level'       => $fallbackLevel,
            'label'       => $LEVEL_LABELS[$fallbackLevel] ?? 'Restauration',
            'detail'      => $LEVEL_DETAIL[$fallbackLevel] ?? '',
            'count'       => $nPhotos,
            'unit_ht_c'   => $LEVEL_PRICES[$fallbackLevel] ?? 83,
            'unit_ttc_c'  => $LEVEL_PRICES_TTC[$fallbackLevel] ?? 100,
            'total_ht_c'  => $nPhotos * ($LEVEL_PRICES[$fallbackLevel] ?? 83),
            'total_ttc_c' => $nPhotos * ($LEVEL_PRICES_TTC[$fallbackLevel] ?? 100),
        ]]);
    }

    // ── Totaux — source de vérité : photos retouchées actives ──────────────
    // TTC réel = somme PRICES_TTC[ai_level] des retouched non rejetés
    // = montant identique à ce qui a été envoyé à Stripe au checkout.
    $discountC = (int) ($order->discount_cents ?? 0);
    $baseHtC   = (int) $lineItems->sum('total_ht_c');
    $baseTtcC  = (int) $lineItems->sum('total_ttc_c');
    $htNetC    = max(0, $baseHtC - $discountC);
    $ttcC      = max(0, $baseTtcC - $discountC);   // remise appliquée sur le TTC
    $tvaC      = $ttcC - $htNetC;                  // TVA exacte (pas d'arrondi flottant)
    $isFree    = $ttcC === 0;

    $year       = $order->paid_at?->format('Y') ?? now()->format('Y');
    $seq        = str_pad(substr($order->reference, -4), 4, '0', STR_PAD_LEFT);
    $invoiceNum = "FAC-{$year}-{$seq}";
    $tvaRate    = (float) ($order->tva_rate ?? 20);
@endphp


{{-- ══════════════════════════════════════════════════
     FOOTER FIXE
     ══════════════════════════════════════════════════ --}}
<div class="footer-fixed">
    <strong>OmnyRestore</strong> &mdash; contact@omnyrestore.fr &mdash; omnyrestore.fr &nbsp;|&nbsp;
    Facture <strong>{{ $invoiceNum }}</strong> &mdash; Commande {{ $order->reference }}<br>
    G&eacute;n&eacute;r&eacute;e le {{ now()->format('d/m/Y') }} &mdash; Document officiel de r&egrave;glement &mdash; &copy; {{ date('Y') }} OmnyRestore
</div>

@php
    $logoPath = public_path('images/logo-text-light.png');
    $logoData = "";
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
    }
@endphp

<div class="header-bg clearfix">
    <div class="header-logo">
        @if($logoData)
            <img src="data:image/png;base64,{{ $logoData }}" style="height: 180px;">
        @else
            <div class="brand">OMNY<span class="brand-accent">RESTORE</span></div>
        @endif
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
            <div class="party-name">{{ $order->billing_name ?? $order->user->name }}</div>
            <div class="party-detail">
                {{ $order->billing_email ?? $order->user->email }}<br>
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
                <th style="width:38%">Description</th>
                <th style="width:6%">Qté</th>
                <th class="r" style="width:14%; white-space:nowrap;">P.U. HT</th>
                <th class="r" style="width:14%; white-space:nowrap;">P.U. TTC</th>
                <th class="r" style="width:14%; white-space:nowrap;">Total HT</th>
                <th class="r" style="width:14%; white-space:nowrap;">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineItems as $line)
            <tr>
                <td style="padding-right: 10px;">
                    <div class="desc-main">{{ $line['label'] }}</div>
                    <div class="desc-sub">
                        {{ $line['detail'] }}<br>
                        Analyse IA · retouche · amélioration résolution
                    </div>
                </td>
                <td>{{ $line['count'] }}</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($line['unit_ht_c'] / 100, 2, ',', ' ') }} €</td>
                <td class="r" style="white-space:nowrap;"><strong>{{ number_format($line['unit_ttc_c'] / 100, 2, ',', ' ') }} €</strong></td>
                <td class="r" style="white-space:nowrap;">{{ number_format($line['total_ht_c'] / 100, 2, ',', ' ') }} €</td>
                <td class="r" style="white-space:nowrap;"><strong>{{ number_format($line['total_ttc_c'] / 100, 2, ',', ' ') }} €</strong></td>
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
                <td class="r">—</td>
                <td class="r" style="white-space:nowrap;">-{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
                <td class="r" style="white-space:nowrap;"><strong>-{{ number_format($discountC / 100, 2, ',', ' ') }} €</strong></td>
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
        <strong>Transparence tarifaire :</strong> Le tarif est estim&eacute; automatiquement selon le niveau de restauration d&eacute;tect&eacute; pour chaque photo (standard, avanc&eacute; ou complet).
        Le co&ucirc;t d'analyse technique est int&eacute;gr&eacute; au tarif global et non factur&eacute; s&eacute;par&eacute;ment.
    </div>

</div>{{-- /.body --}}
</div>{{-- /.page --}}
</body>
</html>
