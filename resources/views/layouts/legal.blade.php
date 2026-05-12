<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'OmnyRestore — Restauration de photos anciennes par IA.')">
    <title>@yield('title', 'OmnyRestore') — OmnyRestore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">

{{-- Navigation --}}
<header class="border-b border-[#C9A84C]/10 py-5 bg-[#0D0B08]/95 backdrop-blur-md">
    <nav class="max-w-4xl mx-auto px-6 flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <div class="w-7 h-7 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-[9px] font-bold tracking-widest">OR</span>
            </div>
            <span class="font-semibold tracking-[0.15em] text-xs uppercase text-[#F5F0E8]">OmnyRestore</span>
        </a>
        <a href="{{ route('home') }}" class="text-[#7A6E5E] text-sm hover:text-[#C9A84C] transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Retour à l'accueil
        </a>
    </nav>
</header>

{{-- Content --}}
<main class="flex-1 max-w-4xl mx-auto px-6 py-16 w-full">

    {{-- Page header --}}
    <div class="mb-12">
        <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-3">@yield('eyebrow')</p>
        <h1 class="text-3xl md:text-4xl font-bold text-[#F5F0E8] mb-4">@yield('heading')</h1>
        <div class="w-12 h-px bg-gradient-to-r from-[#C9A84C] to-transparent mb-4"></div>
        <p class="text-[#7A6E5E] text-sm">
            Dernière mise à jour : <time>@yield('updated_at', '1er juin 2026')</time>
        </p>
    </div>

    {{-- Legal content --}}
    <div class="prose-legal">
        @yield('content')
    </div>

</main>

{{-- Footer --}}
<footer class="mt-16">

    <div class="h-px bg-gradient-to-r from-transparent via-[#C9A84C]/30 to-transparent"></div>

    <div class="bg-[#0A0804] pt-10 pb-6">
        <div class="max-w-4xl mx-auto px-6">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 pb-8 border-b border-[#C9A84C]/8">

                {{-- Marque --}}
                <div class="space-y-3">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                        <div class="w-7 h-7 border border-[#C9A84C] flex items-center justify-center group-hover:bg-[#C9A84C]/10 transition-colors">
                            <span class="text-[#C9A84C] text-[9px] font-bold tracking-widest">OR</span>
                        </div>
                        <span class="text-[#F5F0E8] font-semibold tracking-[0.12em] text-xs uppercase">OmnyRestore</span>
                    </a>
                    <p class="text-[#7A6E5E] text-[11px] leading-relaxed">
                        Une branche d'OmnyVia · Alain GUILLON<br>
                        Meyzieu, France 🇫🇷
                    </p>
                </div>

                {{-- Légal --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-3 font-semibold">Pages légales</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('legal.mentions') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2"><span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Mentions légales</a></li>
                        <li><a href="{{ route('legal.privacy') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2"><span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Politique de confidentialité</a></li>
                        <li><a href="{{ route('legal.cgv') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2"><span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Conditions Générales de Vente</a></li>
                    </ul>
                </div>

                {{-- Contact --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-3 font-semibold">Contact</h4>
                    <ul class="space-y-2">
                        <li>
                            <a href="mailto:contact@omnyrestore.fr" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <svg class="w-3 h-3 text-[#C9A84C]/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                contact@omnyrestore.fr
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <svg class="w-3 h-3 text-[#C9A84C]/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Retour à l'accueil
                            </a>
                        </li>
                    </ul>
                </div>

            </div>

            <div class="pt-5">
                <p class="text-[#7A6E5E] text-[11px] text-center">
                    © {{ date('Y') }} <span class="text-[#C9A84C]/70">OmnyRestore</span> — Paiement sécurisé Stripe · RGPD conforme
                </p>
            </div>

        </div>
    </div>
</footer>

</body>
</html>
