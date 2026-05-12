<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OmnyRestore') }}</title>
    <meta name="description" content="OmnyRestore — Restauration photographique par intelligence artificielle. Redonnez vie à vos souvenirs.">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen flex antialiased">

{{-- AAA: lien d'évitement pour lecteurs d'écran / navigation clavier --}}
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50
          focus:px-4 focus:py-2 focus:bg-[#C9A84C] focus:text-[#0D0B08]
          focus:rounded-sm focus:font-semibold focus:text-sm">
    Aller au contenu principal
</a>

{{-- ════════════════════════════════════════════════════════════════════
     COLONNE GAUCHE — décor (3/5 de l'écran sur ≥lg)
     Identique au design original, juste élargie.
     ════════════════════════════════════════════════════════════════════ --}}
<div class="hidden lg:flex lg:w-3/5 relative overflow-hidden bg-[#0D0B08] flex-col justify-between p-12"
     aria-hidden="true">

    {{-- ── Radial glow — exactement comme avant ─────────────────────── --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[600px] h-[600px] bg-[#C9A84C]/8 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-0 left-0
                    w-[300px] h-[300px] bg-[#C9A84C]/5 rounded-full blur-[80px]"></div>
    </div>

    {{-- ── Quadrillage doré — exactement comme avant ─────────────────── --}}
    <div class="absolute inset-0 pointer-events-none opacity-[0.04]"
         style="background-image: linear-gradient(#C9A84C 1px, transparent 1px),
                                  linear-gradient(90deg, #C9A84C 1px, transparent 1px);
                background-size: 60px 60px;">
    </div>

    {{-- ── Logo ──────────────────────────────────────────────────────── --}}
    <a href="{{ route('home') }}" wire:navigate
       class="relative z-10 flex items-center gap-3 group">
        <div class="w-9 h-9 border border-[#C9A84C] flex items-center justify-center
                    transition-colors group-hover:border-[#E8C97A]">
            <span class="text-[#C9A84C] text-xs font-bold tracking-widest
                         group-hover:text-[#E8C97A] transition-colors">OR</span>
        </div>
        <span class="text-[#F5F0E8] font-semibold tracking-[0.15em] text-sm uppercase
                     group-hover:text-[#C9A84C] transition-colors">OmnyRestore</span>
    </a>

    {{-- ── Cadre photo central — agrandi, même style épuré ───────────── --}}
    <div class="relative z-10 flex-1 flex flex-col justify-center">

        {{-- Cadre décoratif agrandi (w-80 h-[26rem] vs w-64 h-80 avant) --}}
        <div class="w-80 h-[26rem] border border-[#C9A84C]/20 mx-auto relative mb-10">
            <div class="absolute inset-4 border border-[#C9A84C]/10 flex items-center justify-center">
                <div class="text-center px-4">
                    {{-- Icône photo --}}
                    <svg class="w-20 h-20 text-[#C9A84C]/20 mx-auto mb-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                    </svg>
                    <div class="w-10 h-px bg-[#C9A84C]/40 mx-auto mb-5"></div>
                    <p class="text-[#C9A84C]/40 text-xs tracking-widest uppercase mb-3">Restauration IA</p>
                    {{-- Ligne de prix discrets --}}
                    <div class="space-y-1.5">
                        <p class="text-[#7A6E5E]/60 text-[11px] tracking-wide">Standard · 1,00 € TTC</p>
                        <p class="text-[#7A6E5E]/60 text-[11px] tracking-wide">Avancée · 2,00 € TTC</p>
                        <p class="text-[#7A6E5E]/60 text-[11px] tracking-wide">Complète · 3,00 € TTC</p>
                    </div>
                </div>
            </div>
            {{-- Coins décorés --}}
            <div class="absolute top-0 left-0 w-5 h-5 border-t border-l border-[#C9A84C]/60"></div>
            <div class="absolute top-0 right-0 w-5 h-5 border-t border-r border-[#C9A84C]/60"></div>
            <div class="absolute bottom-0 left-0 w-5 h-5 border-b border-l border-[#C9A84C]/60"></div>
            <div class="absolute bottom-0 right-0 w-5 h-5 border-b border-r border-[#C9A84C]/60"></div>
        </div>

        {{-- Citation --}}
        <blockquote class="text-center max-w-sm mx-auto">
            <p class="text-[#F5F0E8]/70 text-sm leading-relaxed italic font-light mb-4
                       font-['Playfair_Display']">
                "Vos souvenirs méritent une seconde vie.<br>
                Vous voyez le résultat avant de payer."
            </p>
            <div class="w-8 h-px bg-[#C9A84C]/40 mx-auto"></div>
        </blockquote>
    </div>

    {{-- ── Badges de confiance — même style qu'avant, corrigés ────────── --}}
    <div class="relative z-10 flex items-center gap-8">
        <div class="flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="text-[#7A6E5E] text-xs">Stripe sécurisé</span>
        </div>
        <div class="flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="text-[#7A6E5E] text-xs">RGPD conforme</span>
        </div>
        <div class="flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <span class="text-[#7A6E5E] text-xs">Résultat visible avant paiement</span>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
     COLONNE DROITE — formulaire (2/5 de l'écran sur ≥lg)
     ════════════════════════════════════════════════════════════════════ --}}
<div id="main-content"
     class="flex-1 lg:w-2/5 flex flex-col justify-center items-center min-h-screen
            bg-[#0D0B08] px-6 py-12 relative"
     role="main">

    {{-- Glow subtil à droite --}}
    <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-[#C9A84C]/4 rounded-full
                blur-[100px] pointer-events-none" aria-hidden="true"></div>

    {{-- Logo mobile (petit écran uniquement) --}}
    <div class="lg:hidden mb-10 relative z-10">
        <a href="{{ route('home') }}" wire:navigate
           class="flex items-center gap-3 justify-center"
           aria-label="OmnyRestore — Retour à l'accueil">
            <div class="w-8 h-8 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-xs font-bold tracking-widest">OR</span>
            </div>
            <span class="text-[#F5F0E8] font-semibold tracking-[0.15em] text-sm uppercase">OmnyRestore</span>
        </a>
    </div>

    {{-- Carte formulaire — légèrement plus large qu'avant (max-w-md vs max-w-sm) --}}
    <div class="w-full max-w-md relative z-10">
        {{ $slot }}
    </div>

    {{-- Liens légaux --}}
    <nav class="mt-10 flex gap-5 text-[#7A6E5E] text-xs relative z-10"
         aria-label="Liens légaux">
        <a href="{{ route('legal.mentions') }}"
           class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]"
           wire:navigate>Mentions légales</a>
        <a href="{{ route('legal.privacy') }}"
           class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]"
           wire:navigate>Confidentialité</a>
        <a href="{{ route('legal.cgv') }}"
           class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]"
           wire:navigate>CGV</a>
    </nav>
</div>

@livewireScripts
</body>
</html>
