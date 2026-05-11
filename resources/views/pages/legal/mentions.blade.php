@extends('layouts.legal')

@section('title', 'Mentions légales')
@section('eyebrow', 'Légal')
@section('heading', 'Mentions légales')
@section('meta_description', 'Mentions légales d\'OmnyRestore — éditeur, hébergeur, propriété intellectuelle.')
@section('updated_at', '1er juin 2026')

@section('content')

{{-- Éditeur --}}
<h2>Éditeur du site</h2>

<div class="info-box">
    <strong>Identité de l'éditeur</strong>
    <p class="!mb-1"><strong>Raison sociale :</strong> OmnyVia</p>
    <p class="!mb-1"><strong>Service :</strong> OmnyRestore</p>
    <p class="!mb-1"><strong>Statut :</strong> Auto-entrepreneur / Entrepreneur individuel</p>
    <p class="!mb-1"><strong>Email de contact :</strong> <a href="mailto:contact@omnyrestore.fr">contact@omnyrestore.fr</a></p>
    <p class="!mb-0"><strong>Site web :</strong> <a href="https://omnyrestore.fr">https://omnyrestore.fr</a></p>
</div>

<p>
    En application de la loi n°2004-575 du 21 juin 2004 pour la Confiance dans l'Économie Numérique (LCEN),
    les présentes mentions légales sont portées à la connaissance des utilisateurs du site OmnyRestore.
</p>

{{-- Hébergeur --}}
<h2>Hébergeur</h2>

<div class="info-box">
    <strong>Hébergeur du site</strong>
    <p class="!mb-1"><strong>Société :</strong> OVH SAS</p>
    <p class="!mb-1"><strong>Siège social :</strong> 2 rue Kellermann — 59100 Roubaix — France</p>
    <p class="!mb-1"><strong>Téléphone :</strong> +33 9 72 10 10 07</p>
    <p class="!mb-0"><strong>Site web :</strong> <a href="https://www.ovhcloud.com" target="_blank" rel="noopener">https://www.ovhcloud.com</a></p>
</div>

<p>
    Les données sont hébergées en France, dans les datacenters d'OVH situés sur le territoire européen,
    dans le respect du Règlement Général sur la Protection des Données (RGPD — Règlement UE 2016/679).
</p>

{{-- Propriété intellectuelle --}}
<h2>Propriété intellectuelle</h2>

<p>
    L'ensemble du contenu présent sur le site OmnyRestore (textes, images, graphismes, logo, structure) est
    la propriété exclusive d'OmnyVia et est protégé par les lois françaises et internationales relatives
    à la propriété intellectuelle.
</p>
<p>
    Toute reproduction, représentation, modification, publication ou adaptation de tout ou partie
    des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite sans l'autorisation
    préalable et écrite d'OmnyVia.
</p>

<h3>Photos soumises par les utilisateurs</h3>
<p>
    Les photographies soumises par les clients pour restauration restent leur propriété exclusive.
    OmnyRestore n'acquiert aucun droit de propriété sur ces images. Elles sont utilisées uniquement
    pour l'exécution de la prestation commandée et supprimées automatiquement 6 mois après
    la livraison de la commande.
</p>

{{-- Responsabilité --}}
<h2>Limitation de responsabilité</h2>

<p>
    OmnyVia s'efforce d'assurer l'exactitude des informations présentes sur le site. Toutefois,
    les informations publiées sont susceptibles d'être modifiées sans préavis.
</p>
<p>
    OmnyVia ne saurait être tenu responsable de dommages directs ou indirects causés au matériel
    de l'utilisateur lors de l'accès au site, résultant de l'utilisation du matériel non conforme,
    de l'apparition d'un bug ou d'une incompatibilité.
</p>
<p>
    La qualité de la restauration dépend de l'état original de la photographie soumise. OmnyRestore
    ne garantit pas de résultats identiques pour toutes les photos, notamment celles présentant des
    dommages irréversibles. Un aperçu est toujours présenté avant paiement.
</p>

{{-- Droit applicable --}}
<h2>Droit applicable et juridiction</h2>

<p>
    Les présentes mentions légales sont régies par le droit français. En cas de litige, les tribunaux
    français seront seuls compétents.
</p>
<p>
    Pour toute réclamation ou demande d'information, contactez-nous à :
    <a href="mailto:contact@omnyrestore.fr">contact@omnyrestore.fr</a>
</p>

{{-- Cookies --}}
<h2>Cookies</h2>

<p>
    Le site OmnyRestore utilise des cookies strictement nécessaires au fonctionnement du service
    (authentification, sécurité de session). Aucun cookie publicitaire ou de traçage tiers n'est utilisé.
</p>
<p>
    Le paiement est traité par <strong>Stripe</strong> (Stripe, Inc., 510 Townsend Street, San Francisco, CA 94103, États-Unis),
    qui peut déposer ses propres cookies lors du processus de paiement, conformément à sa politique de confidentialité.
</p>

@endsection
