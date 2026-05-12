<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ─── Reset ──────────────────────────────────────────────── */
* { margin: 0; padding: 0; box-sizing: border-box; }

/* ─── Page ───────────────────────────────────────────────── */
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 13px;
    color: #2C2418;
    background: #fff;
    line-height: 1.6;
}
.page { padding: 0; }

/* ─── Header sombre ──────────────────────────────────────── */
.header-bg {
    background-color: #1A1208;
    padding: 44px 60px 36px;
    border-bottom: 4px solid #C9A84C;
}
.header-logo {
    float: left;
    width: 50%;
}
.header-logo .brand {
    font-size: 26px;
    font-weight: bold;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: #C9A84C;
}
.header-logo .brand-sub {
    font-size: 10px;
    letter-spacing: 2.5px;
    color: #7A6E5E;
    margin-top: 5px;
    text-transform: uppercase;
}
.header-right {
    float: right;
    width: 44%;
    text-align: right;
}
.header-right .inv-label {
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #7A6E5E;
}
.header-right .inv-ref {
    font-size: 20px;
    font-weight: bold;
    color: #F5F0E8;
    margin-top: 3px;
    letter-spacing: 1px;
}
.header-right .inv-date {
    font-size: 11px;
    color: #9E9085;
    margin-top: 6px;
    line-height: 1.7;
}
.clearfix::after { content: ''; display: table; clear: both; }

/* ─── Corps ──────────────────────────────────────────────── */
.body { padding: 44px 60px; }

/* ─── Bloc parties ───────────────────────────────────────── */
.parties-wrap {
    width: 100%;
    border-bottom: 1px solid #EDE8E0;
    padding-bottom: 30px;
    margin-bottom: 30px;
}
.party-block {
    display: inline-block;
    width: 48%;
    vertical-align: top;
}
.party-block.right { text-align: right; }
.party-section-label {
    font-size: 9px;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #B0A090;
    border-bottom: 1px solid #EDE8E0;
    padding-bottom: 5px;
    margin-bottom: 10px;
}
.party-name {
    font-size: 15px;
    font-weight: bold;
    color: #1A1208;
    margin-bottom: 5px;
}
.party-detail {
    font-size: 11px;
    color: #7A6E5E;
    line-height: 1.7;
}

/* ─── Tampon PAYÉE ───────────────────────────────────────── */
.stamp-wrap { text-align: center; margin: 6px 0 30px; }
.stamp {
    display: inline-block;
    border: 2px solid #1a7a3f;
    color: #1a7a3f;
    padding: 7px 24px;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 3px;
    text-transform: uppercase;
}

