@extends('layouts.legal')

@section('title', 'Conditions Générales de Vente')
@section('eyebrow', 'Contractuel')
@section('heading', 'Conditions Générales de Vente')
@section('meta_description', 'CGV OmnyRestore — tarifs, commandes, paiement Stripe, livraison, droit de rétractation.')
@section('updated_at', '1er juin 2026')

@section('content')

<p>
    Les présentes Conditions Générales de Vente (CGV) régissent les relations contractuelles entre
    <strong>OmnyVia</strong> (éditeur du service OmnyRestore, ci-après « le Prestataire ») et toute
    personne physique effectuant une commande via le site <strong>omnyrestore.fr</strong>
    (ci-après « le Client »).
</p>
<p>
    Toute commande implique l'acceptation sans réserve des présentes CGV.
    Le Client déclare avoir la capacité juridique de contracter (être majeur et non sous tutelle).
</p>

{{-- Services --}}
<h2>Article 1 — Description des services</h2>

<p>
    OmnyRestore propose un service de <strong>restauration de photographies anciennes par intelligence artificielle</strong>.
    Le traitement inclut notamment :
</p>
<ul>
    <li>Correction des artefacts de compression et dommages physiques (déchirures, pliures, taches)</li>
    <li>Upscaling intelligent en résolution jusqu'à 8K</li>
    <li>Reconstruction des textures et micro-détails</li>
    <li>Optimisation du contraste et de la netteté (rendu Studio Professionnel)</li>
    <li>Conservation de la géométrie, des traits et de l'éclairage d'origine</li>
</ul>

