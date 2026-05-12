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
{{-- AAA: skip-to-content link for keyboard users --}}
<body class="min-h-screen flex antialiased">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#C9A84C] focus:text-[#0D0B08] focus:rounded-sm focus:font-semibold focus:text-sm">
    Aller au contenu principal
</a>

{{-- ── Colonne gauche : décor étendu (3/5 de l'écran) ──────────────────── --}}
<div class="hidden lg:flex lg:w-3/5 relative overflow-hidden bg-[#0D0B08] flex-col justify-between"
     aria-hidden="true">

    {{-- ─── Fond : radial glows empilés ─── --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-[#C9A84C]/10 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-10 left-10 w-[350px] h-[350px] bg-[#C9A84C]/6 rounded-full blur-[90px]"></div>
        <div class="absolute top-10 right-10 w-[250px] h-[250px] bg-[#C9A84C]/4 rounded-full blur-[70px]"></div>
    </div>

    {{-- ─── Grille dorée plus prononcée ─── --}}
    <div class="absolute inset-0 pointer-events-none opacity-[0.07]"
         style="background-image: linear-gradient(#C9A84C 1px, transparent 1px), linear-gradient(90deg, #C9A84C 1px, transparent 1px); background-size: 48px 48px;">
    </div>

    {{-- ─── Logo en haut ─── --}}
    <div class="relative z-10 p-10 pb-0">
        <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-3 group">
            <div class="w-10 h-10 border border-[#C9A84C] flex items-center justify-center transition-all group-hover:border-[#E8C97A]">
                <span class="text-[#C9A84C] text-xs font-bold tracking-widest group-hover:text-[#E8C97A] transition-colors">OR</span>
            </div>
            <span class="text-[#F5F0E8] font-semibold tracking-[0.18em] text-sm uppercase group-hover:text-[#C9A84C] transition-colors">OmnyRestore</span>
        </a>
    </div>

    {{-- ─── Contenu central : cadre photo artistique large + citation ─── --}}
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-12 py-8">

        {{-- Grand cadre décoratif avant/après --}}
        <div class="relative w-full max-w-lg mb-10">

            {{-- Cadre extérieur principal --}}
            <div class="relative border border-[#C9A84C]/25 p-1">

                {{-- Coins decoratifs extérieurs --}}
                <div class="absolute -top-1 -left-1 w-6 h-6 border-t-2 border-l-2 border-[#C9A84C]/70"></div>
                <div class="absolute -top-1 -right-1 w-6 h-6 border-t-2 border-r-2 border-[#C9A84C]/70"></div>
                <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-2 border-l-2 border-[#C9A84C]/70"></div>
                <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-2 border-r-2 border-[#C9A84C]/70"></div>

                {{-- Simulation avant/après --}}
                <div class="relative h-64 overflow-hidden bg-[#0F0C08]">

                    {{-- Côté "Avant" --}}
                    <div class="absolute left-0 top-0 w-1/2 h-full flex flex-col items-center justify-center bg-gradient-to-br from-[#1A1208] to-[#0D0B08]">
                        <div class="text-center px-4">
                            <div class="w-16 h-16 mx-auto mb-3 relative">
                                {{-- Photo dégradée (simulation) --}}
                                <div class="w-full h-full bg-gradient-to-br from-[#3A3020]/60 to-[#2A2010]/40 rounded-sm border border-[#7A6E5E]/20"></div>
                                <div class="absolute inset-2 bg-gradient-to-br from-[#5A4E3E]/30 to-transparent rounded-sm"></div>
                                {{-- Rayures damage --}}
                                <div class="absolute top-3 left-1 right-3 h-px bg-[#7A6E5E]/20 rotate-12"></div>
                                <div class="absolute top-6 left-3 right-1 h-px bg-[#7A6E5E]/15 -rotate-6"></div>
                            </div>
                            <p class="text-[#7A6E5E] text-xs tracking-widest uppercase font-light">Avant</p>
                            <div class="w-6 h-px bg-[#7A6E5E]/30 mx-auto mt-2"></div>
                            <p class="text-[#7A6E5E]/50 text-[10px] mt-1.5 leading-relaxed">Photo<br>dégradée</p>
                        </div>
                    </div>

                    {{-- Séparateur central avec curseur --}}
                    <div class="absolute left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-[#C9A84C]/0 via-[#C9A84C]/60 to-[#C9A84C]/0 -translate-x-1/2">
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-[#C9A84C] flex items-center justify-center shadow-lg shadow-[#C9A84C]/20">
                            <svg class="w-4 h-4 text-[#0D0B08]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 9l-4 3 4 3M16 9l4 3-4 3"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Côté "Après" --}}
                    <div class="absolute right-0 top-0 w-1/2 h-full flex flex-col items-center justify-center bg-gradient-to-bl from-[#1A1510] to-[#0D0B08]">
                        <div class="text-center px-4">
                            <div class="w-16 h-16 mx-auto mb-3 relative">
                                {{-- Photo restaurée --}}
                                <div class="w-full h-full bg-gradient-to-br from-[#C9A84C]/20 to-[#8A7035]/10 rounded-sm border border-[#C9A84C]/30"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-[#C9A84C]/50" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-[#C9A84C] text-xs tracking-widest uppercase font-light">Après</p>
                            <div class="w-6 h-px bg-[#C9A84C]/40 mx-auto mt-2"></div>
                            <p class="text-[#C9A84C]/60 text-[10px] mt-1.5 leading-relaxed">Restaurée<br>par IA</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Citation + séparateur --}}
        <blockquote class="text-center max-w-sm">
            <p class="text-[#F5F0E8]/65 text-base leading-relaxed italic font-light font-['Playfair_Display'] mb-5">
                "Vos souvenirs méritent une seconde vie.<br>
                Vous voyez le résultat avant de payer."
            </p>
            <div class="flex items-center gap-4 justify-center">
                <div class="flex-1 h-px bg-gradient-to-r from-transparent to-[#C9A84C]/30"></div>
                <div class="w-1.5 h-1.5 bg-[#C9A84C]/50 rotate-45"></div>
                <div class="flex-1 h-px bg-gradient-to-l from-transparent to-[#C9A84C]/30"></div>
            </div>
        </blockquote>

        {{-- Étapes du service en 3 colonnes --}}
        <div class="grid grid-cols-3 gap-4 mt-8 w-full max-w-md">
            @foreach ([
                ['01', 'Déposez', 'Vos photos originales'],
                ['02', 'Notre IA analyse', 'et restaure chaque photo'],
                ['03', 'Téléchargez', 'en haute définition'],
            ] as [$num, $title, $sub])
            <div class="text-center">
                <div class="text-[#C9A84C]/40 text-xs font-mono mb-1.5">{{ $num }}</div>
                <div class="text-[#F5F0E8]/70 text-xs font-semibold leading-tight">{{ $title }}</div>
                <div class="text-[#7A6E5E] text-[10px] mt-0.5 leading-relaxed">{{ $sub }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ─── Badges de confiance en bas ─── --}}
    <div class="relative z-10 p-10 pt-0">
        <div class="border-t border-[#C9A84C]/10 pt-6 flex items-center gap-8">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="text-[#7A6E5E] text-xs">Paiement Stripe</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="text-[#7A6E5E] text-xs">RGPD conforme</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                <span class="text-[#7A6E5E] text-xs">Résultat visible avant paiement</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Colonne droite : formulaire (2/5 de l'écran) ─────────────────────── --}}
<div id="main-content"
     class="flex-1 lg:w-2/5 flex flex-col justify-center items-center min-h-screen bg-[#0A0805] px-8 py-12 relative"
     role="main">

    {{-- Glow subtil --}}
    <div class="absolute top-0 right-0 w-[350px] h-[350px] bg-[#C9A84C]/5 rounded-full blur-[100px] pointer-events-none" aria-hidden="true"></div>
    <div class="absolute bottom-0 left-0 w-[200px] h-[200px] bg-[#C9A84C]/3 rounded-full blur-[60px] pointer-events-none" aria-hidden="true"></div>

    {{-- Grille subtile à droite aussi --}}
    <div class="absolute inset-0 pointer-events-none opacity-[0.025]"
         style="background-image: linear-gradient(#C9A84C 1px, transparent 1px), linear-gradient(90deg, #C9A84C 1px, transparent 1px); background-size: 48px 48px;"
         aria-hidden="true">
    </div>

    {{-- Logo mobile (visible uniquement sur petit écran) --}}
    <div class="lg:hidden mb-10 relative z-10">
        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 justify-center" aria-label="OmnyRestore — Retour à l'accueil">
            <div class="w-9 h-9 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-xs font-bold tracking-widest">OR</span>
            </div>
            <span class="text-[#F5F0E8] font-semibold tracking-[0.15em] text-sm uppercase">OmnyRestore</span>
        </a>
    </div>

    {{-- Formulaire --}}
    <div class="w-full max-w-md relative z-10">
        {{ $slot }}
    </div>

    {{-- Liens légaux en bas --}}
    <nav class="mt-10 flex gap-5 text-[#7A6E5E] text-xs relative z-10" aria-label="Liens légaux">
        <a href="{{ route('legal.mentions') }}" class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]" wire:navigate>Mentions légales</a>
        <a href="{{ route('legal.privacy') }}" class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]" wire:navigate>Confidentialité</a>
        <a href="{{ route('legal.cgv') }}" class="hover:text-[#C9A84C] transition-colors focus:outline-none focus:text-[#C9A84C]" wire:navigate>CGV</a>
    </nav>
</div>

@livewireScripts
</body>
</html>
