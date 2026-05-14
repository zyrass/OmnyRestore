<x-mail::message>
# Alerte Modération : Contenu Sensible

Bonjour Admin,

L'Intelligence Artificielle de modération a détecté du **contenu sensible ou illégal** dans la commande **{{ $order->reference }}**.

**Catégories détectées :**
@foreach ($categories as $category)
- {{ $category }}
@endforeach

@if (in_array('sexual/minors', $categories))
<x-mail::panel>
**⚠️ URGENCE : Pédocriminalité détectée (CSAM)**
La plateforme a l'obligation légale de retirer ce contenu et de le signaler via PHAROS (internet-signalement.gouv.fr).
Un bouton d'export du rapport PHAROS est disponible dans le panel admin.
</x-mail::panel>
@else
<x-mail::panel>
Il s'agit de contenu NSFW (Adulte) non autorisé par nos CGV.
</x-mail::panel>
@endif

La commande a été suspendue (statut `FLAGGED`) et les photos ont été floutées.
Une action de votre part est requise pour soit ignorer le faux-positif, soit bannir l'utilisateur et détruire les fichiers.

<x-mail::button :url="route('admin.orders.show', $order)">
Gérer l'incident
</x-mail::button>

Merci,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
