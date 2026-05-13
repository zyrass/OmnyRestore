<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="OmnyRestore — Restauration de photos anciennes par intelligence artificielle. Redonnez vie à vos souvenirs en 8K, qualité studio professionnel.">
    <title>OmnyRestore — Restauration Photo IA Premium</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="noise" x-data="{ scrolled: window.scrollY > 50 }" @scroll.window="scrolled = window.scrollY > 50">

{{-- ========== NAVIGATION ========== --}}
<header
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 py-6"
    :class="scrolled ? 'bg-[#0D0B08]/95 backdrop-blur-md border-b border-[#C9A84C]/10 !py-4' : ''"
>
    <nav class="max-w-6xl mx-auto px-6 flex items-center justify-between">
        {{-- Logo --}}
        <a href="/" class="flex items-center gap-3">
            <div class="w-8 h-8 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-xs font-bold tracking-widest">OR</span>
            </div>
            <span class="font-semibold tracking-[0.15em] text-sm uppercase text-[#F5F0E8]">OmnyRestore</span>
        </a>

        {{-- Nav links — ordre calé sur l'ordre réel des sections dans la page --}}
        <div class="hidden md:flex items-center gap-8 text-sm text-[#7A6E5E]">
            <a href="#examples" class="hover:text-[#C9A84C] transition-colors duration-200">Avant / Apr&egrave;s</a>
            <a href="#how" class="hover:text-[#C9A84C] transition-colors duration-200">Comment ça marche</a>
            <a href="#pricing" class="hover:text-[#C9A84C] transition-colors duration-200">Prix par photo</a>
            <a href="#testimonials" class="hover:text-[#C9A84C] transition-colors duration-200">Témoignages</a>
        </div>

        {{-- CTA Auth --}}
        <div class="flex items-center gap-4">
            @auth
                @if (Auth::user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}" class="btn-gold text-sm py-2.5 px-6">
                    ⚙ Panel Admin
                </a>
                @else
                <a href="{{ route('client.orders.index') }}" class="btn-gold text-sm py-2.5 px-6">
                    Mon espace
                </a>
                @endif
            @else
                <a href="{{ route('login') }}" class="text-[#7A6E5E] hover:text-[#C9A84C] text-sm transition-colors">
                    Connexion
                </a>
                <a href="{{ route('register') }}" class="btn-gold text-sm py-2.5 px-6">
                    Commencer
                </a>
            @endauth
        </div>
    </nav>
</header>

{{-- ========== HERO ========== --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    {{-- Background radial gradient --}}
    <div class="absolute inset-0">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-[#C9A84C]/5 rounded-full blur-[120px]"></div>
        <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-[#C9A84C]/3 rounded-full blur-[100px]"></div>
    </div>

    {{-- Grid lines decoration --}}
    <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(#C9A84C 1px, transparent 1px), linear-gradient(90deg, #C9A84C 1px, transparent 1px); background-size: 80px 80px;"></div>

    <div class="relative z-10 text-center max-w-5xl mx-auto px-6 pt-32">
        {{-- Eyebrow --}}
        <div class="inline-flex items-center gap-3 mb-8">
            <div class="w-8 h-px bg-[#C9A84C]/60"></div>
            <span class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase font-medium">Restauration IA &bull; R&eacute;sultat garanti avant paiement</span>
            <div class="w-8 h-px bg-[#C9A84C]/60"></div>
        </div>

        {{-- Headline --}}
        <h1 class="text-5xl md:text-7xl font-bold text-[#F5F0E8] leading-tight mb-6">
            Vos photos anciennes,<br>
            <em class="text-[#C9A84C] not-italic">restaur&eacute;es.</em>
        </h1>

        {{-- Subheadline --}}
        <p class="text-lg md:text-xl text-[#7A6E5E] max-w-2xl mx-auto mb-10 leading-relaxed">
            Nous restaurons vos souvenirs abîmés grâce à l'intelligence artificielle.
            L'IA améliore et rattrape considérablement chaque photo —
            <span class="text-[#F5F0E8]">vous ne payez qu'après avoir vu l'aperçu.</span>
        </p>

        {{-- CTA buttons — auth-aware --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
            @auth
            @if (Auth::user()->role === 'admin')
            {{-- ── CTAs Admin ── --}}
            <a href="{{ route('admin.clients') }}" class="btn-gold glow-gold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Voir la liste des clients
            </a>
            <a href="{{ route('admin.revenue') }}" class="btn-outline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Accéder au chiffre d'affaire
            </a>
            @else
            {{-- ── CTAs Client ── --}}
            <a href="{{ route('client.orders.create') }}" class="btn-gold glow-gold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Déposer mes photos
            </a>
            <a href="{{ route('client.orders.index') }}" class="btn-outline">
                Mes commandes
            </a>
            @endif
            @else
            {{-- ── CTAs Visiteur ── --}}
            <a href="{{ route('register') }}" class="btn-gold glow-gold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Déposer mes photos
            </a>
            <a href="#how" class="btn-outline">
                Voir comment ça marche
            </a>
            @endauth
        </div>

        {{-- Trust indicators --}}
        <div class="flex flex-wrap items-center justify-center gap-8 text-[#7A6E5E] text-sm">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Paiement sécurisé Stripe
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Aperçu avant paiement
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Photos supprimées après 6 mois
            </div>
        </div>
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 animate-bounce">
        <span class="text-[#7A6E5E] text-xs tracking-widest uppercase">Découvrir</span>
        <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </div>
</section>

{{-- ========== BEFORE / AFTER ========== --}}
<section id="examples" class="py-32 max-w-6xl mx-auto px-6">
    <div class="text-center mb-16">
        <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-4">Résultats réels</p>
        <h2 class="text-4xl font-bold text-[#F5F0E8] mb-4">Avant / Après</h2>
        <div class="divider-gold my-6"></div>
        <p class="text-[#7A6E5E] max-w-xl mx-auto">Photos restaurées par notre IA. Textures, couleurs et netteté améliorées selon l'état de chaque photo &mdash; résultat visible avant toute décision.</p>
    </div>

    {{-- Instructions --}}
    <p class="text-center text-xs text-[#7A6E5E]/60 mb-10 tracking-wide">
        ← Glissez le curseur pour comparer →
    </p>

    {{-- Interactive sliders (input range overlay — reliable on all devices) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach([
            ['year' => '1952', 'label' => 'Portrait de famille', 'issues' => 'Déchirure, jaunissement sévère',   'slug' => 'portrait'],
            ['year' => '1967', 'label' => 'Photo de mariage',    'issues' => 'Taches, décoloration, bords abîmés', 'slug' => 'mariage'],
            ['year' => '1943', 'label' => 'Portrait militaire',  'issues' => 'Dommages eau, pliures, contraste perdu', 'slug' => 'militaire'],
        ] as $ex)
        <div class="card-glass overflow-hidden">
            {{-- Slider interactif — événements Alpine.js natifs sur le conteneur --}}
            <div class="relative h-64 overflow-hidden select-none cursor-col-resize"
                 x-data="{ pct: 50, dragging: false }"
                 @mousedown.prevent="dragging = true"
                 @mouseup.window="dragging = false"
                 @mouseleave="dragging = false"
                 @mousemove="if(dragging){ let r=$el.getBoundingClientRect(); pct=Math.max(0,Math.min(100,($event.clientX-r.left)/r.width*100)) }"
                 @click="let r=$el.getBoundingClientRect(); pct=Math.max(0,Math.min(100,($event.clientX-r.left)/r.width*100))"
                 @touchstart.prevent="dragging = true"
                 @touchend.window="dragging = false"
                 @touchmove.prevent="if(dragging){ let r=$el.getBoundingClientRect(); pct=Math.max(0,Math.min(100,($event.touches[0].clientX-r.left)/r.width*100)) }">

                {{-- APRÈS (restaurée) — pleine largeur en fond --}}
                <img src="/images/samples/apres-{{ $ex['slug'] }}.png"
                     alt="Après restauration — {{ $ex['label'] }} {{ $ex['year'] }}"
                     class="absolute inset-0 w-full h-full object-cover object-top pointer-events-none"
                     draggable="false">

                {{-- AVANT (endommagée) — révélée à gauche, pointer-events-none --}}
                <div class="absolute inset-0 z-10 pointer-events-none"
                     :style="{ clipPath: `inset(0 ${100 - pct}% 0 0)` }">
                    <img src="/images/samples/avant-{{ $ex['slug'] }}.png"
                         alt="Avant restauration — {{ $ex['label'] }} {{ $ex['year'] }}"
                         class="w-full h-full object-cover object-top"
                         style="filter: sepia(0.8) contrast(0.65) brightness(0.55);"
                         draggable="false">
                </div>

                {{-- Handle visuel — pointer-events-none (le conteneur capte tout) --}}
                <div class="absolute inset-y-0 z-20 pointer-events-none flex items-center"
                     :style="`left: ${pct}%`">
                    <div class="absolute inset-y-0 w-px -translate-x-1/2 bg-white/90"></div>
                    <div class="w-10 h-10 rounded-full bg-white shadow-2xl -translate-x-1/2 flex items-center justify-center border border-white/20">
                        <svg class="w-4 h-4 text-[#1A1510]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l-4 4 4 4M16 9l4 4-4 4"/>
                        </svg>
                    </div>
                </div>

                {{-- Labels --}}
                <span class="absolute top-3 left-3 z-10 px-2 py-0.5 bg-black/60 text-white/80 text-[10px] font-bold tracking-widest uppercase pointer-events-none">Avant</span>
                <span class="absolute top-3 right-3 z-10 px-2 py-0.5 bg-[#C9A84C] text-[#0D0B08] text-[10px] font-bold tracking-widest uppercase pointer-events-none">Après</span>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[#F5F0E8] font-medium text-sm">{{ $ex['label'] }}</span>
                    <span class="text-[#C9A84C] text-xs border border-[#C9A84C]/30 px-2 py-0.5 rounded-full">{{ $ex['year'] }}</span>
                </div>
                <p class="text-[#7A6E5E] text-xs">{{ $ex['issues'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

</section>

{{-- ========== HOW IT WORKS ========== --}}
<section id="how" class="py-32 border-y border-[#C9A84C]/10 bg-[#1A1510]/40">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center mb-20">
            <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-4">Processus</p>
            <h2 class="text-4xl font-bold text-[#F5F0E8] mb-4">Comment ça marche</h2>
            <div class="divider-gold my-6"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            @foreach([
                ['n' => '01', 'title' => 'Déposez vos photos',     'desc' => 'Uploadez vos photos abîmées directement sur la plateforme. Formats JPEG, PNG, TIFF acceptés.'],
                ['n' => '02', 'title' => 'Analyse & Restauration', 'desc' => "Notre IA analyse les dommages et améliore drastiquement chaque photo : suppression des artefacts, reconstruction des textures, correction des couleurs. L'IA fait ce qu'elle peut au mieux — le résultat vous est montré avant tout paiement."],
                ['n' => '03', 'title' => 'Aperçu filigranné',      'desc' => "Consultez l'aperçu de vos photos restaurées avant de payer. Vous voyez le résultat, puis vous décidez — sans engagement."],
                ['n' => '04', 'title' => 'Téléchargement HD',      'desc' => 'Après paiement, téléchargez vos photos en haute résolution, sans filigrane, dans un ZIP sécurisé accompagné de votre facture PDF.'],
            ] as $step)
            <div class="text-center group">
                <div class="step-badge mx-auto mb-6 group-hover:bg-[#C9A84C]/20 group-hover:border-[#C9A84C] transition-all duration-300">
                    {{ $step['n'] }}
                </div>
                <h3 class="text-[#F5F0E8] font-semibold mb-3">{{ $step['title'] }}</h3>
                <p class="text-[#7A6E5E] text-sm leading-relaxed">{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Guarantee box --}}
        <div class="mt-20 card-glass p-8 text-center max-w-2xl mx-auto">
            <div class="w-12 h-12 border border-[#C9A84C]/40 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="text-[#F5F0E8] text-xl font-semibold mb-3">Notre garantie : vous voyez avant de payer</h3>
            <p class="text-[#7A6E5E] text-sm leading-relaxed">
                Contrairement à d'autres services, nous générons un aperçu filigranné de vos photos restaurées <strong class="text-[#F5F0E8]">avant tout paiement</strong>.
                Si le résultat ne vous convient pas, vous ne devez rien.
            </p>
        </div>
    </div>
</section>

{{-- ========== PRICING ========== --}}
<section id="pricing" class="py-32 max-w-6xl mx-auto px-6">
    <div class="text-center mb-16">
        <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-4">Tarification</p>
        <h2 class="text-4xl font-bold text-[#F5F0E8] mb-4">Prix par photo</h2>
        <div class="divider-gold my-6"></div>
        <p class="text-[#7A6E5E] max-w-md mx-auto text-sm">Chaque photo est analys&eacute;e individuellement. Le niveau de dommage d&eacute;termine le prix &mdash; vous ne payez que les photos que vous gardez.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
        @foreach([
            ['label' => 'Restauration Standard', 'price' => '1', 'ttc' => '1,00&nbsp;&euro;&nbsp;TTC', 'desc' => 'Jaunissement, poussière, légères décolorations', 'features' => ['Analyse IA automatique', 'Aperçu filigranné inclus', 'Livraison ZIP + Facture', 'Délai 24-48h']],
            ['label' => 'Restauration Avancée',  'price' => '2', 'ttc' => '2,00&nbsp;&euro;&nbsp;TTC', 'desc' => 'Rayures profondes, décoloration forte, pliures, grain', 'features' => ['Tout ce qui précède', 'Reconstruction avancée', 'Correction de contraste', 'Délai 48-72h'], 'featured' => true],
            ['label' => 'Restauration Complète', 'price' => '3', 'ttc' => '3,00&nbsp;&euro;&nbsp;TTC', 'desc' => 'Déchirures, dommages eau, zones partiellement manquantes', 'features' => ['Tout ce qui précède', 'Reconstruction complexe', 'Zones reconstruites par IA', 'Délai 72-96h']],
        ] as $plan)
        <div class="card-glass p-8 text-center {{ ($plan['featured'] ?? false) ? 'border-[#C9A84C]/50 relative' : '' }}">
            @if($plan['featured'] ?? false)
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-[#C9A84C] text-[#0D0B08] text-xs font-bold px-4 py-1 tracking-widest uppercase">
                Populaire
            </div>
            @endif
            <h3 class="text-[#F5F0E8] font-semibold mb-1">{{ $plan['label'] }}</h3>
            <p class="text-[#7A6E5E] text-xs mb-6">{{ $plan['desc'] }}</p>
            <div class="mb-1">
                <span class="text-4xl font-bold text-[#C9A84C]">{{ $plan['price'] }}&euro;</span>
                <span class="text-sm text-[#7A6E5E]"> / photo HT</span>
            </div>
            <p class="text-xs text-[#7A6E5E]/70 mb-8">{!! $plan['ttc'] !!}</p>
            <ul class="space-y-3 text-sm text-[#7A6E5E] text-left mb-8">
                @foreach($plan['features'] as $f)
                <li class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-[#C9A84C] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $f }}
                </li>
                @endforeach
            </ul>
            @auth
            <a href="{{ route('client.orders.create') }}" class="{{ ($plan['featured'] ?? false) ? 'btn-gold w-full justify-center' : 'btn-outline w-full justify-center' }}">
            @else
            <a href="{{ route('register') }}" class="{{ ($plan['featured'] ?? false) ? 'btn-gold w-full justify-center' : 'btn-outline w-full justify-center' }}">
            @endauth
                @auth Nouvelle commande @else Commencer @endauth
            </a>
        </div>
        @endforeach
    </div>
    <p class="text-center text-xs text-[#7A6E5E]/60 mt-8">* Le niveau est estim&eacute; automatiquement selon l'&eacute;tat de vos photos. TVA 20% incluse dans le prix TTC.</p>
</section>

{{-- ========== SECTION IA ========== --}}
<section class="py-32 border-y border-[#C9A84C]/10 bg-[#1A1510]/40">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center mb-16">
            <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-4">Technologie</p>
            <h2 class="text-4xl font-bold text-[#F5F0E8] mb-4">Ce que l'IA peut faire pour vous</h2>
            <div class="divider-gold my-6"></div>
            <p class="text-[#7A6E5E] max-w-2xl mx-auto text-sm leading-relaxed">
                L'intelligence artificielle permet d'améliorer et de rattraper drastiquement une photo abîmée.
                Elle analyse chaque pixel, reconstitue les zones dégradées et corrige les déséquilibres de couleur —
                <strong class="text-[#F5F0E8]">mais elle ne peut pas créer ce qui n'existe plus</strong>.
                Le résultat dépend toujours de l'état initial de la photo.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach([
                [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>',
                    'title' => 'Mémoire familiale',
                    'desc'  => 'Redonnez vie aux photos de vos grands-parents, de votre enfance ou de moments à jamais disparus. Chaque photo restaurée est un souvenir sauvé.'
                ],
                [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
                    'title' => 'Restauration intelligente',
                    'desc'  => "L'IA détecte le jaunissement, les rayures, les déchirures et les zones abîmées. Elle les corrige de façon cohérente avec le reste de l'image — sans altérer les visages ni les détails d'époque."
                ],
                [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                    'title' => 'Transparence totale',
                    'desc'  => "Vous voyez le résultat avant de payer. Si l'IA n'a pas pu rattraper suffisamment votre photo, vous n'êtes pas engagé. Aucune mauvaise surprise."
                ],
            ] as $b)
            <div class="card-glass p-8 text-center group hover:border-[#C9A84C]/30 transition-colors duration-300">
                <div class="w-14 h-14 border border-[#C9A84C]/30 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:border-[#C9A84C] group-hover:bg-[#C9A84C]/10 transition-all duration-300">
                    <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $b['icon'] !!}</svg>
                </div>
                <h3 class="text-[#F5F0E8] font-semibold mb-3">{{ $b['title'] }}</h3>
                <p class="text-[#7A6E5E] text-sm leading-relaxed">{{ $b['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========== TESTIMONIALS ========== --}}
@php $testimonials = \App\Models\Testimonial::published()->orderByDesc('created_at')->limit(6)->get(); @endphp
<section id="testimonials" class="py-32 max-w-6xl mx-auto px-6">
    <div class="text-center mb-16">
        <p class="text-[#C9A84C] text-xs tracking-[0.3em] uppercase mb-4">Témoignages</p>
        <h2 class="text-4xl font-bold text-[#F5F0E8] mb-4">Ce que nos clients disent</h2>
        <div class="divider-gold my-6"></div>
    </div>

    @if($testimonials->isEmpty())
    {{-- Empty state --}}
    <div class="text-center py-12">
        <div class="w-16 h-16 border border-[#C9A84C]/20 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-[#7A6E5E]/50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
            </svg>
        </div>
        <p class="text-[#7A6E5E] text-sm">Aucun avis pour le moment.</p>
        <p class="text-[#7A6E5E]/50 text-xs mt-1">Soyez le premier à partager votre expérience après votre restauration.</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($testimonials as $t)
        <div class="card-glass p-6 flex flex-col gap-4">
            {{-- Header : avatar + nom + étoiles --}}
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-[#C9A84C]/15 border border-[#C9A84C]/40 flex items-center justify-center shrink-0">
                    <span class="text-[#C9A84C] text-sm font-bold tracking-wide">{{ $t->author_initials }}</span>
                </div>
                <div>
                    <p class="text-[#F5F0E8] text-sm font-semibold">{{ $t->author_name }}</p>
                    <div class="flex gap-0.5 mt-1">
                        @for($s = 1; $s <= 5; $s++)
                        <svg class="w-3 h-3 {{ $s <= $t->rating ? 'text-[#C9A84C]' : 'text-[#7A6E5E]/25' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        @endfor
                    </div>
                </div>
            </div>
            {{-- Avis --}}
            <p class="text-[#7A6E5E] text-sm leading-relaxed italic flex-1">&ldquo;{{ $t->content }}&rdquo;</p>
        </div>
        @endforeach
    </div>
    @endif
</section>

{{-- ========== TRANSITION EMOTION ========== --}}
<section class="py-24 overflow-hidden bg-gradient-to-b from-transparent to-[#0D0B08]">
    <div class="max-w-6xl mx-auto px-6">
        <div class="relative rounded-sm overflow-hidden aspect-[21/9] md:aspect-[32/9] group border border-[#C9A84C]/10 cursor-default shadow-2xl">
            <img src="/images/transition-heritage.png" 
                 alt="Transmission et émotion" 
                 class="w-full h-full object-cover opacity-30 transform scale-100 group-hover:scale-105 transition-all duration-1000 ease-out">
            
            {{-- Overlay sombre global pour le contraste AAA --}}
            <div class="absolute inset-0 bg-black/60 pointer-events-none"></div>
            
            {{-- Dégradés de finition --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#0D0B08] via-transparent to-[#0D0B08] pointer-events-none opacity-80"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#0D0B08] via-transparent to-[#0D0B08] pointer-events-none opacity-80"></div>
            
            <div class="absolute inset-0 flex items-center justify-center text-center p-8 pointer-events-none">
                <div class="max-w-3xl transform translate-y-4 group-hover:translate-y-0 transition-all duration-1000">
                    <div class="w-16 h-px bg-[#C9A84C]/40 mx-auto mb-8"></div>
                    <h3 class="text-3xl md:text-4xl font-medium italic text-[#F5F0E8] leading-relaxed mb-6 drop-shadow-[0_2px_10px_rgba(0,0,0,0.8)]">
                        &ldquo;Parce que chaque souvenir pr&eacute;cieux m&eacute;rite d'&ecirc;tre transmis aux g&eacute;n&eacute;rations futures dans sa plus belle et authentique lumi&egrave;re.&rdquo;
                    </h3>
                    <p class="text-[#C9A84C] text-[11px] tracking-[0.5em] uppercase font-bold drop-shadow-md">L'art de la restauration</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========== CTA FINAL ========== --}}
<section class="py-32 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-[#C9A84C]/5 to-transparent"></div>
    <div class="relative z-10 text-center max-w-3xl mx-auto px-6">
        <h2 class="text-4xl md:text-5xl font-bold text-[#F5F0E8] mb-6">
            Prêt à redonner vie<br>à vos souvenirs ?
        </h2>
        @auth
        <p class="text-[#7A6E5E] mb-10">Déposez vos prochaines photos directement depuis votre espace client.</p>
        <a href="{{ route('client.orders.create') }}" class="btn-gold glow-gold text-base px-12 py-5">
            Nouvelle commande
        </a>
        @else
        <p class="text-[#7A6E5E] mb-10">Créez votre compte gratuitement. Pas d'abonnement, pas d'engagement.</p>
        <a href="{{ route('register') }}" class="btn-gold glow-gold text-base px-12 py-5">
            Commencer — c'est gratuit
        </a>
        @endauth
    </div>
</section>

{{-- ========== FOOTER ========== --}}
<footer class="mt-16">

    <div class="h-px bg-gradient-to-r from-transparent via-[#C9A84C]/30 to-transparent"></div>

    <div class="bg-[#0A0804] pt-12 pb-6">
        <div class="max-w-6xl mx-auto px-6">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-10 border-b border-[#C9A84C]/8">

                {{-- Colonne 1 : Marque --}}
                <div class="md:col-span-1 space-y-4">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                        <div class="w-8 h-8 border border-[#C9A84C] flex items-center justify-center group-hover:bg-[#C9A84C]/10 transition-colors">
                            <span class="text-[#C9A84C] text-[9px] font-bold tracking-widest">OR</span>
                        </div>
                        <span class="text-[#F5F0E8] font-semibold tracking-[0.12em] text-sm uppercase">OmnyRestore</span>
                    </a>
                    <p class="text-[#7A6E5E] text-xs leading-relaxed">
                        Restauration de photographies anciennes par intelligence artificielle.
                        Voyez le résultat <em>avant</em> de payer.
                    </p>
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
                            <a href="{{ route('home') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Accueil
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('login') }}" wire:navigate class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Se connecter
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('register') }}" wire:navigate class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Créer un compte
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Colonne 3 : Légal --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-4 font-semibold">Informations légales</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ route('legal.mentions') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Mentions légales
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('legal.privacy') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Politique de confidentialité
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('legal.cgv') }}" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <span class="w-1 h-1 rounded-full bg-[#C9A84C]/40 shrink-0"></span>Conditions Générales de Vente
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Colonne 4 : Contact --}}
                <div>
                    <h4 class="text-[#C9A84C] text-[10px] tracking-widest uppercase mb-4 font-semibold">Contact</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="mailto:contact@omnyrestore.fr" class="text-[#7A6E5E] text-xs hover:text-[#F5F0E8] transition-colors flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-[#C9A84C]/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                contact@omnyrestore.fr
                            </a>
                        </li>
                        <li class="pt-1">
                            <p class="text-[#7A6E5E] text-[11px] leading-relaxed">
                                Réponse sous 24–48h ouvrées.<br>
                                Du lundi au vendredi.
                            </p>
                        </li>
                    </ul>
                </div>

            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-[#7A6E5E] text-[11px]">
                    © {{ date('Y') }} <span class="text-[#C9A84C]/70">OmnyRestore</span> — une branche d'<span class="text-[#C9A84C]/70">OmnyVia</span> · Alain GUILLON
                </p>
                <p class="text-[#7A6E5E] text-[11px] flex items-center gap-1.5">
                    Conçu et hébergé en France <span class="text-base leading-none">🇫🇷</span>
                </p>
            </div>

        </div>
    </div>
</footer>

{{-- Alpine.js est fourni par @livewireScripts (Livewire 3) --}}
@livewireScripts
</body>
</html>
