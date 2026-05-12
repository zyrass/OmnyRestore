@extends('layouts.legal')

@section('title', 'Politique de confidentialité')
@section('eyebrow', 'RGPD · Mise à jour mai 2026')
@section('heading', 'Politique de confidentialité')
@section('meta_description', 'Politique de confidentialité d\'OmnyRestore — collecte, traitement, conservation et protection de vos données personnelles. Droits RGPD, DPO, CNIL.')
@section('updated_at', 'Mai 2026 — version 1.2')

@section('content')

<p>
    OmnyVia (service OmnyRestore) s'engage à protéger votre vie privée et à traiter vos données personnelles
    conformément au <strong>Règlement Général sur la Protection des Données</strong> (RGPD — Règlement UE 2016/679)
    et à la loi Informatique et Libertés du 6 janvier 1978 modifiée (loi n° 78-17).
</p>

{{-- 1. Responsable du traitement --}}
<h2>1. Responsable du traitement</h2>

<div class="info-box">
    <strong>Identité du responsable de traitement</strong>
    <p class="!mb-1"><strong>Nom :</strong> Alain GUILLON</p>
    <p class="!mb-1"><strong>Structure :</strong> OmnyVia — service OmnyRestore</p>
    <p class="!mb-1"><strong>Adresse :</strong> 7 impasse Paul Langevin, 69330 Meyzieu, Auvergne-Rhône-Alpes, France</p>
    <p class="!mb-1"><strong>Email :</strong> <a href="mailto:contact@omnyrestore.fr">contact@omnyrestore.fr</a></p>
    <p class="!mb-1"><strong>Contact données personnelles :</strong> <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a></p>
    <p class="!mb-0"><strong>Hébergement :</strong> OVH SAS, 2 rue Kellermann, 59100 Roubaix, France (UE)</p>
</div>

<p>
    OmnyRestore n'est pas soumis à l'obligation de désigner un Délégué à la Protection des Données (DPO)
    au sens de l'article 37 du RGPD. Toute demande relative à la protection de vos données peut être
    adressée à <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a>.
</p>

{{-- 2. Données collectées et finalités --}}
<h2>2. Données collectées, finalités et durées de conservation</h2>

<p>OmnyRestore collecte uniquement les données strictement nécessaires à l'exécution de ses services :</p>

