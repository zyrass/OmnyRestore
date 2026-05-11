@extends('layouts.legal')
@section('title', 'Paiement annulé')
@section('eyebrow', 'Commande')
@section('heading', 'Paiement annulé')
@section('updated_at', '')

@section('content')
<div class="info-box">
    <p>Votre paiement a été annulé. Aucune somme n'a été prélevée.</p>
    <p>Votre commande reste disponible dans votre espace client. Vous pouvez reprendre le paiement quand vous le souhaitez.</p>
</div>
<div class="text-center mt-8 flex gap-4 justify-center">
    <a href="{{ route('client.orders.index') }}" class="btn-outline">Mes commandes</a>
    <a href="{{ route('home') }}" class="btn-gold">Accueil</a>
</div>
@endsection
