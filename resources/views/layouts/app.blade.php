<!DOCTYPE html>
{{-- OmnyRestore App Layout --}}
<html lang="fr" style="font-size: 19px;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OmnyRestore') }} — @yield('title', 'Espace client')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Chart.js — disponible globalement pour les pages admin --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @livewireStyles
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { display: flex !important; flex-direction: column !important; min-height: 100vh !important; }
        main { flex: 1 0 auto !important; min-height: 70vh !important; }
        footer { flex-shrink: 0 !important; }

        /* API View Transitions — Fondus fluides entre les pages (Aller/Retour) */
        @view-transition { navigation: auto; }
        
        ::view-transition-old(root) {
            animation: 0.3s cubic-bezier(0.4, 0, 0.2, 1) both fade-out;
        }
        ::view-transition-new(root) {
            animation: 0.4s cubic-bezier(0.4, 0, 0.2, 1) both fade-in;
        }

        @keyframes fade-in {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fade-out {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body class="bg-[#0D0B08] text-[#F5F0E8] flex flex-col min-h-full" x-data x-on:omny:confirm.window="$store.confirmModal.open($event.detail)">

{{-- ========== TOP NAV ========== --}}
<livewire:layout.navbar />

{{-- ========== MAIN ========== --}}
<main class="w-full max-w-[1440px] mx-auto app-layout py-12 flex-grow">
    {{-- Flash messages globaux (non affichés sur les pages Livewire qui gèrent leur propre feedback) --}}
    @unless (request()->routeIs('admin.orders.show') || request()->routeIs('client.orders.show'))
    @if (session('success'))
    <div class="flex items-center gap-3 bg-emerald-950/50 border border-emerald-500/30 text-emerald-400 rounded-sm px-4 py-3 mb-6 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div class="flex items-center gap-3 bg-red-950/50 border border-red-500/30 text-red-400 rounded-sm px-4 py-3 mb-6 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @endunless

    {!! isset($slot) ? $slot : $__env->yieldContent('content') !!}
</main>

{{-- ========== FOOTER ========== --}}
<footer>

    {{-- Séparateur dégradé doré --}}
    <div class="h-px bg-gradient-to-r from-transparent via-[#C9A84C]/30 to-transparent"></div>

    <div class="bg-[#0A0804] pt-12 pb-6">
        <div class="max-w-[1440px] mx-auto app-layout">

            {{-- ── Grille principale ── --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-10 border-b border-[#C9A84C]/8">

                {{-- Colonne 1 : Marque --}}
                <div class="md:col-span-1 space-y-4">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 group">
                        <img src="{{ asset('images/logo.png') }}" alt="OmnyRestore" class="w-16 h-16 object-contain group-hover:scale-110 transition-transform">
                        <span class="text-[#F5F0E8] font-semibold tracking-[0.12em] text-sm uppercase">OmnyRestore</span>
                    </a>
                    <p class="text-[#7A6E5E] text-xs leading-relaxed">
                        Restauration de photographies anciennes par intelligence artificielle.
                        Voyez le résultat <em>avant</em> de payer.
                    </p>
                    {{-- Badges de confiance --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-[#C9A84C] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span class="text-[#7A6E5E] text-[11px]">Paiement sécurisé Stripe PCI-DSS</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-[#C9A84C] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span class="text-[#7A6E5E] text-[11px]">RGPD conforme · Données en France</span>
                        </div>
                    </div>
                </div>

                {{-- Colonne 2 : Navigation --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-4 font-semibold">Navigation</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ route('home') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Accueil
                            </a>
                        </li>
                        @auth
                        <li>
                            <a href="{{ route('client.orders.index') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Mes commandes
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('client.profile') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Mon profil
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('client.tickets.index') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Mes tickets
                            </a>
                        </li>
                        @endauth
                    </ul>
                </div>

                {{-- Colonne 3 : Légal --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-4 font-semibold">Informations légales</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ route('legal.mentions') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Mentions légales
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('legal.privacy') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Politique de confidentialité
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('legal.cgv') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>
                                Conditions Générales de Vente
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Colonne 4 : Contact & Support --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-4 font-semibold">Contact & Support</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="mailto:contact@omnyrestore.fr"
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-[#C9A84C]/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                contact@omnyrestore.fr
                            </a>
                        </li>
                        @auth
                        <li>
                            <a href="{{ route('client.tickets.create') }}" wire:navigate
                               class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-[#C9A84C]/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Ouvrir un ticket support
                            </a>
                        </li>
                        @endauth
                        <li class="pt-2">
                            <p class="text-[#7A6E5E] text-[11px] leading-relaxed">
                                Réponse sous 24–48h ouvrées.<br>
                                Du lundi au vendredi.
                            </p>
                        </li>
                    </ul>
                </div>

            </div>{{-- fin grille --}}

            {{-- ── Barre de bas de page ── --}}
            <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-[#7A6E5E] text-[11px]">
                    © {{ date('Y') }} <span class="text-[#C9A84C]/70">OmnyRestore</span> — une branche d'<span class="text-[#C9A84C]/70">OmnyVia</span> · Alain GUILLON
                </p>
                <div class="flex items-center gap-4">
                    @auth
                    <a href="{{ route('client.account.delete') }}" wire:navigate
                       class="text-red-900/60 text-[10px] hover:text-red-400 transition-colors flex items-center gap-1">
                        <span class="w-1 h-1 rounded-full bg-red-900/40"></span>
                        Supprimer mes données (RGPD)
                    </a>
                    @endauth
                    <p class="text-[#7A6E5E] text-[11px] flex items-center gap-1.5">
                        Conçu et hébergé en France
                        <span class="text-base leading-none">🇫🇷</span>
                    </p>
                </div>
            </div>

        </div>
    </div>
</footer>

{{-- ═══ Modal de confirmation global ══════════════════════════════════ --}}
<div x-data
     x-show="$store.confirmModal.show"
     x-transition.opacity
     style="display:none;"
     class="fixed inset-0 z-[200] flex items-center justify-center p-4"
     aria-modal="true">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"
         @click="$store.confirmModal.cancel()"></div>

    {{-- Boîte de dialogue --}}
    <div x-show="$store.confirmModal.show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative w-full max-w-md bg-[#141008] border border-[#C9A84C]/20 rounded-sm shadow-2xl overflow-hidden">

        {{-- Bandeau top --}}
        <div class="h-0.5 w-full"
             :style="{ background: $store.confirmModal.danger
                ? 'linear-gradient(to right, #ef4444, #dc2626)'
                : 'linear-gradient(to right, #C9A84C, #E8C97A)' }">
        </div>

        <div class="p-6">
            {{-- Icône --}}
            <div class="flex items-center gap-4 mb-5">
                <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
                     :style="{ background: $store.confirmModal.danger ? 'rgba(239,68,68,0.12)' : 'rgba(201,168,76,0.12)',
                               border: $store.confirmModal.danger ? '1px solid rgba(239,68,68,0.3)' : '1px solid rgba(201,168,76,0.3)' }">
                    <template x-if="$store.confirmModal.danger">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </template>
                    <template x-if="!$store.confirmModal.danger">
                        <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                </div>
                <div>
                    <h3 class="text-[#F5F0E8] font-semibold text-base" x-text="$store.confirmModal.title"></h3>
                    <p class="text-[#7A6E5E] text-sm mt-0.5" x-text="$store.confirmModal.message"></p>
                </div>
            </div>

            {{-- Boutons --}}
            <div class="flex items-center justify-end gap-3">
                <button @click="$store.confirmModal.cancel()"
                        class="px-4 py-2 text-sm text-[#7A6E5E] border border-[#7A6E5E]/25 rounded-sm hover:border-[#7A6E5E]/60 hover:text-[#F5F0E8] transition-all">
                    Annuler
                </button>
                <button @click="$store.confirmModal.confirm()"
                        :style="$store.confirmModal.danger
                            ? 'background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#f87171;'
                            : 'background:rgba(201,168,76,0.15);border:1px solid rgba(201,168,76,0.4);color:#C9A84C;'"
                        class="px-5 py-2 text-sm font-medium rounded-sm hover:opacity-80 transition-all"
                        x-text="$store.confirmModal.confirmLabel">
                </button>
            </div>
        </div>
    </div>
</div>

@livewireScripts

<script>
/**
 * Initialise le store Alpine confirmModal.
 * Compatible wire:navigate : appel aussi sur livewire:navigated
 * car alpine:init ne se déclenche qu'une seule fois.
 */
function initConfirmModalStore() {
    if (typeof Alpine === 'undefined') return;
    
    // Ne pas réinitialiser si déjà présent
    if (Alpine.store('confirmModal')) return;

    Alpine.store('confirmModal', {
        show: false,
        title: '',
        message: '',
        confirmLabel: 'Confirmer',
        danger: false,
        _resolve: null,

        open(detail) {
            this.title        = detail.title        || 'Confirmation';
            this.message      = detail.message      || 'Êtes-vous sûr ?';
            this.confirmLabel = detail.confirmLabel || 'Confirmer';
            this.danger       = detail.danger       !== undefined ? detail.danger : true;
            this._resolve     = detail.callback     || null;
            this.show = true;
        },

        confirm() {
            const callback = this._resolve;
            this.show = false;
            this._resolve = null;
            if (typeof callback === 'function') {
                callback();
            }
        },

        cancel() {
            this.show = false;
            this._resolve = null;
        }
    });
}

if (typeof Alpine !== 'undefined') {
    initConfirmModalStore();
} else {
    document.addEventListener('alpine:init', initConfirmModalStore);
}
// Protection wire:navigate
document.addEventListener('livewire:navigated', initConfirmModalStore);

/**
 * Support natif des View Transitions pour Livewire 3
 * Permet d'avoir les animations de fondu même sur les mises à jour asynchrones.
 */
document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
        respond(() => {
            if (!document.startViewTransition) return;
            
            // On enveloppe la mise à jour du DOM dans une transition de vue
            document.startViewTransition(() => {
                // Cette fonction sera appelée quand le DOM est prêt à être mis à jour
            });
        });
    });
});

/**
 * Helper global : omnyConfirm({title, message, confirmLabel, danger}) → Promise<void>
 * Usage dans Blade/Alpine :
 *   @click="omnyConfirm({title:'Clore ?', message:'...', danger:true}).then(() => $wire.closeTicket())"
 */
window.omnyConfirm = function(options) {
    return new Promise((resolve) => {
        const userCallback = options.callback;
        const internalCallback = () => {
            if (typeof userCallback === 'function') userCallback();
            resolve();
        };
        window.dispatchEvent(new CustomEvent('omny:confirm', {
            detail: { ...options, callback: internalCallback }
        }));
    });
};
</script>
@stack('scripts')
</body>
</html>