<table>
    <thead>
        <tr>
            <th>Donnée</th>
            <th>Finalité</th>
            <th>Base légale (RGPD)</th>
            <th>Durée de conservation</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Nom, email, mot de passe</strong></td>
            <td>Création et gestion du compte client</td>
            <td>Exécution du contrat (Art. 6.1.b)</td>
            <td>Durée du compte actif + 12 mois après fermeture</td>
        </tr>
        <tr>
            <td><strong>Photos soumises</strong> (originales)</td>
            <td>Exécution de la prestation de restauration IA</td>
            <td>Exécution du contrat (Art. 6.1.b)</td>
            <td><strong>6 mois après livraison</strong>, puis suppression automatique</td>
        </tr>
        <tr>
            <td><strong>Photos restaurées</strong></td>
            <td>Livraison de la commande (archive ZIP téléchargeable)</td>
            <td>Exécution du contrat (Art. 6.1.b)</td>
            <td><strong>6 mois après livraison</strong>, puis suppression automatique</td>
        </tr>
        <tr>
            <td><strong>Données de commande</strong> (référence, statut, montant)</td>
            <td>Gestion des commandes, suivi client, litiges</td>
            <td>Exécution du contrat (Art. 6.1.b)</td>
            <td>5 ans à compter de la commande</td>
        </tr>
        <tr>
            <td><strong>Factures</strong> (montant, TVA, identité anonymisée post-suppression)</td>
            <td>Obligation comptable légale</td>
            <td>Obligation légale (Art. 6.1.c) — Art. L.123-22 C. com.</td>
            <td><strong>10 ans</strong> à compter de l'exercice comptable</td>
        </tr>
        <tr>
            <td><strong>Données de paiement</strong> (carte, IBAN)</td>
            <td>Traitement sécurisé du paiement</td>
            <td>Exécution du contrat (Art. 6.1.b)</td>
            <td>Gérées exclusivement par <strong>Stripe</strong> — non stockées chez OmnyRestore</td>
        </tr>
        <tr>
            <td><strong>Logs techniques</strong> (IP, user agent, actions d'audit)</td>
            <td>Sécurité, conformité NIS2, détection de fraude</td>
            <td>Intérêt légitime / Obligation légale (Art. 6.1.c / 6.1.f)</td>
            <td>12 mois glissants</td>
        </tr>
        <tr>
            <td><strong>Consentement marketing</strong></td>
            <td>Envoi d'emails promotionnels (si opt-in explicite)</td>
            <td>Consentement (Art. 6.1.a)</td>
            <td>Jusqu'au retrait du consentement</td>
        </tr>
    </tbody>
</table>

<div class="info-box">
    <strong>📸 Note sur les photos</strong>
    <p class="!mb-0">
        Vos photos (originales et restaurées) sont hébergées dans un espace de stockage privé à accès restreint.
        Elles ne sont jamais utilisées à des fins de démonstration, d'entraînement d'IA, de publicité
        ou partagées avec des tiers sans votre consentement explicite.
        La suppression intervient automatiquement 6 mois après la date de livraison.
    </p>
</div>

{{-- 3. Sous-traitants --}}
<h2>3. Sous-traitants et transferts hors UE</h2>

<p>
    OmnyRestore fait appel aux sous-traitants suivants. Chacun a fourni des garanties suffisantes
    au sens de l'article 28 du RGPD :
</p>

<table>
    <thead>
        <tr>
            <th>Sous-traitant</th>
            <th>Rôle</th>
            <th>Localisation</th>
            <th>Garanties</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>OVH SAS</strong></td>
            <td>Hébergement serveurs</td>
            <td>Roubaix, France 🇫🇷</td>
            <td>Données en UE — <a href="https://www.ovhcloud.com/fr/personal-data-protection/" target="_blank" rel="noopener">Politique OVH</a></td>
        </tr>
        <tr>
            <td><strong>Stripe, Inc.</strong></td>
            <td>Traitement des paiements</td>
            <td>San Francisco, USA 🇺🇸</td>
            <td>CCT UE — <a href="https://stripe.com/fr/privacy" target="_blank" rel="noopener">Politique Stripe</a></td>
        </tr>
        <tr>
            <td><strong>Resend</strong></td>
            <td>Emails transactionnels</td>
            <td>USA 🇺🇸</td>
            <td>CCT UE (clauses contractuelles types)</td>
        </tr>
        <tr>
            <td><strong>Amazon Web Services S3</strong></td>
            <td>Stockage photos (optionnel)</td>
            <td>eu-west-3 — Paris, France 🇫🇷</td>
            <td>Données en UE — <a href="https://aws.amazon.com/fr/compliance/gdpr-center/" target="_blank" rel="noopener">Centre RGPD AWS</a></td>
        </tr>
    </tbody>
</table>

<p>
    <strong>Aucune donnée personnelle n'est vendue à des tiers.</strong>
    Aucun transfert hors Union Européenne ne s'effectue sans garanties appropriées
    (clauses contractuelles types approuvées par la Commission européenne ou décision d'adéquation).
</p>

{{-- 4. Cookies --}}
<h2>4. Cookies et traceurs</h2>

<p>OmnyRestore utilise uniquement des cookies strictement nécessaires au fonctionnement du service :</p>

<table>
    <thead>
        <tr>
            <th>Cookie</th>
            <th>Finalité</th>
            <th>Durée</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>laravel_session</strong></td>
            <td>Session authentifiée (connexion, panier, messages flash)</td>
            <td>Session (fermeture navigateur) ou 2h max</td>
        </tr>
        <tr>
            <td><strong>XSRF-TOKEN</strong></td>
            <td>Protection contre les attaques CSRF</td>
            <td>Session</td>
        </tr>
        <tr>
            <td><strong>remember_web_*</strong></td>
            <td>Option « Rester connecté » (si cochée)</td>
            <td>400 jours maximum</td>
        </tr>
    </tbody>
</table>

<p>
    OmnyRestore <strong>n'utilise pas de cookies publicitaires, analytiques tiers ou de suivi comportemental.</strong>
    Aucun consentement cookie supplémentaire n'est requis pour la navigation sur le site.
</p>

{{-- 5. Droits --}}
<h2>5. Vos droits (RGPD — Articles 15 à 22)</h2>

<p>Conformément au RGPD, vous disposez des droits suivants :</p>

<ul>
    <li><strong>Droit d'accès (Art. 15)</strong> — obtenir une copie de l'ensemble de vos données personnelles traitées</li>
    <li><strong>Droit de rectification (Art. 16)</strong> — corriger des données inexactes ou incomplètes</li>
    <li><strong>Droit à l'effacement / « droit à l'oubli » (Art. 17)</strong> — demander la suppression de votre compte et de toutes vos données personnelles</li>
    <li><strong>Droit à la portabilité (Art. 20)</strong> — recevoir vos données dans un format structuré et lisible par machine (JSON / CSV)</li>
    <li><strong>Droit d'opposition (Art. 21)</strong> — vous opposer à un traitement fondé sur l'intérêt légitime</li>
    <li><strong>Droit au retrait du consentement (Art. 7)</strong> — retirer à tout moment votre consentement aux communications marketing</li>
    <li><strong>Droit à la limitation (Art. 18)</strong> — demander la suspension temporaire d'un traitement</li>
</ul>

<p>
    Pour exercer vos droits : <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a>
    ou via votre espace client → <em>Mon compte → Mes données</em>.<br>
    Nous répondrons dans un délai maximum d'<strong>1 mois</strong> (délai prorogeable de 2 mois pour les demandes complexes — RGPD Art. 12.3).
    Une copie de votre pièce d'identité pourra être demandée pour vérifier votre identité.
</p>

<h3>Droit à l'effacement — modalités spécifiques</h3>
<p>
    Lors d'une demande d'effacement, votre compte est immédiatement désactivé et vos données personnelles
    identifiantes (nom, email, adresse) sont <strong>anonymisées de façon irréversible</strong>.
    Vos photos originales et restaurées sont <strong>supprimées immédiatement</strong> de nos serveurs.
</p>
<p>
    Les <strong>factures</strong> sont conservées sous forme anonymisée (données financières sans identité)
    pendant <strong>10 ans</strong> conformément à l'article L.123-22 du Code de commerce.
    Les données de commande anonymisées (montants, dates) sont conservées 5 ans pour nos obligations comptables.
    Ces données anonymisées ne permettent plus de vous identifier et ne constituent plus des données personnelles au sens du RGPD.
</p>

{{-- 6. Violation de données --}}
<h2>6. Violations de données personnelles</h2>

<p>
    En cas de violation de données personnelles susceptible d'engendrer un risque pour vos droits et libertés,
    OmnyRestore s'engage à notifier la <strong>CNIL</strong> dans les <strong>72 heures</strong> suivant
    la prise de connaissance de l'incident (RGPD Art. 33).
    Si la violation est susceptible d'engendrer un risque élevé, vous en serez informé directement
    par email dans les meilleurs délais (RGPD Art. 34).
</p>

{{-- 7. Sécurité --}}
<h2>7. Sécurité des données</h2>

<p>OmnyRestore met en œuvre les mesures techniques et organisationnelles suivantes (RGPD Art. 32) :</p>
<ul>
    <li>Chiffrement des mots de passe (bcrypt, coût 12 — irréversible)</li>
    <li>Connexions chiffrées <strong>HTTPS/TLS 1.3</strong> sur l'ensemble du site</li>
    <li>Stockage des photos dans un bucket S3 <strong>privé</strong> (accès uniquement par URLs signées temporaires)</li>
    <li>Journalisation des accès aux données sensibles (<strong>logs d'audit immuables</strong>)</li>
    <li>Accès administrateur restreint par authentification forte (2FA recommandée)</li>
    <li>Suppression automatique des photos <strong>6 mois</strong> après livraison</li>
    <li>Séparation des environnements de développement et de production</li>
    <li>Revue régulière des accès et des habilitations</li>
</ul>

{{-- 8. Réclamation --}}
<h2>8. Réclamation auprès de la CNIL</h2>

<p>
    Si vous estimez que le traitement de vos données personnelles constitue une violation du RGPD,
    vous avez le droit d'introduire une réclamation auprès de l'autorité de contrôle compétente :
</p>
<ul>
    <li><strong>CNIL</strong> — Commission Nationale de l'Informatique et des Libertés</li>
    <li>En ligne : <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">https://www.cnil.fr/fr/plaintes</a></li>
    <li>Par courrier : CNIL, 3 Place de Fontenoy — TSA 80715 — 75334 Paris Cedex 07</li>
</ul>

<p>
    Nous vous encourageons à nous contacter en premier lieu à <a href="mailto:privacy@omnyrestore.fr">privacy@omnyrestore.fr</a>
    afin de résoudre toute difficulté à l'amiable avant toute saisine de la CNIL.
</p>

{{-- 9. Modifications --}}
<h2>9. Modifications de la présente politique</h2>

<p>
    Toute modification substantielle de cette politique de confidentialité fera l'objet d'une notification
    par email aux utilisateurs disposant d'un compte actif, au moins 30 jours avant son entrée en vigueur.
    La date de mise à jour est indiquée en haut de cette page.
    La version en vigueur est celle consultable sur ce site.
</p>

@endsection
