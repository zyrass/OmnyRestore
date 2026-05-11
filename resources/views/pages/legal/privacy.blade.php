@extends('layouts.legal')

@section('title', 'Politique de confidentialité')
@section('eyebrow', 'RGPD')
@section('heading', 'Politique de confidentialité')
@section('meta_description', 'Politique de confidentialité d\'OmnyRestore — collecte, traitement et protection de vos données personnelles.')
@section('updated_at', '1er juin 2026')

@section('content')

<p>
    OmnyVia (OmnyRestore) s'engage à protéger votre vie privée et à traiter vos données personnelles
    conformément au Règlement Général sur la Protection des Données (RGPD — Règlement UE 2016/679)
    et à la loi Informatique et Libertés du 6 janvier 1978 modifiée.
</p>

{{-- Responsable --}}
<h2>Responsable du traitement</h2>

<div class="info-box">
    <strong>Coordonnées du responsable</strong>
    <p class="!mb-1"><strong>Entité :</strong> OmnyVia — service OmnyRestore</p>
    <p class="!mb-1"><strong>Email DPO :</strong> <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a></p>
    <p class="!mb-0"><strong>Hébergement :</strong> OVH SAS, Roubaix, France (UE)</p>
</div>

{{-- Données collectées --}}
<h2>Données collectées et finalités</h2>

<table>
    <thead>
        <tr>
            <th>Donnée</th>
            <th>Finalité</th>
            <th>Base légale</th>
            <th>Durée</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Nom, email, mot de passe</strong></td>
            <td>Création et gestion du compte client</td>
            <td>Exécution du contrat</td>
            <td>Durée du compte + 12 mois</td>
        </tr>
        <tr>
            <td><strong>Photos soumises</strong> (originales)</td>
            <td>Exécution de la prestation de restauration</td>
            <td>Exécution du contrat</td>
            <td>6 mois après livraison, puis suppression automatique</td>
        </tr>
        <tr>
            <td><strong>Photos restaurées</strong></td>
            <td>Livraison de la commande (téléchargement ZIP)</td>
            <td>Exécution du contrat</td>
            <td>6 mois après livraison, puis suppression automatique</td>
        </tr>
        <tr>
            <td><strong>Données de commande</strong> (référence, montant, statut)</td>
            <td>Gestion des commandes, comptabilité</td>
            <td>Obligation légale (conservation 5 ans)</td>
            <td>5 ans à compter de la commande</td>
        </tr>
        <tr>
            <td><strong>Données de paiement</strong></td>
            <td>Traitement du paiement</td>
            <td>Exécution du contrat</td>
            <td>Gérées par Stripe — non stockées chez OmnyRestore</td>
        </tr>
        <tr>
            <td><strong>Logs techniques</strong> (IP, user agent, actions)</td>
            <td>Sécurité, conformité NIS2, détection de fraude</td>
            <td>Intérêt légitime / Obligation légale</td>
            <td>12 mois glissants</td>
        </tr>
        <tr>
            <td><strong>Consentement marketing</strong></td>
            <td>Envoi d'emails promotionnels (si opt-in)</td>
            <td>Consentement explicite</td>
            <td>Jusqu'au retrait du consentement</td>
        </tr>
    </tbody>
</table>

{{-- Sous-traitants --}}
<h2>Sous-traitants et transferts</h2>

<p>
    OmnyRestore fait appel aux sous-traitants suivants pour l'exécution de ses services.
    Chacun présente des garanties suffisantes en matière de protection des données :
</p>

<ul>
    <li><strong>OVH SAS</strong> (hébergement) — Roubaix, France — <a href="https://www.ovhcloud.com/fr/personal-data-protection/" target="_blank" rel="noopener">Politique OVH</a></li>
    <li><strong>Stripe, Inc.</strong> (paiement) — San Francisco, USA — transfert encadré par les clauses contractuelles types UE — <a href="https://stripe.com/fr/privacy" target="_blank" rel="noopener">Politique Stripe</a></li>
    <li><strong>Resend</strong> (emails transactionnels) — USA — transfert encadré par les clauses contractuelles types UE</li>
    <li><strong>Amazon Web Services S3</strong> (stockage photos, optionnel) — région eu-west-3 (Paris, France)</li>
</ul>

<p>
    Aucune donnée personnelle n'est vendue à des tiers. Aucun transfert de données vers des pays
    hors Union Européenne ne s'effectue sans garanties appropriées (clauses contractuelles types ou
    décision d'adéquation de la Commission européenne).
</p>

{{-- Droits --}}
<h2>Vos droits (RGPD Articles 15 à 22)</h2>

<p>Conformément au RGPD, vous disposez des droits suivants :</p>

<ul>
    <li><strong>Droit d'accès</strong> — obtenir une copie de vos données personnelles</li>
    <li><strong>Droit de rectification</strong> — corriger des données inexactes</li>
    <li><strong>Droit à l'effacement</strong> ("droit à l'oubli") — demander la suppression de votre compte et de vos données</li>
    <li><strong>Droit à la portabilité</strong> — recevoir vos données dans un format lisible (JSON)</li>
    <li><strong>Droit d'opposition</strong> — vous opposer au traitement fondé sur l'intérêt légitime</li>
    <li><strong>Droit de retrait du consentement</strong> — retirer à tout moment votre consentement aux emails marketing</li>
    <li><strong>Droit à la limitation</strong> — demander la suspension temporaire du traitement</li>
</ul>

<p>
    Pour exercer vos droits, contactez-nous à : <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a>
    ou via votre espace client (section "Mon compte" → "Mes données").
    Nous répondrons dans un délai maximum d'<strong>1 mois</strong> (RGPD Art. 12).
</p>

<h3>Droit à l'effacement — modalités spécifiques</h3>
<p>
    Lors d'une demande d'effacement, votre compte est désactivé et vos données personnelles
    (nom, email) sont anonymisées. Les données de commandes sont conservées 5 ans pour
    obligation comptable légale, mais dissociées de votre identité.
    Les photos sont supprimées immédiatement de nos serveurs.
</p>

{{-- Réclamation --}}
<h2>Réclamation auprès de la CNIL</h2>

<p>
    Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation
    auprès de la <strong>CNIL</strong> (Commission Nationale de l'Informatique et des Libertés) :
</p>
<ul>
    <li>En ligne : <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">https://www.cnil.fr/fr/plaintes</a></li>
    <li>Par courrier : CNIL, 3 Place de Fontenoy — TSA 80715 — 75334 Paris Cedex 07</li>
</ul>

{{-- Sécurité --}}
<h2>Sécurité des données</h2>

<p>OmnyRestore met en œuvre les mesures techniques et organisationnelles suivantes :</p>
<ul>
    <li>Chiffrement des mots de passe (bcrypt, coût 12)</li>
    <li>Connexions chiffrées HTTPS/TLS sur l'ensemble du site</li>
    <li>Stockage des photos dans un bucket S3 privé (accès par URLs signées temporaires)</li>
    <li>Journalisation des accès aux données sensibles (logs d'audit immuables)</li>
    <li>Accès administrateur restreint par authentification forte</li>
    <li>Suppression automatique des photos 6 mois après livraison</li>
</ul>

@endsection
