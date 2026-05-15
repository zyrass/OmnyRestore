<?php
/**
 * Admin — Ressources de conformité (RGPD, NIS2)
 * Route: GET /admin/compliance
 */

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Ressources Conformité — Admin')]
class extends Component
{
    // Composant purement statique / informatif
}; ?>

<div x-data="{ tab: 'legal' }" class="pb-12">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8]">Pilotage & Conformité</h1>
        <p class="text-[#7A6E5E] text-sm mt-1">Cadre réglementaire, gouvernance de la sécurité et stratégie du système d'information.</p>
    </div>

    {{-- Barre d'onglets --}}
    <div class="flex flex-wrap items-center gap-3 mb-10 border-b border-[#C9A84C]/10 pb-6">
        <button 
            @click="tab = 'legal'" 
            :class="tab === 'legal' ? 'bg-[#C9A84C] text-[#0D0B08]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] bg-[#0F0C08] border border-[#C9A84C]/20'"
            class="px-8 py-4 rounded-sm text-sm font-black transition-all duration-300 uppercase tracking-[0.2em] flex items-center gap-2"
        >
            ⚖️ <span class="hidden sm:inline">Conformité</span> Légale
        </button>
        <button 
            @click="tab = 'security'" 
            :class="tab === 'security' ? 'bg-[#3B82F6] text-white shadow-[0_0_15px_rgba(59,130,246,0.3)]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] bg-[#0F0C08] border border-[#3B82F6]/20'"
            class="px-8 py-4 rounded-sm text-sm font-black transition-all duration-300 uppercase tracking-[0.2em] flex items-center gap-2"
        >
            🛡️ Sécurité <span class="hidden sm:inline">& Normes</span>
        </button>
        <button 
            @click="tab = 'strategy'" 
            :class="tab === 'strategy' ? 'bg-[#10B981] text-white shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] bg-[#0F0C08] border border-[#10B981]/20'"
            class="px-8 py-4 rounded-sm text-sm font-black transition-all duration-300 uppercase tracking-[0.2em] flex items-center gap-2"
        >
            🚀 Stratégie SI
        </button>
    </div>

    {{-- CONTENU : CONFORMITÉ LÉGALE --}}
    <div x-show="tab === 'legal'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="mb-10 p-5 bg-[#C9A84C]/5 border-l-4 border-[#C9A84C] text-sm text-[#F5F0E8]/80 leading-relaxed italic">
            <p><strong>Cadre Juridique Impératif :</strong> OmnyRestore opère dans un écosystème hautement régulé. Cette section détaille les piliers de notre conformité, garantissant la protection des droits fondamentaux de nos utilisateurs et la sécurité juridique de la plateforme face aux autorités compétentes (CNIL, DGCCRF).</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- BLOC RGPD --}}
            <div class="card-glass p-8 h-full flex flex-col border-[#C9A84C]/20">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-full bg-[#C9A84C]/10 flex items-center justify-center border border-[#C9A84C]/30 text-[#C9A84C] shrink-0 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Protection des Données</h2>
                        <p class="text-[#C9A84C] text-xs font-mono mt-1 tracking-[0.2em] uppercase">RGPD — RÈGLEMENT UE 2016/679</p>
                    </div>
                </div>
                <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                    <div class="p-4 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                        <h3 class="font-bold text-[#C9A84C] mb-2 flex items-baseline justify-between text-base">Minimisation <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 5.1.c</span></h3>
                        <p class="text-xs leading-relaxed">Nous appliquons le principe de "Privacy by Default". Seules les données strictement nécessaires à l'algorithme de restauration (Email, Metadata Image) sont collectées et traitées.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                        <h3 class="font-bold text-[#C9A84C] mb-2 flex items-baseline justify-between text-base">Conservation <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 5.1.e</span></h3>
                        <p class="text-xs leading-relaxed">La durée de vie des actifs numériques est limitée. Toutes les photos restaurées sont purgées de façon immuable 6 mois après leur livraison, sauf exercice explicite du droit à l'oubli par le client.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                        <h3 class="font-bold text-[#C9A84C] mb-2 flex items-baseline justify-between text-base">Sécurité <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 32</span></h3>
                        <p class="text-xs leading-relaxed">Mise en œuvre de mesures techniques et organisationnelles appropriées (chiffrement AES-256) pour garantir un niveau de sécurité adapté au risque de violation des données personnelles.</p>
                    </div>
                </div>
            </div>

            {{-- BLOC Loi Godfrain --}}
            <div class="card-glass p-8 h-full flex flex-col border-red-500/20">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-full bg-red-900/20 flex items-center justify-center border border-red-500/30 text-red-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Cybercriminalité</h2>
                        <p class="text-red-400 text-xs font-mono mt-1 tracking-[0.2em] uppercase">LOI GODFRAIN — CODE PÉNAL FR</p>
                    </div>
                </div>
                <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                    <div class="p-4 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                        <h3 class="font-bold text-red-400 mb-2 flex items-baseline justify-between text-base">Accès Frauduleux <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 323-1</span></h3>
                        <p class="text-xs leading-relaxed">L'intrusion ou le maintien illicite dans le Système de Traitement Automatisé de Données (STAD) d'OmnyRestore est passible de 3 ans d'emprisonnement et 100 000€ d'amende.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                        <h3 class="font-bold text-red-400 mb-2 flex items-baseline justify-between text-base">Altération <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 323-3</span></h3>
                        <p class="text-xs leading-relaxed">Toute suppression, modification ou extraction frauduleuse de données issues de notre infrastructure constitue une infraction pénale majeure, poursuivie systématiquement par nos services juridiques.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                        <h3 class="font-bold text-red-400 mb-2 flex items-baseline justify-between text-base">Entrave <span class="text-[#7A6E5E] font-mono text-[10px] font-normal tracking-normal uppercase">Art. 323-2</span></h3>
                        <p class="text-xs leading-relaxed">L'entrave ou la dégradation du fonctionnement normal de nos serveurs (via des attaques de type DoS/DDoS) est un délit spécifique dont les peines sont aggravées en cas de dommage systémique.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CONTENU : SÉCURITÉ & NORMES --}}
    <div x-show="tab === 'security'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="mb-10 p-5 bg-blue-500/5 border-l-4 border-blue-500 text-sm text-[#F5F0E8]/80 leading-relaxed italic">
            <p><strong>Standards de Résilience :</strong> OmnyRestore applique les normes les plus exigeantes du marché pour garantir une infrastructure inébranlable. Ce volet technique fusionne les directives européennes (NIS2) et les cadres de management de la sécurité (ISO 27001).</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- BLOC NIS2 --}}
            <div class="card-glass p-8 h-full flex flex-col border-blue-500/20">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-full bg-blue-900/20 flex items-center justify-center border border-blue-500/30 text-blue-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Cyber-Directives</h2>
                        <p class="text-blue-400 text-xs font-mono mt-1 tracking-[0.2em] uppercase">Directive NIS 2 — UE 2022/2555</p>
                    </div>
                </div>
                <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                    <div class="p-4 bg-[#0F0C08] border border-blue-500/20 rounded-sm relative">
                        <span class="absolute top-2 right-4 text-[#7A6E5E] font-mono text-[9px] uppercase">Art. 21</span>
                        <h3 class="font-bold text-blue-400 mb-2 text-base">Gestion des Risques</h3>
                        <p class="text-xs leading-relaxed">Imposition de l'authentification multifacteur (MFA) pour tous les accès privilégiés, chiffrement des communications bout-en-bout et surveillance proactive des points d'entrée API.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-blue-500/20 rounded-sm relative">
                        <span class="absolute top-2 right-4 text-[#7A6E5E] font-mono text-[9px] uppercase">Art. 23</span>
                        <h3 class="font-bold text-blue-400 mb-2 text-base">Signalement Incidents</h3>
                        <p class="text-xs leading-relaxed">Mise en place d'une cellule de réponse aux incidents capable de notifier l'ANSSI et les parties prenantes dans un délai impératif de 24h à 72h en cas de menace avérée ou d'intrusion.</p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-blue-500/20 rounded-sm relative">
                        <span class="absolute top-2 right-4 text-[#7A6E5E] font-mono text-[9px] uppercase">Art. 21.2</span>
                        <h3 class="font-bold text-blue-400 mb-2 text-base">Chaîne de Valeur</h3>
                        <p class="text-xs leading-relaxed">Audit de sécurité rigoureux de nos fournisseurs (hébergeurs, processeurs de paiement) pour garantir qu'aucune faille tierce ne puisse compromettre l'écosystème OmnyRestore.</p>
                    </div>
                </div>
            </div>

            {{-- BLOC ISO 27001 --}}
            <div class="card-glass p-8 h-full flex flex-col border-purple-500/20">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-full bg-purple-900/20 flex items-center justify-center border border-purple-500/30 text-purple-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Management Sécurité</h2>
                        <p class="text-purple-400 text-xs font-mono mt-1 tracking-[0.2em] uppercase">ISO/IEC 27001:2022</p>
                    </div>
                </div>
                <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                    <div class="p-4 bg-[#0F0C08] border border-purple-500/20 rounded-sm">
                        <h3 class="font-bold text-purple-400 mb-2 text-base">Culture PDCA</h3>
                        <p class="text-xs leading-relaxed">Adoption du cycle "Plan-Do-Check-Act" : chaque vulnérabilité détectée alimente un plan d'action correctif immédiat, garantissant une évolution constante de nos défenses.</p>
                    </div>
                    <div class="p-5 bg-purple-900/10 border border-purple-500/40 rounded-sm shadow-lg">
                        <h3 class="font-black text-purple-400 mb-2 text-[11px] flex items-center gap-2 uppercase tracking-widest">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36-2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            Condition d'usage Normatif
                        </h3>
                        <p class="text-xs text-purple-200 leading-relaxed font-medium italic">
                            L'application opérationnelle stricte de cette norme et son audit officiel nécessitent l'acquisition préalable du référentiel complet auprès de l'Organisation Internationale de Normalisation (ISO).
                        </p>
                    </div>
                    <div class="p-4 bg-[#0F0C08] border border-purple-500/20 rounded-sm">
                        <h3 class="font-bold text-purple-400 mb-2 text-base">Contrôles de l'Annexe A</h3>
                        <p class="text-xs leading-relaxed">Mise en œuvre des 93 mesures de sécurité (Contrôles de l'Annexe A) couvrant les aspects organisationnels, humains, physiques et technologiques de la plateforme.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CONTENU : STRATÉGIE SI --}}
    <div x-show="tab === 'strategy'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="mb-10 p-5 bg-emerald-500/5 border-l-4 border-emerald-500 text-sm text-[#F5F0E8]/80 leading-relaxed italic">
            <p><strong>Pilotage Stratégique :</strong> Cette section définit la trajectoire technologique d'OmnyRestore à moyen et long terme. Le SDSI et la PSSI ne sont pas des documents statiques, mais des leviers de croissance permettant de supporter la montée en charge jusqu'à 10 000+ commandes/mois.</p>
        </div>

        <div class="flex flex-col gap-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
                {{-- BLOC SDSI --}}
                <div class="card-glass p-8 h-full flex flex-col border-emerald-500/20">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 rounded-full bg-emerald-900/20 flex items-center justify-center border border-emerald-500/30 text-emerald-400 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Schéma Directeur (SDSI)</h2>
                            <p class="text-emerald-400 text-xs font-mono mt-1 tracking-[0.2em] uppercase">Planification 24-36 Mois</p>
                        </div>
                    </div>
                    <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                        <div class="p-4 bg-[#0F0C08] border border-emerald-500/20 rounded-sm">
                            <h3 class="font-bold text-emerald-400 mb-2 italic text-base">Pré-requis essentiels</h3>
                            <p class="text-xs leading-relaxed">Le SDSI est l'aboutissement d'un processus strict : il exige une étude d'impact business **(BIA)** pour identifier les processus critiques et une **Analyse de Risque (EBIOS-RM)** pour quantifier les menaces pesant sur le patrimoine informationnel.</p>
                        </div>
                        <div class="p-5 border-l-4 border-emerald-500/50 bg-emerald-500/10 my-4 shadow-lg">
                            <p class="text-sm text-emerald-100 italic font-bold leading-relaxed">
                                "L'analyse d'impact (BIA) permet de définir les objectifs de temps de rétablissement (RTO) et de perte de données (RPO), piliers du SDSI."
                            </p>
                        </div>
                        <div class="p-4 bg-[#0F0C08] border border-emerald-500/20 rounded-sm">
                            <h3 class="font-bold text-emerald-400 mb-2 text-base">Alignement Stratégique</h3>
                            <p class="text-xs leading-relaxed">Garantit que chaque euro investi dans l'infrastructure IA ou le stockage S3 contribue directement à la rentabilité opérationnelle du SaaS et à la satisfaction client.</p>
                        </div>
                    </div>
                </div>

                {{-- BLOC PSSI --}}
                <div class="card-glass p-8 h-full flex flex-col border-cyan-500/20 bg-[#0A0805]">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 rounded-full bg-cyan-900/30 flex items-center justify-center border border-cyan-500/40 text-cyan-400 shrink-0 shadow-lg shadow-cyan-900/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 21a10.003 10.003 0 008.389-4.56l.054.09m-3.44 2.04C15.259 15.799 14.25 12.517 14.25 9V7.182a9.036 9.036 0 01-1.597-1.313L11.538 4.75L10.421 5.869a9.036 9.036 0 01-1.597 1.313V9c0 3.517-1.009 6.799-2.753 9.571"/></svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-[#F5F0E8] leading-tight tracking-tight uppercase">Politique Sécurité (PSSI)</h2>
                            <p class="text-cyan-400 text-xs font-mono mt-1 tracking-[0.2em] uppercase">Gouvernance & Opérations</p>
                        </div>
                    </div>
                    <div class="space-y-4 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                        <div class="p-4 bg-[#0F0C08] border border-cyan-500/40 rounded-sm">
                            <h3 class="font-bold text-cyan-400 mb-2 flex items-center gap-2 text-base">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36-2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                Déploiement Normatif
                            </h3>
                            <p class="text-xs text-cyan-200 leading-relaxed italic">La PSSI dérive directement des orientations stratégiques du SDSI. Elle traduit les besoins de protection en règles de configuration (Firewalls, RBAC, IDS/IPS).</p>
                        </div>
                        <div class="p-5 border-l-4 border-cyan-500/50 bg-cyan-500/10 my-4 shadow-lg">
                            <p class="text-sm text-cyan-100 italic font-bold leading-relaxed">
                                "Une PSSI efficace est celle qui est comprise par tous, de l'opérateur de saisie à l'administrateur système."
                            </p>
                        </div>
                        <div class="p-4 bg-[#0F0C08] border border-cyan-500/20 rounded-sm">
                            <h3 class="font-bold text-cyan-400 mb-2 text-base">Contrôle d'Accès (RBAC)</h3>
                            <p class="text-xs leading-relaxed">Application du principe de "Moindre Privilège" : chaque collaborateur n'accède qu'aux ressources indispensables à sa mission, avec une traçabilité totale des actions.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SMSI Fondamentaux (Refonte élégante) --}}
            <div class="mt-20 relative py-12 px-8 border-y border-purple-500/10 bg-gradient-to-r from-transparent via-purple-500/5 to-transparent">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-[#0D0B08] px-6 text-purple-400 font-black text-[9px] uppercase tracking-[0.4em] whitespace-nowrap">
                    Fondamentaux du Management de la Sécurité
                </div>
                <div class="flex flex-col items-center text-center max-w-4xl mx-auto">
                    <div class="mb-6 text-purple-500/20">
                        <svg class="w-12 h-12 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21L14.017 18C14.017 16.8954 14.9124 16 16.017 16H19.017C19.5693 16 20.017 15.5523 20.017 15V9C20.017 8.44772 19.5693 8 19.017 8H16.017C14.9124 8 14.017 7.10457 14.017 6V5C14.017 3.89543 14.9124 3 16.017 3H19.017C21.2261 3 23.017 4.79086 23.017 7V15C23.017 17.2091 21.2261 19 19.017 19H17.017L14.017 21ZM1 21L1 18C1 16.8954 1.89543 16 3 16H6C6.55228 16 7 15.5523 7 15V9C7 8.44772 6.55228 8 6 8H3C1.89543 8 1 7.10457 1 6V5C1 3.89543 1.89543 3 3 3H6C8.20914 3 10 4.79086 10 7V15C10 17.2091 8.20914 19 6 19H4L1 21Z"/></svg>
                    </div>
                    <blockquote class="text-[#F5F0E8] italic leading-[1.8] text-xl font-light tracking-tight">
                        "La base d'un <span class="text-purple-400 font-bold non-italic">SMSI</span> réussi repose sur le triptyque <span class="text-purple-400 font-bold non-italic">DIC</span> (ou <span class="text-purple-300 font-bold non-italic text-sm">CIA</span> en anglais : Confidentialité, Intégrité, Disponibilité) et l'application rigoureuse du cycle <span class="text-purple-400 font-bold non-italic">PDCA</span>. Ce cadre, ancré dans la norme <span class="text-purple-400 font-bold non-italic">ISO 27001</span>, assure la pérennité et la confiance numérique d'OmnyRestore."
                    </blockquote>
                </div>
            </div>

            {{-- Mermaid Sequence Diagram --}}
            <div class="mt-16 p-10 bg-[#0A0805] border border-[#C9A84C]/10 rounded-sm shadow-2xl">
                <h3 class="text-[#F5F0E8] text-sm font-black mb-12 uppercase tracking-[0.4em] text-center flex items-center justify-center gap-6">
                    <span class="h-px w-12 bg-gradient-to-r from-transparent via-[#C9A84C]/40 to-transparent"></span>
                    Cinématique de Gouvernance SI
                    <span class="h-px w-12 bg-gradient-to-l from-transparent via-[#C9A84C]/40 to-transparent"></span>
                </h3>
                
                <div class="mermaid flex justify-center overflow-x-auto py-6 min-h-[400px]">
                    sequenceDiagram
                        autonumber
                        participant M as Direction Métier
                        participant B as Analyse BIA
                        participant R as Risques EBIOS
                        participant S as Schéma SDSI
                        participant P as Politique PSSI
                        
                        M->>B: Identifier fonctions critiques
                        Note right of B: Analyse des impacts business
                        B->>R: Transférer DIC (Confidentialité...)
                        R->>R: Analyser menaces & vulnérabilités
                        R->>S: Proposer mesures de traitement
                        Note over S: Planification stratégique 24-36 mois
                        S->>P: Décliner en règles opérationnelles
                        P->>M: Gouvernance & Sécurité garantie
                </div>
            </div>
        </div>
    </div>

    {{-- Footer d'alerte --}}
    <div class="mt-16 p-8 bg-[#0F0C08] border border-[#C9A84C]/10 rounded-sm text-center shadow-inner">
        <p class="text-[#7A6E5E] text-xs max-w-2xl mx-auto mb-8 leading-relaxed italic">
            La conformité n'est pas une destination mais un voyage continu. Chaque membre de l'équipe OmnyRestore est garant de l'intégrité de ce cadre. 
            Une faille humaine peut suffire à compromettre l'ensemble du système de gouvernance.
        </p>
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn-gold inline-flex items-center text-xs px-12 py-4 tracking-[0.2em] font-black uppercase transition-all hover:scale-105 active:scale-95">
            Retour au Panel Admin
        </a>
    </div>
</div>

@push('scripts')
<script type="module">
    import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
    
    function initMermaid() {
        mermaid.initialize({ 
            startOnLoad: true, 
            theme: 'dark',
            themeVariables: {
                primaryColor: '#06b6d4',
                primaryTextColor: '#fff',
                primaryBorderColor: '#0891b2',
                lineColor: '#C9A84C',
                secondaryColor: '#10b981',
                tertiaryColor: '#0f172a',
                mainBkg: '#0F0C08',
                nodeBorder: '#C9A84C'
            }
        });
        mermaid.run();
    }

    // Initialisation au chargement
    initMermaid();

    // Ré-initialisation après navigation Livewire
    document.addEventListener('livewire:navigated', () => {
        initMermaid();
    });

    // Observer les changements d'onglets pour relancer Mermaid si nécessaire
    document.addEventListener('click', (e) => {
        if (e.target.closest('button')) {
            setTimeout(() => initMermaid(), 100);
        }
    });
</script>
@endpush

