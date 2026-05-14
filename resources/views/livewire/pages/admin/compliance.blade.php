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

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8]">Ressources de Conformité</h1>
        <p class="text-[#7A6E5E] text-sm mt-1">Rappels essentiels sur la gestion des données et la sécurité.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
        
        {{-- BLOC RGPD (Or) --}}
        <div class="card-glass p-6 h-full flex flex-col">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-[#C9A84C]/10 flex items-center justify-center border border-[#C9A84C]/30 text-[#C9A84C] shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-[#F5F0E8] leading-tight">Règlement Général sur la Protection des Données</h2>
                    <p class="text-[#C9A84C] text-xs">RGPD — UE 2016/679</p>
                </div>
            </div>

            <div class="space-y-3 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                <div class="p-3 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                    <h3 class="font-bold text-[#C9A84C] mb-1 flex items-baseline justify-between">
                        1. Minimisation des données
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 5(1)c</span>
                    </h3>
                    <p class="text-xs">Ne collectez et ne conservez que les informations strictement nécessaires pour la restauration de l'image (email, nom, photo originale).</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                    <h3 class="font-bold text-[#C9A84C] mb-1 flex items-baseline justify-between">
                        2. Droit à l'oubli
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 17</span>
                    </h3>
                    <p class="text-xs">Sauf obligation comptable, les photos doivent être purgées des serveurs après livraison et expiration du délai de rétractation/support.</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-[#C9A84C]/20 rounded-sm">
                    <h3 class="font-bold text-[#C9A84C] mb-1 flex items-baseline justify-between">
                        3. Consentement & Confidentialité
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 7</span>
                    </h3>
                    <p class="text-xs">Les photographies anciennes sont souvent sensibles. Ne les publiez jamais (avant/après) sans consentement écrit explicite (Opt-in).</p>
                </div>
            </div>
        </div>

        {{-- BLOC NIS2 (Bleu) --}}
        <div class="card-glass p-6 h-full flex flex-col border-blue-500/10">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-blue-900/20 flex items-center justify-center border border-blue-500/30 text-blue-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-[#F5F0E8] leading-tight">Directive Cybersécurité</h2>
                    <p class="text-blue-400 text-xs">NIS 2 — UE 2022/2555</p>
                </div>
            </div>

            <div class="space-y-3 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                <div class="p-3 bg-[#0F0C08] border border-blue-500/20 rounded-sm">
                    <h3 class="font-bold text-blue-400 mb-1 flex items-baseline justify-between">
                        1. Gestion des risques
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 21</span>
                    </h3>
                    <p class="text-xs">L'accès à l'administration doit être strictement restreint (MFA). Renouvelez régulièrement vos secrets (ex: clés API Stripe, DB credentials).</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-blue-500/20 rounded-sm">
                    <h3 class="font-bold text-blue-400 mb-1 flex items-baseline justify-between">
                        2. Notification d'incidents
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 23</span>
                    </h3>
                    <p class="text-xs">En cas de fuite de données ou d'intrusion, la notification aux autorités (ANSSI) doit être effectuée dans les 24h (alerte précoce).</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-blue-500/20 rounded-sm">
                    <h3 class="font-bold text-blue-400 mb-1 flex items-baseline justify-between">
                        3. Continuité d'activité
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 21(2)c</span>
                    </h3>
                    <p class="text-xs">La politique de sauvegarde (database + fichiers locaux/S3) doit être opérationnelle, stockée à froid, et testée régulièrement.</p>
                </div>
            </div>
        </div>

        {{-- BLOC Loi Godfrain (Rouge) --}}
        <div class="card-glass p-6 h-full flex flex-col border-red-500/10">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-red-900/20 flex items-center justify-center border border-red-500/30 text-red-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-[#F5F0E8] leading-tight">Loi sur la Cybercriminalité</h2>
                    <p class="text-red-400 text-xs">Loi Godfrain — Code Pénal FR</p>
                </div>
            </div>

            <div class="space-y-3 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                <div class="p-3 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                    <h3 class="font-bold text-red-400 mb-1 flex items-baseline justify-between">
                        1. Accès frauduleux
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 323-1</span>
                    </h3>
                    <p class="text-xs">L'accès ou le maintien frauduleux dans tout ou partie d'un Système de Traitement Automatisé de Données (STAD) est puni par la loi.</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                    <h3 class="font-bold text-red-400 mb-1 flex items-baseline justify-between">
                        2. Entrave au fonctionnement
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 323-2</span>
                    </h3>
                    <p class="text-xs">Le fait d'entraver ou de fausser le fonctionnement d'un STAD (ex: attaques DDoS) est pénalement répréhensible.</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-red-500/20 rounded-sm">
                    <h3 class="font-bold text-red-400 mb-1 flex items-baseline justify-between">
                        3. Modification de données
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Art. 323-3</span>
                    </h3>
                    <p class="text-xs">L'introduction, la modification ou la suppression frauduleuse de données dans le système d'OmnyRestore est un délit pénal.</p>
                </div>
            </div>
        </div>

        {{-- BLOC ISO 27001 (Violet) --}}
        <div class="card-glass p-6 h-full flex flex-col border-purple-500/10">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-purple-900/20 flex items-center justify-center border border-purple-500/30 text-purple-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-[#F5F0E8] leading-tight">Sécurité de l'Information</h2>
                    <p class="text-purple-400 text-xs">Bonnes pratiques — ISO/IEC 27001</p>
                </div>
            </div>

            <div class="space-y-3 text-sm text-[#F5F0E8]/80 leading-relaxed flex-1">
                <div class="p-3 bg-[#0F0C08] border border-purple-500/20 rounded-sm">
                    <h3 class="font-bold text-purple-400 mb-1 flex items-baseline justify-between">
                        1. Cycle PDCA
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Chap. 4-10</span>
                    </h3>
                    <p class="text-xs">Application du cycle Plan-Do-Check-Act pour l'amélioration continue du SMSI (Système de Management de la Sécurité de l'Information).</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-purple-500/20 rounded-sm">
                    <h3 class="font-bold text-purple-400 mb-1 flex items-baseline justify-between">
                        2. Classification de l'info
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Annexe A.8</span>
                    </h3>
                    <p class="text-xs">Les photographies clients et données bancaires sont classifiées comme critiques et nécessitent un chiffrement au repos et en transit.</p>
                </div>

                <div class="p-3 bg-[#0F0C08] border border-purple-500/20 rounded-sm">
                    <h3 class="font-bold text-purple-400 mb-1 flex items-baseline justify-between">
                        3. Sécurité des communications
                        <span class="text-[#7A6E5E] font-mono text-[10px] font-normal">Annexe A.13</span>
                    </h3>
                    <p class="text-xs">Protection rigoureuse des échanges réseau (TLS 1.3) pour prévenir l'interception des données transmises par les utilisateurs.</p>
                </div>
            </div>
        </div>

    </div>

    {{-- Call to action --}}
    <div class="mt-8 text-center">
        <p class="text-[#7A6E5E] text-xs max-w-2xl mx-auto mb-4">La conformité est l'affaire de tous. Une erreur de manipulation sur les données personnelles peut engager la responsabilité civile et pénale de l'entreprise.</p>
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn-gold inline-flex items-center text-sm px-6">
            Retour au Panel Admin
        </a>
    </div>
</div>
