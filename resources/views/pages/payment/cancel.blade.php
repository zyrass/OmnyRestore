<x-layouts.app title="Paiement annulé — OmnyRestore">

<div class="min-h-[70vh] flex items-center justify-center px-6">
    <div class="text-center max-w-md">

        {{-- Icône annulation --}}
        <div class="w-20 h-20 rounded-full bg-red-950/30 border border-red-500/30 flex items-center justify-center mx-auto mb-8">
            <svg class="w-9 h-9 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-3">Paiement annulé</h1>
        <p class="text-[#7A6E5E] text-sm leading-relaxed mb-2">
            Votre paiement a été annulé. Aucune somme n'a été prélevée.
        </p>
        <p class="text-[#7A6E5E] text-sm leading-relaxed mb-8">
            Votre commande reste disponible dans votre espace client.<br>
            Vous pouvez reprendre le paiement quand vous le souhaitez.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('client.orders.index') }}" wire:navigate class="btn-gold">
                Retour à mes commandes
            </a>
        </div>

    </div>
</div>

</x-layouts.app>