/* ─── Tableau prestations ────────────────────────────────── */
.section-title {
    font-size: 9px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #B0A090;
    margin-bottom: 10px;
}
table.items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}
table.items thead tr { background-color: #F7F4EE; }
table.items thead th {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9E9085;
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #DDD8CE;
}
table.items thead th.r { text-align: right; }
table.items tbody td {
    padding: 13px 12px;
    font-size: 12px;
    border-bottom: 1px solid #F0EDE6;
    vertical-align: top;
    color: #2C2418;
}
table.items tbody td.r { text-align: right; }
table.items tbody td .desc-main { font-weight: bold; font-size: 13px; }
table.items tbody td .desc-sub { color: #9E9085; font-size: 11px; margin-top: 3px; }
.coupon-row td { color: #1a7a3f !important; background-color: #f2fbf5; }
.coupon-row td.coupon-label { font-weight: bold; }

/* ─── Totaux ──────────────────────────────────────────────── */
.totals-wrap { text-align: right; margin-top: 8px; }
table.totals {
    display: inline-table;
    width: 300px;
    border-collapse: collapse;
}
table.totals td {
    padding: 6px 0;
    font-size: 12px;
    color: #7A6E5E;
}
table.totals td.amount { text-align: right; padding-left: 24px; }
table.totals tr.sep td { border-top: 1px solid #EDE8E0; padding-top: 10px; }
table.totals tr.sep-bold td { border-top: 2px solid #1A1208; padding-top: 14px; }
table.totals tr.ttc-row td {
    font-size: 18px;
    font-weight: bold;
    color: #1A1208;
}
table.totals tr.free-row td {
    font-size: 17px;
    font-weight: bold;
    color: #1a7a3f;
}

/* ─── Note IA ────────────────────────────────────────────── */
.note-ia {
    background: #F7F4EE;
    border-left: 4px solid #C9A84C;
    padding: 14px 18px;
    font-size: 10.5px;
    color: #9E9085;
    line-height: 1.7;
    margin-top: 30px;
}

/* ─── Pied de page ───────────────────────────────────────── */
.footer {
    margin-top: 50px;
    padding-top: 16px;
    border-top: 1px solid #EDE8E0;
    font-size: 10px;
    color: #B0A090;
    text-align: center;
    line-height: 1.8;
}
.footer strong { color: #7A6E5E; }
</style>
</head>
<body>
<div class="page">

@php
    // ── Source de vérité ───────────────────────────────────────
    // total_price_cents = HT net après remise (fixé par l'admin)
    $baseHtC  = (int) ($order->base_price_cents ?? 0);
    $discountC= (int) ($order->discount_cents   ?? 0);

    // Si total_price_cents est renseigné, c'est la valeur de référence
    // Sinon on le calcule (base - remise)
    $htNetC   = $order->total_price_cents !== null
        ? (int) $order->total_price_cents
        : max(0, $baseHtC - $discountC);

    $tvaRate  = 20; // TVA FR 20%
    $tvaC     = (int) round($htNetC * $tvaRate / 100);
    $ttcC     = $htNetC + $tvaC;
    $isFree   = $ttcC === 0;

    $nPhotos  = (int) ($order->photo_count ?? 1);
    $unitHtC  = $nPhotos > 0 ? (int) round($baseHtC / $nPhotos) : $baseHtC;

    $level = $order->damage_level ?? 'light';
    $levelLabel = match($level) {
        'light'  => 'Standard (légère dégradation)',
        'medium' => 'Avancée (dégradation modérée)',
        'heavy'  => 'Complète (dégradation importante)',
        default  => ucfirst($level),
    };

    $invoiceNum = 'FAC-' . ($order->paid_at?->format('Y') ?? now()->format('Y'))
                . '-' . str_pad(substr($order->reference, -4), 4, '0', STR_PAD_LEFT);
@endphp

{{-- ══════════════════════════════════════════════════
     EN-TÊTE SOMBRE
     ══════════════════════════════════════════════════ --}}
<div class="header-bg clearfix">
    <div class="header-logo">
        <div class="brand">OmnyRestore</div>
        <div class="brand-sub">Restauration photographique IA</div>
    </div>
    <div class="header-right">
        <div class="inv-label">Facture</div>
        <div class="inv-ref">{{ $invoiceNum }}</div>
        <div class="inv-date">
            Émise le {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
            Réf. commande : {{ $order->reference }}
        </div>
    </div>
</div>

<div class="body">

    {{-- ══════════════════════════════════════════════════
         PARTIES : PRESTATAIRE / CLIENT
         ══════════════════════════════════════════════════ --}}
    <div class="parties-wrap clearfix">
        <div class="party-block">
            <div class="party-section-label">Prestataire</div>
            <div class="party-name">OmnyRestore</div>
            <div class="party-detail">
                Service de restauration photographique par IA<br>
                contact@omnyrestore.fr<br>
                omnyrestore.fr
            </div>
        </div>
        <div class="party-block right">
            <div class="party-section-label">Facturé à</div>
            <div class="party-name">{{ $order->user->name }}</div>
            <div class="party-detail">
                {{ $order->user->email }}<br>
                Client depuis {{ $order->user->created_at->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         TAMPON PAYÉE
         ══════════════════════════════════════════════════ --}}
    <div class="stamp-wrap">
        <span class="stamp">✓ Payée — {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
    </div>

    {{-- ══════════════════════════════════════════════════
         TABLEAU PRESTATIONS
         ══════════════════════════════════════════════════ --}}
    <div class="section-title">Détail de la prestation</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:52%">Description</th>
                <th style="width:10%">Qté</th>
                <th class="r" style="width:19%">Prix unit. HT</th>
                <th class="r" style="width:19%">Total HT</th>
            </tr>
        </thead>
        <tbody>
            {{-- Ligne principale --}}
            <tr>
                <td>
                    <div class="desc-main">Restauration photographique</div>
                    <div class="desc-sub">
                        Niveau : {{ $levelLabel }}<br>
                        Analyse IA + retouche + upscale haute résolution 8K
                    </div>
                </td>
                <td>{{ $nPhotos }}</td>
                <td class="r">{{ number_format($unitHtC / 100, 2, ',', ' ') }} €</td>
                <td class="r">{{ number_format($baseHtC / 100, 2, ',', ' ') }} €</td>
            </tr>

            {{-- Ligne coupon (si applicable) --}}
            @if ($discountC > 0)
            <tr class="coupon-row">
                <td class="coupon-label">
                    Code de réduction{{ $order->coupon_code ? ' · ' . strtoupper($order->coupon_code) : '' }}
                    <div class="desc-sub">Remise appliquée sur la prestation</div>
                </td>
                <td>—</td>
                <td class="r">—</td>
                <td class="r">−{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- ══════════════════════════════════════════════════
         TOTAUX
         ══════════════════════════════════════════════════ --}}
    <div class="totals-wrap">
        <table class="totals">
            @if ($discountC > 0)
            <tr>
                <td>Sous-total HT</td>
                <td class="amount">{{ number_format($baseHtC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td style="color:#1a7a3f">Remise</td>
                <td class="amount" style="color:#1a7a3f">−{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
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
                    @if ($isFree)
                        Offert ✓
                    @else
                        {{ number_format($ttcC / 100, 2, ',', ' ') }} €
                    @endif
                </td>
            </tr>
            @if ($order->payment_intent_id && !str_starts_with($order->payment_intent_id, 'coupon_free_'))
            <tr>
                <td style="color:#B0A090; font-size:8px">Réf. paiement</td>
                <td class="amount" style="color:#B0A090; font-size:8px">{{ $order->payment_intent_id }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════
         NOTE IA (transparence tarifaire)
         ══════════════════════════════════════════════════ --}}
    <div class="note-ia">
        <strong>Transparence tarifaire :</strong> Le tarif inclut l'analyse de chaque photo par intelligence artificielle
        (GPT-4o Vision) pour évaluer l'état de dégradation et adapter le traitement. Ce coût d'analyse (~0,01 € HT/photo)
        est intégré au prix global de la restauration et n'est pas facturé séparément.
    </div>

    {{-- ══════════════════════════════════════════════════
         PIED DE PAGE
         ══════════════════════════════════════════════════ --}}
    <div class="footer">
        <strong>OmnyRestore</strong> · contact@omnyrestore.fr · omnyrestore.fr<br>
        Cette facture fait foi de règlement complet de la commande <strong>{{ $order->reference }}</strong>.<br>
        Générée automatiquement le {{ now()->format('d/m/Y à H:i') }} · Conservez ce document pour votre comptabilité.<br>
        © {{ date('Y') }} OmnyRestore — Tous droits réservés.
    </div>

</div>{{-- /.body --}}
</div>{{-- /.page --}}
</body>
</html>
