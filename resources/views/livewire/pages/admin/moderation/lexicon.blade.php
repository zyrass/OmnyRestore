<?php

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Lexique de Modération IA — OmnyRestore');

?>

<div class="py-12 px-6 max-w-7xl mx-auto min-h-screen">
    {{-- Header avec effet de texte --}}
    <div class="relative mb-16">
        <div class="absolute -top-10 -left-10 w-64 h-64 bg-red-600/10 rounded-full blur-[100px]"></div>
        <div class="relative z-10">
            <h1 class="text-5xl font-extrabold tracking-tighter text-[#F5F0E8] leading-none mb-4">
                Lexique <span class="bg-gradient-to-r from-red-500 to-orange-400 bg-clip-text text-transparent">Modération IA</span>
            </h1>
            <p class="text-[#7A6E5E] text-lg max-w-2xl border-l-2 border-[#C9A84C]/30 pl-6 py-1">
                Comprendre les critères de détection d'OpenAI pour garantir la sécurité et la conformité légale d'OmnyRestore.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {{-- Section Principale : Les Catégories --}}
        <div class="lg:col-span-2 space-y-8">
            
            {{-- Introduction --}}
            <div class="card-glass p-8 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-red-500/5 rounded-full -mr-10 -mt-10 blur-3xl transition-all group-hover:bg-red-500/10"></div>
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center shrink-0 border border-red-500/20">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-[#F5F0E8] mb-2">Analyse Multimodale OpenAI</h2>
                        <p class="text-[#7A6E5E] text-sm leading-relaxed">
                            Chaque média téléchargé est soumis au moteur <code class="text-red-400 font-mono">omni-moderation-latest</code>. 
                            Ce processus asynchrone garantit que nous ne traitons aucun contenu violant nos conditions générales d'utilisation (CGU).
                        </p>
                    </div>
                </div>
            </div>

            {{-- Grille des Flags --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {{-- Sexual --}}
                <div class="card-glass p-6 border-l-4 border-l-pink-600/50 hover:bg-white/5 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] uppercase text-pink-500/60">Protection Intégrité</span>
                        <div class="p-1.5 bg-pink-500/10 rounded-lg"><svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg></div>
                    </div>
                    <h3 class="text-lg font-bold text-[#F5F0E8] mb-4">Contenu Sexuel</h3>
                    <div class="space-y-4">
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold">sexual</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Pornographie, actes explicites, nudité non artistique.</p>
                        </div>
                        <div class="p-3 bg-red-950/20 rounded border border-red-500/30">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-red-500 font-mono text-xs font-bold">sexual/minors (CSAM)</span>
                                <span class="text-[9px] bg-red-600 text-white px-1.5 rounded-full font-bold animate-pulse">CRITIQUE</span>
                            </div>
                            <p class="text-[11px] text-red-200/70 italic">Pédocriminalité. Signalement PHAROS obligatoire sans délai.</p>
                        </div>
                    </div>
                </div>

                {{-- Hate --}}
                <div class="card-glass p-6 border-l-4 border-l-orange-600/50 hover:bg-white/5 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] uppercase text-orange-500/60">Respect & Éthique</span>
                        <div class="p-1.5 bg-orange-500/10 rounded-lg"><svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
                    </div>
                    <h3 class="text-lg font-bold text-[#F5F0E8] mb-4">Haine & Harcèlement</h3>
                    <div class="space-y-4">
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">hate / hate/threatening</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Discours haineux ciblant une identité ou menaces de violence physique.</p>
                        </div>
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">harassment / harassment/threatening</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Contenu abusif ou insultant destiné à l'intimidation d'un individu.</p>
                        </div>
                    </div>
                </div>

                {{-- Violence --}}
                <div class="card-glass p-6 border-l-4 border-l-red-600/50 hover:bg-white/5 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] uppercase text-red-500/60">Sûreté Publique</span>
                        <div class="p-1.5 bg-red-500/10 rounded-lg"><svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                    </div>
                    <h3 class="text-lg font-bold text-[#F5F0E8] mb-4">Violence & Gore</h3>
                    <div class="space-y-4">
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">violence / graphic</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Promotion de la violence, torture ou images gores et choquantes de blessures réelles.</p>
                        </div>
                    </div>
                </div>

                {{-- Illicit --}}
                <div class="card-glass p-6 border-l-4 border-l-purple-600/50 hover:bg-white/5 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] uppercase text-purple-500/60">Légalité</span>
                        <div class="p-1.5 bg-purple-500/10 rounded-lg"><svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg></div>
                    </div>
                    <h3 class="text-lg font-bold text-[#F5F0E8] mb-4">Activités Illicites</h3>
                    <div class="space-y-4">
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">illicit / violent</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Vente de drogues, trafic d'armes ou instructions pour créer des explosifs.</p>
                        </div>
                    </div>
                </div>

                {{-- Self Harm --}}
                <div class="card-glass p-6 border-l-4 border-l-blue-600/50 hover:bg-white/5 transition-all md:col-span-2">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] uppercase text-blue-500/60">Soutien Psychologique</span>
                        <div class="p-1.5 bg-blue-500/10 rounded-lg"><svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg></div>
                    </div>
                    <h3 class="text-lg font-bold text-[#F5F0E8] mb-4">Automutilation & Suicide</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">self-harm / intent</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Expression d'intentions de s'automutiler ou de se suicider.</p>
                        </div>
                        <div class="p-3 bg-black/20 rounded border border-white/5">
                            <span class="text-red-400 font-mono text-xs font-bold italic">self-harm / instructions</span>
                            <p class="text-[11px] text-[#7A6E5E] mt-1">Méthodes détaillées sur comment s'automutiler ou mettre fin à ses jours.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar : Actions & Légal --}}
        <div class="space-y-8">
            
            {{-- Actions --}}
            <div class="card-glass p-8 border border-[#C9A84C]/20 relative">
                <div class="absolute top-0 right-0 w-16 h-16 bg-[#C9A84C]/5 rounded-bl-[100px]"></div>
                <h3 class="text-[#C9A84C] font-bold text-xs uppercase tracking-widest mb-6">Workflow de Crise</h3>
                
                <div class="space-y-6 relative">
                    <div class="absolute left-[15px] top-2 bottom-2 w-0.5 bg-[#C9A84C]/10"></div>
                    
                    <div class="flex gap-4 relative">
                        <div class="w-8 h-8 rounded-full bg-[#1A1510] border border-[#C9A84C]/30 flex items-center justify-center shrink-0 z-10 text-[10px] font-bold text-[#C9A84C]">1</div>
                        <div>
                            <h4 class="text-[#F5F0E8] text-xs font-bold mb-1">Analyse Humaine</h4>
                            <p class="text-[10px] text-[#7A6E5E]">L'admin vérifie les images (floutées) pour confirmer ou infirmer le flag IA.</p>
                        </div>
                    </div>

                    <div class="flex gap-4 relative">
                        <div class="w-8 h-8 rounded-full bg-[#1A1510] border border-[#C9A84C]/30 flex items-center justify-center shrink-0 z-10 text-[10px] font-bold text-[#C9A84C]">2</div>
                        <div>
                            <h4 class="text-[#F5F0E8] text-xs font-bold mb-1">Traitement</h4>
                            <p class="text-[10px] text-[#7A6E5E]">Restauration du statut (Faux positif) ou activation de la procédure de destruction.</p>
                        </div>
                    </div>

                    <div class="flex gap-4 relative">
                        <div class="w-8 h-8 rounded-full bg-red-600 border border-red-500 flex items-center justify-center shrink-0 z-10 text-[10px] font-bold text-white shadow-[0_0_15px_rgba(220,38,38,0.4)]">3</div>
                        <div>
                            <h4 class="text-red-400 text-xs font-bold mb-1">Signalement</h4>
                            <p class="text-[10px] text-[#7A6E5E]">Génération automatique du rapport PHAROS et transmission aux autorités.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-white/5">
                    <div class="flex items-center gap-3 text-red-500 text-[10px] font-bold tracking-widest uppercase">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Urgence Immédiate
                    </div>
                    <p class="text-[10px] text-[#7A6E5E] mt-2 italic">
                        Toute détection de <strong>CSAM</strong> entraîne la fermeture immédiate du compte et la conservation de l'IP pour enquête.
                    </p>
                </div>
            </div>

            {{-- Légal --}}
            <div class="p-8 rounded-sm bg-black/40 border border-white/5">
                <h4 class="text-[#F5F0E8] text-[11px] font-bold uppercase tracking-widest mb-4">Base Légale (LCEN)</h4>
                <p class="text-[11px] text-[#7A6E5E] leading-relaxed mb-4">
                    Conformément à l'article 6 de la Loi n° 2004-575 (LCEN), OmnyRestore est tenu de concourir à la lutte contre la diffusion des infractions visées aux troisième et quatrième alinéas de l'article 24 de la loi du 29 juillet 1881.
                </p>
                <div class="text-[9px] text-[#7A6E5E] uppercase tracking-widest py-2 px-3 border border-white/5 rounded inline-block">
                    Capture IP Active : {{ request()->ip() }}
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .card-glass {
        background: rgba(26, 21, 16, 0.4);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(201, 168, 76, 0.1);
        border-radius: 4px;
    }
    .text-gold-glow {
        text-shadow: 0 0 20px rgba(220, 38, 38, 0.3);
    }
</style>
