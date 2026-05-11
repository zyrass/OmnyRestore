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
<footer class="border-t border-[#C9A84C]/10 py-8 mt-16">
    <div class="max-w-4xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-4">
        <p class="text-[#7A6E5E] text-xs">© {{ date('Y') }} OmnyRestore — OmnyVia</p>
        <div class="flex gap-6 text-xs">
            <a href="{{ route('legal.mentions') }}" class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">Mentions légales</a>
            <a href="{{ route('legal.privacy') }}" class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">Confidentialité</a>
            <a href="{{ route('legal.cgv') }}" class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">CGV</a>
        </div>
    </div>
</footer>

</body>
</html>
