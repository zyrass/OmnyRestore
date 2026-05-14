<?php

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Lexique de Modération IA — OmnyRestore');

?>

<div class="py-10 px-6 max-w-7xl mx-auto">
    <div class="mb-10 border-b border-[#C9A84C]/10 pb-8">
        <h1 class="text-4xl font-bold text-[#F5F0E8] tracking-tight">Lexique <span class="text-red-500 text-gold-glow">Modération IA</span></h1>
        <p class="text-[#7A6E5E] mt-2">Guide de référence des catégories de détection automatique d'OpenAI.</p>
    </div>

    <div class="bg-red-900/5 border border-red-900/20 p-8 rounded-sm">
        <h3 class="text-[#F5F0E8] font-bold mb-6 flex items-center gap-2">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Classification des Contenus (OpenAI Moderation API)
        </h3>
        
        <p class="text-sm text-[#7A6E5E] mb-8 leading-relaxed">
            OmnyRestore utilise le modèle <code class="text-red-400">omni-moderation-latest</code> pour analyser chaque image téléchargée. 
            Lorsqu'un contenu dépasse les seuils de confiance de l'IA, la commande est automatiquement suspendue au statut <span class="text-red-400 font-bold">FLAGGED</span>.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Catégorie Sexuelle --}}
            <div class="p-6 bg-black/40 border border-red-500/10 rounded-sm hover:border-red-500/30 transition-all">
                <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-pink-500 shadow-[0_0_8px_rgba(236,72,153,0.5)]"></span>
                    Contenu Sexuel
                </h4>
                <ul class="space-y-4">
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">sexual</strong>
                        Pornographie, actes sexuels ou contenu sexuellement explicite. 
                        <span class="block mt-1 text-[11px] italic text-red-400/60">Politique : Tolérance Zéro (Action: Bannissement & Destruction).</span>
                    </li>
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-500 block mb-1">sexual/minors (CSAM) 🚨</strong>
                        Images impliquant des mineurs dans un contexte sexuel (Pédocriminalité). 
                        <span class="block mt-1 text-[11px] font-bold text-red-500 bg-red-900/10 p-2 border border-red-500/20 rounded-sm">
                            ACTION OBLIGATOIRE : Rapport PHAROS immédiat + Coopération avec les autorités.
                        </span>
                    </li>
                </ul>
            </div>

            {{-- Catégorie Haine & Harcèlement --}}
            <div class="p-6 bg-black/40 border border-red-500/10 rounded-sm hover:border-red-500/30 transition-all">
                <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.5)]"></span>
                    Haine & Harcèlement
                </h4>
                <ul class="space-y-4">
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">hate / hate/threatening</strong>
                        Contenu promouvant la haine basée sur la race, la religion, l'orientation sexuelle, le handicap ou l'origine ethnique. Inclut les menaces physiques.
                    </li>
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">harassment / harassment/threatening</strong>
                        Contenu abusif, insultant ou menaçant destiné à intimider ou harceler une personne ou un groupe.
                    </li>
                </ul>
            </div>

            {{-- Catégorie Violence --}}
            <div class="p-6 bg-black/40 border border-red-500/10 rounded-sm hover:border-red-500/30 transition-all">
                <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-600 shadow-[0_0_8px_rgba(220,38,38,0.5)]"></span>
                    Violence & Gore
                </h4>
                <ul class="space-y-4">
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">violence / violence/graphic</strong>
                        Promotion de la violence, de la torture, ou images macabres, gores et choquantes de blessures réelles.
                    </li>
                </ul>
            </div>

            {{-- Catégorie Activités Illicites --}}
            <div class="p-6 bg-black/40 border border-red-500/10 rounded-sm hover:border-red-500/30 transition-all">
                <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.5)]"></span>
                    Activités Illicites
                </h4>
                <ul class="space-y-4">
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">illicit / illicit/violent</strong>
                        Promotion d'activités criminelles, trafic de drogues, ou instructions pour la fabrication d'armes et d'explosifs.
                    </li>
                </ul>
            </div>

            {{-- Catégorie Automutilation --}}
            <div class="p-6 bg-black/40 border border-red-500/10 rounded-sm hover:border-red-500/30 transition-all">
                <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>
                    Automutilation
                </h4>
                <ul class="space-y-4">
                    <li class="text-sm text-[#7A6E5E]">
                        <strong class="text-red-400 block mb-1">self-harm / intent / instructions</strong>
                        Promotion, encouragement ou instructions détaillées concernant le suicide ou l'automutilation délibérée.
                    </li>
                </ul>
            </div>

            {{-- Actions Administrateur --}}
            <div class="p-6 bg-[#C9A84C]/5 border border-[#C9A84C]/20 rounded-sm">
                <h4 class="text-[#C9A84C] font-bold text-xs uppercase tracking-widest mb-4">Actions de l'Administrateur</h4>
                <p class="text-xs text-[#7A6E5E] leading-relaxed">
                    Lorsqu'une commande est <span class="text-[#F5F0E8] font-bold">FLAGGED</span>, vous avez trois options :
                </p>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center gap-2 text-[11px] text-[#7A6E5E]">
                        <span class="w-1 h-1 rounded-full bg-[#C9A84C]"></span>
                        <strong class="text-[#F5F0E8]">Faux Positif</strong> : Remise en production.
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-[#7A6E5E]">
                        <span class="w-1 h-1 rounded-full bg-[#C9A84C]"></span>
                        <strong class="text-[#F5F0E8]">Bannir & Détruire</strong> : Suppression des fichiers et du compte.
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-[#7A6E5E]">
                        <span class="w-1 h-1 rounded-full bg-[#C9A84C]"></span>
                        <strong class="text-[#F5F0E8]">Rapport PHAROS</strong> : Signalement aux autorités (obligatoire pour CSAM).
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 p-5 bg-black/40 border border-white/5 rounded-sm">
            <h5 class="text-[#F5F0E8] text-xs font-bold uppercase tracking-widest mb-2">Responsabilité du Dirigeant</h5>
            <p class="text-[11px] text-[#7A6E5E] leading-relaxed">
                Conformément à la Loi pour la Confiance dans l'Économie Numérique (LCEN), l'hébergeur a l'obligation de retirer promptement tout contenu illicite dont il a connaissance. La capture systématique de l'IP lors de la commande permet de répondre aux réquisitions judiciaires.
            </p>
        </div>
    </div>
</div>
