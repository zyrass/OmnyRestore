<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1A1208; line-height: 1.5; }
        .header { border-bottom: 2px solid #C9A84C; padding-bottom: 20px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; color: #1A1208; }
        .subtitle { font-size: 14px; color: #7A6E5E; margin-top: 5px; }
        
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .card { background: #FDFAF4; border: 1px solid #E8E2D4; padding: 15px; text-align: center; }
        .card-title { font-size: 10px; text-transform: uppercase; color: #7A6E5E; margin-bottom: 5px; }
        .card-value { font-size: 18px; font-weight: bold; color: #C9A84C; }
        
        table.details { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.details th { background: #F5F1E8; padding: 10px; font-size: 10px; text-transform: uppercase; text-align: left; border-bottom: 1px solid #DDD8CE; }
        table.details td { padding: 10px; border-bottom: 1px solid #F0EDE6; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #A09080; border-top: 1px solid #DDD8CE; padding-top: 10px; }
        .summary-box { background: #f0faf5; border: 1px solid #34d399; padding: 20px; margin-top: 30px; }
        .summary-title { color: #1a7a3f; font-weight: bold; font-size: 16px; }
    </style>
</head>
@php
    $logoPath = public_path('images/logo-text-light.png');
    $logoData = "";
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
    }
@endphp

<body>
    <div class="header">
        @if($logoData)
            <img src="data:image/png;base64,{{ $logoData }}" style="height: 60px; margin-bottom: 10px;">
        @endif
        <div class="title">Rapport Financier OmnyRestore</div>
        <div class="subtitle">Période : {{ ucfirst($label) }}</div>
    </div>

    <table class="grid">
        <tr>
            <td class="card">
                <div class="card-title">CA Total HT</div>
                <div class="card-value">{{ number_format($stats['ht_cents'] / 100, 2, ',', ' ') }} €</div>
            </td>
            <td class="card">
                <div class="card-title">CA Total TTC</div>
                <div class="card-value">{{ number_format($stats['ttc_cents'] / 100, 2, ',', ' ') }} €</div>
            </td>
            <td class="card">
                <div class="card-title">Commandes</div>
                <div class="card-value">{{ $stats['count'] }}</div>
            </td>
            <td class="card">
                <div class="card-title">Photos</div>
                <div class="card-value">{{ $stats['photos'] }}</div>
            </td>
        </tr>
    </table>

    <h3>Détails des Charges estimées</h3>
    <table class="details">
        <thead>
            <tr>
                <th>Poste de dépense</th>
                <th class="text-right">Base de calcul</th>
                <th class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Coûts IA (Analyse & Restauration)</td>
                <td class="text-right">{{ $stats['photos'] }} photos x 0,15 €</td>
                <td class="text-right" style="color: #b91c1c">{{ number_format($stats['ai_cost'] / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td>Cotisations URSSAF</td>
                <td class="text-right">21,2% du CA TTC ({{ number_format($stats['ttc_cents'] / 100, 2, ',', ' ') }} €)</td>
                <td class="text-right" style="color: #b91c1c">{{ number_format($stats['urssaf'] / 100, 2, ',', ' ') }} €</td>
            </tr>
        </tbody>
    </table>

    <div class="summary-box" style="background: #fff5f5; border-color: #feb2b2; margin-top: 20px;">
        <div class="summary-title" style="color: #c53030; font-size: 14px;">Déclaration URSSAF (Estimation)</div>
        <table style="width: 100%; margin-top: 10px; font-size: 13px;">
            <tr>
                <td style="color: #742a2a;"><strong>Montant brut à déclarer :</strong></td>
                <td class="text-right" style="color: #c53030;"><strong>{{ number_format($stats['ttc_cents'] / 100, 2, ',', ' ') }} €</strong></td>
            </tr>
            <tr>
                <td style="color: #742a2a; font-size: 11px;">Cotisation à payer (21,2%) :</td>
                <td class="text-right" style="color: #742a2a; font-size: 11px;">{{ number_format($stats['urssaf'] / 100, 2, ',', ' ') }} €</td>
            </tr>
        </table>
        <p style="margin-top: 8px; font-size: 9px; color: #9b2c2c; font-style: italic;">Note : Vous devez déclarer le chiffre d'affaires TTC encaissé lors de votre déclaration trimestrielle ou mensuelle.</p>
    </div>

    <div class="summary-box">
        <div class="summary-title">Résultat Net Estimé : {{ number_format($stats['net'] / 100, 2, ',', ' ') }} €</div>
        <p style="margin-top: 5px; font-size: 11px; color: #1a7a3f;">Ceci est le montant net après déduction des frais de fonctionnement IA et des taxes sociales.</p>
    </div>

    <h3 style="margin-top: 40px;">Répartition Quotidienne</h3>
    <table class="details">
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Commandes</th>
                <th class="text-right">CA HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dailyBreakdown as $date => $data)
            <tr>
                <td>{{ $date }}</td>
                <td class="text-right">{{ $data['count'] }}</td>
                <td class="text-right font-bold">{{ number_format($data['ht'] / 100, 2, ',', ' ') }} €</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Document confidentiel généré le {{ now()->format('d/m/Y H:i') }} &mdash; OmnyRestore Admin Panel
    </div>
</body>
</html>
