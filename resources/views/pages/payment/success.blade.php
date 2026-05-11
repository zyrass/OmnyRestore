@extends('layouts.legal')
@section('title', 'Paiement confirmé')
@section('eyebrow', 'Commande')
@section('heading', 'Paiement confirmé ✓')
@section('updated_at', '')

@section('content')
<div class="info-box text-center">
    <p>Merci pour votre confiance ! Votre paiement a bien été reçu.</p>
    <p>Vous allez recevoir un email avec votre lien de téléchargement sous peu.</p>
</div>
<div class="text-center mt-8">
    <a href="{{ route('client.orders.index') }}" class="btn-gold">Voir mes commandes</a>
</div>
@endsection
