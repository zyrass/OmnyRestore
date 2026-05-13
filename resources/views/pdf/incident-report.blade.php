<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; padding: 40px; }
    .header { border-bottom: 2px solid #C9A84C; padding-bottom: 20px; margin-bottom: 30px; }
    .logo-box { float: left; width: 50%; }
    .status-box { float: right; width: 45%; text-align: right; }
    .title { font-size: 24px; font-weight: bold; color: #b91c1c; text-transform: uppercase; margin-bottom: 5px; }
    .subtitle { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
    .section { margin-bottom: 25px; clear: both; }
    .section-title { font-size: 14px; font-weight: bold; color: #1a1a1a; border-left: 4px solid #C9A84C; padding-left: 10px; margin-bottom: 15px; text-transform: uppercase; }
    .grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .grid td { padding: 10px; border: 1px solid #eee; vertical-align: top; }
    .label { font-size: 9px; color: #888; text-transform: uppercase; margin-bottom: 3px; }
    .value { font-weight: bold; font-size: 11px; }
    .alert-box { background: #fef2f2; border: 1px solid #fee2e2; padding: 15px; color: #991b1b; font-size: 11px; margin-bottom: 20px; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #aaa; text-align: center; padding: 20px 0; border-top: 1px solid #eee; }
    .clearfix::after { content: ''; display: table; clear: both; }
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

<div class="header clearfix">
    <div class="logo-box">
        @if($logoData)
            <img src="data:image/png;base64,{{ $logoData }}" style="height: 50px; margin-bottom: 5px;">
        @else
            <div class="title">OmnyRestore</div>
        @endif
        <div class="subtitle">Protocole de Réponse aux Incidents (PRI)</div>
    </div>
    <div class="status-box">
        <div style="color: #b91c1c; font-weight: bold; font-size: 14px;">ÉTAT DE CRISE ACTIF</div>
        <div class="value" style="margin-top: 5px;">Déclenché le : {{ now()->format('d/m/Y H:i') }}</div>
        <div class="label">ID Incident : INC-{{ now()->format('Ymd-His') }}</div>
    </div>
</div>

<div class="alert-box">
    <strong>DOCUMENT CONFIDENTIEL</strong><br>
    Ce document contient le protocole de sécurité et les mesures d'urgence activées suite à la détection d'un incident majeur. 
    Il sert de preuve de diligence raisonnable au sens du RGPD.
</div>

<div class="section">
    <div class="section-title">1. Actions d'Urgence Immédiates</div>
    <table class="grid">
        <tr>
            <td width="33%">
                <div class="label">Systèmes</div>
                <div class="value">Isolation Infrastructure</div>
            </td>
            <td width="33%">
                <div class="label">Identité</div>
                <div class="value">Rotation des Secrets API</div>
            </td>
            <td width="33%">
                <div class="label">Données</div>
                <div class="value">Vérification Backup</div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">2. Annuaire de Crise & Légal</div>
    <table class="grid">
        <tr>
            <td width="50%">
                <div class="label">Responsable Protection Données (DPO)</div>
                <div class="value">Alain GUILLON</div>
                <div class="value" style="font-weight: normal;">dpo@omnyrestore.fr</div>
            </td>
            <td width="50%">
                <div class="label">Contact Autorité (CNIL)</div>
                <div class="value">cnil.fr/notifier</div>
                <div class="value" style="font-weight: normal;">Délai légal : 72 Heures</div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">3. Preuves de Sécurité & Conformité</div>
    <div style="background: #fafafa; padding: 15px; border: 1px solid #eee;">
        <table width="100%">
            <tr>
                <td>
                    <div class="label">Chiffrement des données</div>
                    <div class="value">AES-256 (Actif)</div>
                </td>
                <td>
                    <div class="label">Contrôle d'accès (RBAC)</div>
                    <div class="value">Accès Administrateur Restreint</div>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 15px;">
                    <div class="label">Architecture</div>
                    <div class="value">Environnement Cloud Isolé</div>
                </td>
                <td style="padding-top: 15px;">
                    <div class="label">Logs Système</div>
                    <div class="value">Journalisation centralisée persistante</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="section">
    <div class="section-title">4. Journal des décisions</div>
    <div style="height: 150px; border: 1px solid #eee; padding: 10px; color: #ccc;">
        Espace réservé à la consignation manuelle des décisions prises durant la cellule de crise...
    </div>
</div>

<div class="footer">
    OmnyRestore — Document généré par le système de gestion d'incident — Confidentiel — Page 1/1
</div>

</body>
</html>