<h3>Limitation du service</h3>
<p>
    La qualité du résultat dépend de l'état de la photographie originale. Certaines photos présentant
    des dommages irréversibles (destruction partielle de l'image source) ne peuvent pas être
    intégralement restaurées. Un <strong>aperçu filigranné est systématiquement présenté avant paiement</strong>,
    permettant au Client d'évaluer le résultat avant tout engagement financier.
</p>

{{-- Tarifs --}}
<h2>Article 2 — Tarifs</h2>

<p>Tous les prix sont indiqués en euros (€), <strong>hors taxes</strong>. La TVA applicable est de 20 %.</p>

<table>
    <thead>
        <tr>
            <th>Prestation</th>
            <th>Prix HT</th>
            <th>Prix TTC</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Restauration Standard</strong></td>
            <td>à partir de 1,00 €</td>
            <td>à partir de 1,20 €</td>
            <td>1 photo, dommages légers (jaunissement, poussière)</td>
        </tr>
        <tr>
            <td><strong>Restauration Avancée</strong></td>
            <td>à partir de 10,00 €</td>
            <td>à partir de 12,00 €</td>
            <td>Photo très endommagée (déchirures majeures, dommages eau, pliures)</td>
        </tr>
        <tr>
            <td><strong>Lot de photos</strong></td>
            <td>Sur devis</td>
            <td>Sur devis</td>
            <td>À partir de 5 photos — tarif dégressif</td>
        </tr>
    </tbody>
</table>

<p>
    Le tarif définitif est évalué par le Prestataire après analyse des photos soumises.
    Le Client est informé du prix exact via la plateforme avant tout paiement.
    <strong>Aucune somme n'est prélevée sans accord explicite du Client.</strong>
</p>
<p>
    Les prix peuvent être modifiés à tout moment. Les commandes sont facturées au tarif
    en vigueur au moment de la validation du paiement.
</p>

{{-- Commande --}}
<h2>Article 3 — Processus de commande</h2>

<ul>
    <li><strong>Étape 1 — Dépôt :</strong> Le Client crée un compte et dépose ses photos sur la plateforme.</li>
    <li><strong>Étape 2 — Traitement :</strong> Le Prestataire analyse et restaure les photos par IA.</li>
    <li><strong>Étape 3 — Aperçu :</strong> Le Client reçoit un aperçu filigranné basse résolution du résultat.</li>
    <li><strong>Étape 4 — Paiement :</strong> Le Client valide le paiement via Stripe pour débloquer le téléchargement haute résolution.</li>
    <li><strong>Étape 5 — Livraison :</strong> Un lien de téléchargement sécurisé (ZIP) est envoyé par email et disponible dans l'espace client.</li>
</ul>

<p>
    La commande est définitivement confirmée à la réception du paiement validé par Stripe.
    Un email de confirmation est envoyé automatiquement.
</p>

{{-- Paiement --}}
<h2>Article 4 — Paiement</h2>

<p>
    Le paiement est sécurisé par <strong>Stripe</strong> (Stripe, Inc.), certifié PCI DSS niveau 1.
    Les modes de paiement acceptés sont ceux proposés par Stripe lors du paiement
    (carte bancaire Visa, Mastercard, etc.).
</p>
<p>
    OmnyRestore <strong>ne stocke jamais</strong> les données de carte bancaire du Client.
    Ces données transitent exclusivement par les serveurs de Stripe.
</p>
<p>
    En cas d'échec du paiement, la commande reste dans l'état « En attente de paiement » et
    le Client peut réessayer. L'accès aux photos haute résolution sans filigrane est conditionné
    à la réception effective du paiement.
</p>

{{-- Livraison --}}
<h2>Article 5 — Livraison</h2>

<p>
    La livraison est exclusivement <strong>numérique</strong>. Après confirmation du paiement,
    le Client reçoit un lien de téléchargement sécurisé vers une archive ZIP contenant les
    photos restaurées en haute résolution (jusqu'à 8K), sans filigrane.
</p>

<h3>Délais indicatifs</h3>
<ul>
    <li>Restauration Standard : <strong>24 à 48 heures</strong> ouvrées</li>
    <li>Restauration Avancée : <strong>48 à 72 heures</strong> ouvrées</li>
    <li>Lot de photos : selon devis</li>
</ul>

<p>
    Ces délais sont indicatifs et peuvent varier en période de forte activité.
    Le Client est informé par email à chaque changement de statut de sa commande.
</p>

<h3>Disponibilité du lien de téléchargement</h3>
<p>
    Le lien de téléchargement est actif pendant <strong>6 mois</strong> à compter de la livraison.
    Passé ce délai, les photos sont supprimées de nos serveurs conformément à notre politique RGPD.
    Il appartient au Client de télécharger et sauvegarder ses photos dans ce délai.
</p>

{{-- Rétractation --}}
<h2>Article 6 — Droit de rétractation</h2>

<p>
    Conformément à l'article L221-28 du Code de la consommation, <strong>le droit de rétractation
    ne s'applique pas</strong> aux contenus numériques dont l'exécution a commencé avec l'accord
    du consommateur, avant l'expiration du délai de rétractation.
</p>
<p>
    Cependant, le modèle commercial d'OmnyRestore est conçu pour protéger le Client :
    <strong>un aperçu du résultat est fourni avant tout paiement.</strong>
    Le Client décide librement de payer ou non après avoir visualisé le résultat.
    Si l'aperçu ne lui convient pas, il n'est pas débité et peut annuler sa commande.
</p>

{{-- Réclamations --}}
<h2>Article 7 — Réclamations et litiges</h2>

<p>
    Toute réclamation doit être adressée à :
    <a href="mailto:contact@omnyrestore.fr">contact@omnyrestore.fr</a>
    dans un délai de <strong>30 jours</strong> suivant la livraison.
</p>
<p>
    En cas de litige non résolu amiablement, le Client peut recourir gratuitement à un médiateur
    de la consommation conformément aux articles L611-1 et suivants du Code de la consommation.
</p>
<p>
    Les présentes CGV sont soumises au <strong>droit français</strong>. En cas de litige persistant,
    les tribunaux compétents seront ceux du ressort du siège social du Prestataire.
</p>

{{-- Propriété intellectuelle --}}
<h2>Article 8 — Propriété intellectuelle des photos restaurées</h2>

<p>
    Les photos restaurées livrées au Client sont la propriété du Client (ou de l'ayant droit
    de la photographie originale). OmnyVia ne revendique aucun droit sur ces œuvres restaurées.
</p>
<p>
    Le Client autorise OmnyRestore à utiliser les photos soumises uniquement dans le cadre
    strict de l'exécution de la prestation. Aucune utilisation commerciale, publication ou
    partage de ces photos par OmnyRestore n'est effectué sans autorisation expresse du Client.
</p>

@endsection
