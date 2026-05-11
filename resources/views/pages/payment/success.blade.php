<x-layouts.app title="Paiement confirmé — OmnyRestore">

<div class="min-h-[70vh] flex items-center justify-center px-6">
    <div class="text-center max-w-md" x-data="{ dots: '' }" x-init="setInterval(() => dots = dots.length < 3 ? dots + '.' : '', 500)">

        {{-- Icône or animée --}}
        <div class="w-20 h-20 rounded-full bg-[#C9A84C]/10 border border-[#C9A84C]/30 flex items-center justify-center mx-auto mb-8 animate-pulse">
            <svg class="w-9 h-9 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-3">Paiement confirmé !</h1>
        <p class="text-[#7A6E5E] text-sm leading-relaxed mb-2">
            Votre paiement a bien été enregistré par Stripe.
        </p>
        <p class="text-[#7A6E5E] text-sm leading-relaxed mb-8">
            Nous finalisons votre commande<span x-text="dots"></span><br>
            Vous recevrez un email avec le lien de téléchargement dans quelques instants.
        </p>

        <div class="card-glass p-4 mb-8 text-left">
            <div class="flex items-start gap-2 text-xs text-[#7A6E5E]">
                <svg class="w-4 h-4 text-[#C9A84C] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Le téléchargement est activé automatiquement dès la confirmation Stripe (quelques secondes). Vérifiez votre boite mail.</span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('client.orders.index') }}" wire:navigate class="btn-gold">
                Mes commandes
            </a>
        </div>

    </div>
</div>

</x-layouts.app>
