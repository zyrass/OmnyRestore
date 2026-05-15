<?php

use function Livewire\Volt\{state, layout, title};
use App\Models\Order;
use App\Models\User;

layout('layouts.app');
title('Cellule de Crise — OmnyRestore');

state([
    'activeTab' => 'emergency',
    'lastBackup' => now()->subHours(4)->format('d/m/Y H:i'),
    'dpoContact' => [
        'name' => 'Alain GUILLON (DPO Interne)',
        'email' => 'dpo@omnyrestore.fr',
        'phone' => '+33 (0)6 XX XX XX XX'
    ],
    'emergencyContacts' => [
        ['label' => 'ANSSI (Incident Majeur)', 'value' => '01 71 21 01 21', 'type' => 'phone', 'logo' => '/images/anssi.png'],
        ['label' => 'CNIL (Notification)', 'value' => 'cnil.fr/notifier', 'type' => 'url', 'logo' => 'cnil'],
        ['label' => 'OVHcloud (Support VIP)', 'value' => '08 203 203 63', 'type' => 'phone', 'logo' => '/images/ovh.ico'],
    ],
    'commsTemplates' => [
        [
            'id' => 'data_leak',
            'title' => 'Notification de Violation de Données (RGPD)',
            'subject' => 'Information importante concernant la sécurité de vos données — OmnyRestore',
            'content' => "Madame, Monsieur,\n\nNous vous informons qu'un incident de sécurité a été détecté sur nos systèmes le [DATE]. Cet incident a pu entraîner un accès non autorisé à certaines de vos informations personnelles (Nom, Prénom, Email).\n\n**Ce que nous avons fait :**\n- Isolation immédiate des serveurs impactés.\n- Signalement à la CNIL effectué sous 72h.\n- Renforcement intégral de nos protocoles d'accès.\n\n**Ce que vous devez faire :**\nPar mesure de précaution, nous vous recommandons de modifier votre mot de passe sur notre plateforme ainsi que sur tout autre service où vous utiliseriez le même identifiant.\n\nNous regrettons sincèrement cet incident et restons à votre entière disposition pour toute question à l'adresse dpo@omnyrestore.fr.\n\nAlain GUILLON, Responsable de la protection des données."
        ],
        [
            'id' => 'maintenance_crisis',
            'title' => 'Interruption Majeure de Service',
            'subject' => 'Maintenance d\'urgence en cours — OmnyRestore',
            'content' => "Cher client,\n\nNotre plateforme OmnyRestore rencontre actuellement une interruption de service suite à un incident technique majeur chez notre hébergeur. \n\nNos équipes sont pleinement mobilisées pour rétablir l'accès dans les plus brefs délais. Soyez assuré que vos photos et vos données de paiement (gérées par Stripe) ne sont pas impactées par cet incident.\n\nNous vous tiendrons informé de la résolution de la situation via nos réseaux sociaux et par email.\n\nMerci de votre patience et de votre confiance.\n\nL'équipe OmnyRestore"
        ],
        [
            'id' => 'anssi_report',
            'title' => 'Rapport Préliminaire ANSSI / Police',
            'subject' => 'Dépôt de plainte / Signalement Incident Cyber',
            'content' => "OBJET : Signalement d'intrusion sur le système de traitement automatisé de données OmnyRestore.\n\nCONTEXTE :\n- Date de détection : [DATE/HEURE]\n- Vecteur suspecté : [Vecteur ex: Injection SQL / Vol de session]\n- Systèmes impactés : Base de données clients / Serveur de rendu IA\n\nMESURES DE PRÉSERVATION :\n- Gel des logs système (Disk image créée).\n- Blocage des adresses IP sources suspectes : [LISTE IP]\n- Liste des fichiers modifiés/accédés indûment : [LISTE FILES]\n\nContact technique : Alain GUILLON (+33 6 XX XX XX XX)"
        ]
    ],
    'isCrisisActive' => false,
    'crisisStartedAt' => null,
    'deadlineTimer' => 72 * 3600,
]);

$toggleCrisis = function() {
    $this->isCrisisActive = !$this->isCrisisActive;
    if ($this->isCrisisActive) {
        $this->crisisStartedAt = now()->format('d/m/Y H:i');
    } else {
        $this->crisisStartedAt = null;
        $this->deadlineTimer = 72 * 3600;
    }
};

?>

<div class="py-10 px-6 max-w-7xl mx-auto" x-data="{ 
    activeTab: 'emergency',
    timer: 72 * 3600,
    isCrisisActive: @entangle('isCrisisActive'),
    showTriggerConfirm: false,
    init() {
        setInterval(() => { 
            if(this.isCrisisActive && this.timer > 0) this.timer-- 
        }, 1000);
    },
    formatTimer() {
        const h = Math.floor(this.timer / 3600);
        const m = Math.floor((this.timer % 3600) / 60);
        const s = this.timer % 60;
        return `${h}h ${m}m ${s}s`;
    },
    copyToClipboard(text) {
        navigator.clipboard.writeText(text);
        alert('Copié dans le presse-papier !');
    }
}">
    {{-- Header de Crise --}}
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-red-900/30 pb-8">
        <div>
            <div x-show="isCrisisActive" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-900/20 border border-red-500/30 text-red-500 text-[10px] font-bold tracking-widest uppercase mb-3 animate-pulse">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                ÉTAT DE CRISE ACTIF
            </div>
            <div x-show="!isCrisisActive" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-900/20 border border-green-500/30 text-green-500 text-[10px] font-bold tracking-widest uppercase mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                Systèmes Nominaux — Prêt pour Incident
            </div>
            <h1 class="text-4xl font-bold text-[#F5F0E8] tracking-tight">Cellule de <span :class="isCrisisActive ? 'text-red-500' : 'text-[#C9A84C]'">Crise</span></h1>
            <p class="text-[#7A6E5E] mt-2">Protocole de réponse aux incidents, conformité RGPD et continuité d'activité.</p>
        </div>
        
        <div class="flex items-center gap-4">
            <div x-show="isCrisisActive" class="text-right">
                <div class="text-[10px] uppercase tracking-widest text-[#7A6E5E] mb-1">Délai Légal CNIL</div>
                <div class="text-2xl font-mono text-red-500 bg-red-900/10 px-4 py-2 border border-red-500/20 rounded-sm" x-text="formatTimer()"></div>
            </div>

            <div class="flex gap-2">
                @if($isCrisisActive)
                <a href="{{ route('admin.incident.export') }}" target="_blank" class="h-12 px-6 bg-[#C9A84C] text-black font-bold text-sm hover:bg-[#D4B86A] transition-colors rounded-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Générer Rapport PRI
                </a>
                <button wire:click="toggleCrisis" class="h-12 px-4 bg-gray-800 text-gray-400 text-xs font-bold uppercase tracking-widest hover:text-white transition-colors border border-gray-700">
                    Clore la crise
                </button>
                @else
                <button @click="showTriggerConfirm = true" x-show="!showTriggerConfirm" class="h-12 px-8 bg-red-900 text-red-100 font-bold text-sm hover:bg-red-800 transition-all rounded-sm flex items-center gap-2 border border-red-500/50 shadow-[0_0_20px_rgba(185,28,28,0.3)]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    DÉCLENCHER ÉTAT DE CRISE
                </button>
                <div x-show="showTriggerConfirm" x-transition class="flex items-center gap-2">
                    <span class="text-xs text-red-500 font-bold uppercase tracking-widest animate-pulse">Confirmer ?</span>
                    <button wire:click="toggleCrisis" @click="showTriggerConfirm = false" class="px-4 py-2 bg-red-600 text-white text-xs font-bold rounded-sm">OUI, ACTIVER</button>
                    <button @click="showTriggerConfirm = false" class="px-4 py-2 bg-gray-800 text-gray-400 text-xs font-bold rounded-sm">ANNULER</button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        {{-- Navigation Latérale --}}
        <div class="lg:col-span-1 space-y-1">
            <button @click="activeTab = 'emergency'" :class="activeTab === 'emergency' ? 'bg-red-900/20 text-red-400 border-red-500/30' : 'text-[#7A6E5E] border-transparent hover:text-[#F5F0E8]'" class="w-full text-left px-4 py-3 rounded-sm border transition-all text-sm font-medium flex items-center justify-between">
                1. Actions d'Urgence
                <svg x-show="activeTab === 'emergency'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button @click="activeTab = 'legal'" :class="activeTab === 'legal' ? 'bg-red-900/20 text-red-400 border-red-500/30' : 'text-[#7A6E5E] border-transparent hover:text-[#F5F0E8]'" class="w-full text-left px-4 py-3 rounded-sm border transition-all text-sm font-medium flex items-center justify-between">
                2. Juridique & DPO
                <svg x-show="activeTab === 'legal'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button @click="activeTab = 'comms'" :class="activeTab === 'comms' ? 'bg-red-900/20 text-red-400 border-red-500/30' : 'text-[#7A6E5E] border-transparent hover:text-[#F5F0E8]'" class="w-full text-left px-4 py-3 rounded-sm border transition-all text-sm font-medium flex items-center justify-between">
                3. Communication
                <svg x-show="activeTab === 'comms'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button @click="activeTab = 'proofs'" :class="activeTab === 'proofs' ? 'bg-red-900/20 text-red-400 border-red-500/30' : 'text-[#7A6E5E] border-transparent hover:text-[#F5F0E8]'" class="w-full text-left px-4 py-3 rounded-sm border transition-all text-sm font-medium flex items-center justify-between">
                4. Preuves de Sécurité
                <svg x-show="activeTab === 'proofs'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        {{-- Contenu Principal --}}
        <div class="lg:col-span-3">
            
            {{-- TAB: EMERGENCY --}}
            <div x-show="activeTab === 'emergency'" x-transition class="space-y-6">
                <div class="bg-red-900/5 border border-red-900/20 p-6 rounded-sm">
                    <h3 class="text-[#F5F0E8] font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Actions Immédiates (PRI)
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3 text-sm text-[#7A6E5E]">
                            <span class="w-5 h-5 rounded-full bg-red-900/30 text-red-500 flex items-center justify-center shrink-0 font-bold text-[10px]">1</span>
                            <span><strong>Isoler les systèmes</strong> : Couper les accès VPN et réinitialiser les secrets de production (Stripe API, AWS).</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-[#7A6E5E]">
                            <span class="w-5 h-5 rounded-full bg-red-900/30 text-red-500 flex items-center justify-center shrink-0 font-bold text-[10px]">2</span>
                            <span><strong>Vérifier l'intégrité</strong> : Contrôler la signature de la dernière backup du {{ $lastBackup }}.</span>
                        </li>
                        <li class="flex items-start gap-3 text-sm text-[#7A6E5E]">
                            <span class="w-5 h-5 rounded-full bg-red-900/30 text-red-500 flex items-center justify-center shrink-0 font-bold text-[10px]">3</span>
                            <span><strong>Notifier le DPO</strong> : Lancer la procédure de qualification de la fuite (Données sensibles ?).</span>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($emergencyContacts as $contact)
                    <div class="p-4 bg-black/20 border border-[#C9A84C]/10 rounded-sm flex items-center gap-4">
                        @if($contact['logo'] === 'cnil')
                            <svg class="w-8 h-8 shrink-0" viewBox="0 0 1564.3 813.9" xmlns="http://www.w3.org/2000/svg">
                                <style>.st0{fill:#004D9D;}.st1{fill:#E30613;}</style>
                                <g transform="matrix(11.3,0,0,11.3,-261.4,-321.7)">
                                    <path class="st0" d="m 24,84.8 c -0.6,-0.6 -1,-1.5 -1,-2.6 0,-1.2 0.3,-2.1 0.9,-2.7 0.6,-0.6 1.5,-1 2.7,-1 0.8,0 1.5,0.1 2.1,0.3 v 1.3 c -0.6,-0.3 -1.3,-0.4 -2,-0.4 -0.8,0 -1.4,0.2 -1.7,0.6 -0.4,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.3 0.6,1.7 0.4,0.4 1,0.6 1.7,0.6 0.7,0 1.4,-0.1 2,-0.4 v 1.3 c -0.7,0.2 -1.4,0.3 -2.1,0.3 -1.2,0.1 -2.1,-0.2 -2.7,-0.8 z"/>
                                    <path class="st0" d="m 29.9,82.2 c 0,-1.3 0.3,-2.2 0.9,-2.8 0.6,-0.6 1.4,-0.9 2.6,-0.9 1.2,0 2,0.3 2.6,0.9 0.6,0.6 0.9,1.5 0.9,2.8 0,2.4 -1.2,3.6 -3.5,3.6 -2.4,-0.1 -3.5,-1.2 -3.5,-3.6 z m 4.9,1.7 c 0.3,-0.4 0.5,-1 0.5,-1.7 0,-0.9 -0.2,-1.5 -0.5,-1.8 -0.3,-0.4 -0.8,-0.5 -1.5,-0.5 -0.7,0 -1.2,0.2 -1.5,0.5 -0.3,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.4 0.5,1.7 0.3,0.4 0.8,0.6 1.5,0.6 0.7,0 1.2,-0.2 1.5,-0.6 z"/>
                                    <path class="st0" d="m 38.2,78.7 h 1.5 l 2.1,4.2 2.1,-4.2 h 1.5 v 7 h -1.5 v -4.5 l -1.7,3.3 h -0.9 l -1.7,-3.3 v 4.5 h -1.5 v -7 z"/>
                                    <path class="st0" d="m 47.3,78.7 h 1.5 l 2.1,4.2 2.1,-4.2 h 1.5 v 7 H 53 v -4.5 l -1.7,3.3 h -0.9 l -1.7,-3.3 v 4.5 h -1.5 v -7 z"/>
                                    <path class="st0" d="M 56.5,78.7 H 58 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 59.9,85.4 v -1.3 c 0.3,0.1 0.7,0.2 1.1,0.3 0.4,0.1 0.7,0.1 1.1,0.1 0.6,0 0.9,-0.1 1.2,-0.2 0.3,-0.1 0.3,-0.3 0.3,-0.6 0,-0.2 -0.1,-0.4 -0.2,-0.5 -0.1,-0.1 -0.3,-0.2 -0.5,-0.3 -0.2,-0.1 -0.6,-0.2 -1.1,-0.3 -0.7,-0.2 -1.3,-0.5 -1.6,-0.8 -0.3,-0.3 -0.5,-0.7 -0.5,-1.3 0,-0.6 0.2,-1.1 0.7,-1.5 0.5,-0.4 1.2,-0.5 2.1,-0.5 0.4,0 0.8,0 1.2,0.1 0.4,0.1 0.7,0.1 0.9,0.2 v 1.3 c -0.6,-0.2 -1.2,-0.3 -1.9,-0.3 -0.5,0 -0.9,0.1 -1.1,0.2 -0.2,0.1 -0.4,0.3 -0.4,0.6 0,0.2 0,0.3 0.1,0.4 0.1,0.1 0.2,0.2 0.5,0.3 0.2,0.1 0.6,0.2 1,0.3 0.8,0.2 1.4,0.5 1.7,0.8 0.3,0.3 0.5,0.8 0.5,1.3 0,0.6 -0.2,1.1 -0.7,1.5 -0.5,0.4 -1.2,0.5 -2.2,0.5 -0.9,0 -1.6,-0.1 -2.2,-0.3 z"/>
                                    <path class="st0" d="m 66.4,85.4 v -1.3 c 0.3,0.1 0.7,0.2 1.1,0.3 0.4,0.1 0.7,0.1 1.1,0.1 0.6,0 0.9,-0.1 1.2,-0.2 0.3,-0.1 0.3,-0.3 0.3,-0.6 0,-0.2 -0.1,-0.4 -0.2,-0.5 -0.1,-0.1 -0.3,-0.2 -0.5,-0.3 -0.2,-0.1 -0.6,-0.2 -1.1,-0.3 -0.7,-0.2 -1.3,-0.5 -1.6,-0.8 -0.3,-0.3 -0.5,-0.7 -0.5,-1.3 0,-0.6 0.2,-1.1 0.7,-1.5 0.5,-0.4 1.2,-0.5 2.1,-0.5 0.4,0 0.8,0 1.2,0.1 0.4,0.1 0.7,0.1 0.9,0.2 v 1.3 c -0.6,-0.2 -1.2,-0.3 -1.9,-0.3 -0.5,0 -0.9,0.1 -1.1,0.2 -0.2,0.1 -0.4,0.3 -0.4,0.6 0,0.2 0,0.3 0.1,0.4 0.1,0.1 0.2,0.2 0.5,0.3 0.2,0.1 0.6,0.2 1,0.3 0.8,0.2 1.4,0.5 1.7,0.8 0.3,0.3 0.5,0.8 0.5,1.3 0,0.6 -0.2,1.1 -0.7,1.5 -0.5,0.4 -1.2,0.5 -2.2,0.5 -0.9,0 -1.7,-0.1 -2.2,-0.3 z"/>
                                    <path class="st0" d="m 73.3,78.7 h 1.5 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 76.6,82.2 c 0,-1.3 0.3,-2.2 0.9,-2.8 0.6,-0.6 1.4,-0.9 2.6,-0.9 1.2,0 2,0.3 2.6,0.9 0.6,0.6 0.9,1.5 0.9,2.8 0,2.4 -1.2,3.6 -3.5,3.6 -2.4,-0.1 -3.5,-1.2 -3.5,-3.6 z m 4.9,1.7 c 0.3,-0.4 0.5,-1 0.5,-1.7 0,-0.9 -0.2,-1.5 -0.5,-1.8 -0.3,-0.4 -0.8,-0.5 -1.5,-0.5 -0.7,0 -1.2,0.2 -1.5,0.5 -0.3,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.4 0.5,1.7 0.3,0.4 0.8,0.6 1.5,0.6 0.7,0 1.2,-0.2 1.5,-0.6 z"/>
                                    <path class="st0" d="m 85.3,78.7 h 1.3 l 3.4,4.6 v -4.6 h 1.5 v 7 H 90.2 L 86.7,81 v 4.6 h -1.5 v -6.9 z"/>
                                    <path class="st0" d="m 98.1,78.7 h 1.3 l 3.4,4.6 v -4.6 h 1.5 v 7 H 103 L 99.6,81 v 4.6 h -1.5 z"/>
                                    <path class="st0" d="m 108.7,78.7 h 1.6 l 2.7,7 h -1.5 L 110.8,84 h -2.6 l -0.7,1.7 H 106 Z m 1.9,4.1 -1.1,-2.8 -1.1,2.8 z"/>
                                    <path class="st0" d="M 115.2,79.8 H 113 v -1.1 h 5.8 v 1.1 h -2.2 v 5.8 h -1.5 v -5.8 z"/>
                                    <path class="st0" d="m 120.4,78.7 h 1.5 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 123.7,82.2 c 0,-1.3 0.3,-2.2 0.9,-2.8 0.6,-0.6 1.4,-0.9 2.6,-0.9 1.2,0 2,0.3 2.6,0.9 0.6,0.6 0.9,1.5 0.9,2.8 0,2.4 -1.2,3.6 -3.5,3.6 -2.4,-0.1 -3.5,-1.2 -3.5,-3.6 z m 4.9,1.7 c 0.3,-0.4 0.5,-1 0.5,-1.7 0,-0.9 -0.2,-1.5 -0.5,-1.8 -0.3,-0.4 -0.8,-0.5 -1.5,-0.5 -0.7,0 -1.2,0.2 -1.5,0.5 -0.3,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.4 0.5,1.7 0.3,0.3 0.8,0.6 1.5,0.6 0.7,0 1.2,-0.2 1.5,-0.6 z"/>
                                    <path class="st0" d="m 132.4,78.7 h 1.3 l 3.4,4.6 v -4.6 h 1.5 v 7 h -1.3 l -3.4,-4.6 v 4.6 h -1.5 z"/>
                                    <path class="st0" d="m 143,78.7 h 1.6 l 2.7,7 h -1.5 L 145.1,84 h -2.6 l -0.7,1.7 h -1.6 z m 1.9,4.1 -1.1,-2.8 -1.1,2.8 z"/>
                                    <path class="st0" d="m 149.1,78.7 h 1.5 v 5.8 h 3.5 v 1.1 h -5 z"/>
                                    <path class="st0" d="m 155.5,78.7 h 5.1 v 1.1 H 157 v 1.8 h 3.2 v 1.1 H 157 v 1.8 h 3.6 v 1.1 h -5.1 z"/>
                                    <path class="st0" d="m 23.2,91 h 1.5 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 26.2,91 h 1.3 l 3.4,4.6 V 91 h 1.5 v 7 H 31.1 L 27.7,93.4 V 98 h -1.5 z"/>
                                    <path class="st0" d="m 33.8,91 h 5 v 1.1 h -3.5 v 1.8 h 3.1 V 95 h -3.1 v 3 h -1.5 z"/>
                                    <path class="st0" d="m 39,94.5 c 0,-1.3 0.3,-2.2 0.9,-2.8 0.6,-0.6 1.4,-0.9 2.6,-0.9 1.2,0 2,0.3 2.6,0.9 0.6,0.6 0.9,1.5 0.9,2.8 0,2.4 -1.2,3.6 -3.5,3.6 -2.3,0 -3.5,-1.2 -3.5,-3.6 z m 5,1.8 c 0.3,-0.4 0.5,-1 0.5,-1.7 0,-0.9 -0.2,-1.5 -0.5,-1.8 -0.3,-0.4 -0.8,-0.5 -1.5,-0.5 -0.7,0 -1.2,0.2 -1.5,0.5 -0.3,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.4 0.5,1.7 0.3,0.4 0.8,0.6 1.5,0.6 0.7,0 1.1,-0.2 1.5,-0.6 z"/>
                                    <path class="st0" d="m 47.2,91 h 3.4 c 0.8,0 1.4,0.2 1.8,0.6 0.4,0.4 0.6,1 0.6,1.7 0,0.5 -0.1,0.9 -0.4,1.2 -0.2,0.3 -0.6,0.6 -1,0.7 0.1,0.1 0.2,0.2 0.3,0.3 0.1,0.1 0.1,0.3 0.2,0.4 L 53,97.8 H 51.5 L 50.7,95.9 C 50.6,95.7 50.5,95.6 50.5,95.6 50.4,95.5 50.3,95.5 50.1,95.5 H 48.9 V 98 h -1.5 v -7 z m 3,3.5 c 0.4,0 0.7,-0.1 0.9,-0.3 0.2,-0.2 0.3,-0.5 0.3,-0.8 0,-0.3 -0.1,-0.7 -0.3,-0.9 -0.2,-0.2 -0.5,-0.3 -0.9,-0.3 h -1.6 v 2.2 h 1.6 z"/>
                                    <path class="st0" d="m 54.2,91 h 1.5 l 2.1,4.2 2.1,-4.2 h 1.5 v 7 h -1.5 v -4.5 l -1.7,3.3 H 57.3 L 55.6,93.5 V 98 h -1.5 v -7 z"/>
                                    <path class="st0" d="m 65.1,91 h 1.6 l 2.7,7 H 67.9 L 67.2,96.3 H 64.6 L 63.9,98 h -1.6 z m 1.9,4.2 -1.1,-2.8 -1.1,2.8 z"/>
                                    <path class="st0" d="M 71,92.2 H 68.8 V 91 h 5.8 v 1.1 H 72.4 V 98 H 71 Z"/>
                                    <path class="st0" d="m 75.7,91 h 1.5 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 81.7,99.4 c -0.3,-0.3 -0.4,-0.8 -0.4,-1.3 -1.8,-0.2 -2.7,-1.4 -2.7,-3.5 0,-1.3 0.3,-2.2 0.9,-2.8 0.6,-0.6 1.4,-0.9 2.6,-0.9 1.2,0 2,0.3 2.6,0.9 0.6,0.6 0.9,1.5 0.9,2.8 0,2.2 -1,3.4 -2.9,3.5 0,0.3 0.1,0.5 0.2,0.6 0.1,0.1 0.3,0.2 0.6,0.2 0.3,0 0.5,0 0.8,-0.1 v 1 c -0.1,0 -0.3,0.1 -0.5,0.1 -0.2,0 -0.4,0 -0.6,0 -0.8,-0.1 -1.3,-0.2 -1.5,-0.5 z m 1.7,-3.1 c 0.3,-0.4 0.5,-1 0.5,-1.7 0,-0.9 -0.2,-1.5 -0.5,-1.8 -0.3,-0.4 -0.8,-0.5 -1.5,-0.5 -0.7,0 -1.2,0.2 -1.5,0.5 -0.3,0.4 -0.5,1 -0.5,1.8 0,0.8 0.2,1.4 0.5,1.7 0.3,0.4 0.8,0.6 1.5,0.6 0.7,0 1.2,-0.2 1.5,-0.6 z"/>
                                    <path class="st0" d="m 87.4,97.4 c -0.6,-0.5 -0.8,-1.1 -0.8,-2 V 91 h 1.5 v 4.2 c 0,0.5 0.1,0.9 0.4,1.2 0.3,0.3 0.7,0.4 1.3,0.4 0.5,0 1,-0.1 1.2,-0.4 0.3,-0.3 0.4,-0.7 0.4,-1.2 V 91 h 1.5 v 4.3 c 0,0.9 -0.3,1.5 -0.8,2 -0.6,0.5 -1.3,0.7 -2.3,0.7 -1,0 -1.8,-0.1 -2.4,-0.6 z"/>
                                    <path class="st0" d="m 94.4,91 h 5.1 v 1.1 h -3.6 v 1.8 h 3.2 V 95 h -3.2 v 1.8 h 3.6 V 98 h -5.1 z"/>
                                    <path class="st1" d="m 107.9,97.3 c -0.3,0.3 -0.6,0.4 -1,0.6 -0.4,0.1 -0.8,0.2 -1.3,0.2 -0.8,0 -1.4,-0.2 -1.9,-0.5 -0.5,-0.3 -0.7,-0.8 -0.7,-1.4 0,-0.5 0.1,-0.9 0.4,-1.2 0.3,-0.3 0.7,-0.6 1.2,-0.9 -0.3,-0.3 -0.5,-0.6 -0.7,-0.8 -0.1,-0.2 -0.2,-0.5 -0.2,-0.8 0,-0.5 0.2,-0.9 0.5,-1.2 0.4,-0.3 0.8,-0.5 1.4,-0.5 0.6,0 1.1,0.1 1.4,0.4 0.4,0.3 0.5,0.7 0.5,1.2 0,0.4 -0.1,0.7 -0.3,0.9 -0.2,0.3 -0.4,0.5 -0.8,0.8 l 1.5,1.5 c 0.1,-0.2 0.2,-0.4 0.2,-0.7 v -0.2 h 1.1 v 0.2 c 0,0.3 0,0.5 -0.1,0.8 -0.1,0.3 -0.2,0.5 -0.3,0.7 l 0.7,0.7 -0.9,0.8 z m -1.5,-0.4 c 0.2,-0.1 0.4,-0.2 0.6,-0.4 L 105.4,95 c -0.4,0.2 -0.6,0.3 -0.8,0.5 -0.2,0.2 -0.3,0.4 -0.3,0.7 0,0.3 0.1,0.5 0.3,0.7 0.2,0.2 0.5,0.2 0.9,0.2 0.4,0 0.6,-0.1 0.9,-0.2 z m -0.2,-3.7 c 0.1,-0.1 0.2,-0.3 0.2,-0.6 0,-0.2 -0.1,-0.4 -0.2,-0.5 -0.1,-0.1 -0.3,-0.2 -0.6,-0.2 -0.3,0 -0.5,0.1 -0.6,0.2 -0.1,0.1 -0.2,0.3 -0.2,0.5 0,0.2 0.1,0.4 0.2,0.5 0.1,0.1 0.3,0.3 0.6,0.5 0.3,-0.1 0.5,-0.2 0.6,-0.4 z"/>
                                    <path class="st0" d="m 113.5,91 h 1.5 v 5.8 h 3.5 V 98 h -5 z"/>
                                    <path class="st0" d="m 119.5,91 h 1.5 v 7 h -1.5 z"/>
                                    <path class="st0" d="m 122.5,91 h 3.6 c 0.7,0 1.2,0.2 1.6,0.5 0.4,0.3 0.5,0.8 0.5,1.4 0,0.4 -0.1,0.7 -0.2,0.9 -0.1,0.3 -0.3,0.5 -0.6,0.6 0.4,0.1 0.6,0.3 0.8,0.5 0.2,0.2 0.3,0.6 0.3,1 0,0.7 -0.2,1.2 -0.6,1.5 -0.4,0.3 -0.9,0.5 -1.7,0.5 h -3.7 z m 3.3,2.9 c 0.7,0 1,-0.3 1,-0.9 0,-0.3 -0.1,-0.5 -0.2,-0.7 -0.2,-0.1 -0.5,-0.2 -0.9,-0.2 H 124 v 1.8 z m 0,3 c 0.4,0 0.7,-0.1 0.8,-0.2 0.1,-0.1 0.3,-0.4 0.3,-0.8 0,-0.3 -0.1,-0.5 -0.3,-0.7 -0.2,-0.1 -0.5,-0.2 -0.9,-0.2 H 124 v 1.9 z"/>
                                    <path class="st0" d="m 129.7,91 h 5.1 v 1.1 h -3.6 v 1.8 h 3.2 V 95 h -3.2 v 1.8 h 3.6 V 98 h -5.1 z"/>
                                    <path class="st0" d="m 136,91 h 3.4 c 0.8,0 1.4,0.2 1.8,0.6 0.4,0.4 0.6,1 0.6,1.7 0,0.5 -0.1,0.9 -0.4,1.2 -0.2,0.3 -0.6,0.6 -1,0.7 0.1,0.1 0.2,0.2 0.3,0.3 0.1,0.1 0.1,0.3 0.2,0.4 l 0.9,1.9 h -1.5 l -0.8,-1.9 c -0.1,-0.2 -0.2,-0.3 -0.2,-0.3 -0.1,-0.1 -0.2,-0.1 -0.4,-0.1 h -1.2 V 98 H 136 Z m 3.1,3.5 c 0.4,0 0.7,-0.1 0.9,-0.3 0.2,-0.2 0.3,-0.5 0.3,-0.8 0,-0.3 -0.1,-0.7 -0.3,-0.9 -0.2,-0.2 -0.5,-0.3 -0.9,-0.3 h -1.6 v 2.2 h 1.6 z"/>
                                    <path class="st0" d="m 144.4,92.2 h -2.2 V 91 h 5.8 v 1.1 h -2.2 V 98 h -1.5 v -5.8 z"/>
                                    <path class="st0" d="m 149.1,91 h 5.1 v 1.1 h -3.6 v 1.8 h 3.2 V 95 h -3.2 v 1.8 h 3.6 V 98 h -5.1 z m 2.7,-2.5 h 1.3 l -1,2 H 151 Z"/>
                                    <path class="st0" d="m 155.4,97.7 v -1.3 c 0.3,0.1 0.7,0.2 1.1,0.3 0.4,0.1 0.7,0.1 1.1,0.1 0.6,0 0.9,0 1.2,-0.2 0.2,-0.1 0.3,-0.3 0.3,-0.6 0,-0.2 -0.1,-0.4 -0.2,-0.5 -0.1,-0.1 -0.3,-0.2 -0.5,-0.3 -0.2,-0.1 -0.6,-0.2 -1.1,-0.3 -0.7,-0.2 -1.3,-0.5 -1.6,-0.8 -0.3,-0.3 -0.5,-0.7 -0.5,-1.3 0,-0.6 0.2,-1.1 0.7,-1.5 0.5,-0.3 1.2,-0.5 2.1,-0.5 0.4,0 0.8,0 1.2,0.1 0.4,0.1 0.7,0.1 0.9,0.2 v 1.3 c -0.6,-0.2 -1.2,-0.3 -1.9,-0.3 -0.5,0 -0.9,0.1 -1.1,0.2 -0.2,0.1 -0.4,0.3 -0.4,0.6 0,0.2 0,0.3 0.1,0.4 0.1,0.1 0.2,0.2 0.5,0.3 0.2,0.1 0.6,0.2 1,0.3 0.8,0.2 1.4,0.5 1.7,0.8 0.3,0.3 0.5,0.8 0.5,1.3 0,0.6 -0.2,1.1 -0.7,1.5 -0.5,0.4 -1.2,0.5 -2.2,0.5 -2.2,1.1 -1.6,0 -2.2,-0.3 z"/>
                                </g>
                                <rect x="1481.3" y="389.9" class="st1" width="83" height="83"/>
                                <g transform="matrix(11.3,0,0,11.3,-261.4,-321.7)">
                                    <path class="st0" d="m 52.5,62.1 0.8,6.7 c -3.6,1.1 -7.9,1.7 -13.1,1.7 -6.3,0 -10.8,-1.6 -13.3,-4.7 C 24.3,62.6 23,57.2 23,49.4 23,41.6 24.3,36.2 26.9,33 c 2.6,-3.2 7,-4.7 13.3,-4.7 4.6,0 8.7,0.5 12.3,1.4 l -0.8,6.7 c -3.2,-0.2 -7,-0.3 -11.5,-0.3 -3,0 -5,0.9 -6.1,2.8 -1.1,1.9 -1.6,5.4 -1.6,10.5 0,5.1 0.5,8.6 1.6,10.5 1.1,1.9 3.1,2.8 6.1,2.8 5.5,0 9.6,-0.2 12.3,-0.6 z"/>
                                    <path class="st0" d="m 93.6,29 v 37.5 c 0,2.2 -1.1,3.3 -3.2,3.3 h -5.6 c -0.9,0 -1.6,-0.2 -2.1,-0.6 -0.5,-0.4 -1,-1.1 -1.5,-2 L 70.4,45.4 c -1.3,-2.9 -2.2,-5 -2.5,-6.3 h -0.7 c 0.3,2 0.4,4.1 0.4,6.5 V 69.8 H 59 V 32.2 c 0,-2.2 1.1,-3.3 3.3,-3.3 h 5.5 c 0.9,0 1.6,0.2 2.1,0.6 0.5,0.4 1,1.1 1.5,2 l 10.4,21.1 c 1,2 2,4.2 3,6.6 h 0.6 C 85.2,56.1 85,53.8 85,52.3 V 29 Z"/>
                                    <path class="st0" d="m 110.9,69.8 h -9 V 29 h 9 z"/>
                                    <path class="st0" d="m 145.2,69.9 h -16.8 c -3.2,0 -5.5,-0.8 -7.1,-2.4 -1.5,-1.6 -2.3,-3.7 -2.3,-6.3 V 29 h 9 v 30.4 c 0,1.1 0.2,1.8 0.7,2.3 0.5,0.5 1.3,0.7 2.5,0.7 h 14 z"/>
                                </g>
                            </svg>
                        @else
                            <img src="{{ $contact['logo'] }}" alt="" class="w-8 h-8 rounded-sm shrink-0">
                        @endif
                        <div>
                            <div class="text-[9px] uppercase tracking-widest text-[#7A6E5E] mb-0.5">{{ $contact['label'] }}</div>
                            <div class="text-[#F5F0E8] font-mono font-bold text-sm">{{ $contact['value'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- TAB: LEGAL --}}
            <div x-show="activeTab === 'legal'" x-transition class="space-y-6">
                <div class="bg-[#C9A84C]/5 border border-[#C9A84C]/10 p-6 rounded-sm">
                    <h3 class="text-[#F5F0E8] font-bold mb-4">Informations DPO & Légal</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-[#C9A84C]/10">
                            <span class="text-sm text-[#7A6E5E]">Nom du DPO</span>
                            <span class="text-sm text-[#F5F0E8] font-medium">{{ $dpoContact['name'] }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-[#C9A84C]/10">
                            <span class="text-sm text-[#7A6E5E]">Email direct</span>
                            <span class="text-sm text-[#C9A84C] font-mono">{{ $dpoContact['email'] }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <span class="text-sm text-[#7A6E5E]">Délai Notification CNIL</span>
                            <span class="text-sm text-red-500 font-bold">72 Heures maximum</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: COMMS --}}
            <div x-show="activeTab === 'comms'" x-transition class="space-y-6">
                {{-- Conseil stratégique --}}
                <div class="p-6 bg-[#C9A84C]/5 border border-[#C9A84C]/20 rounded-sm">
                    <h4 class="text-[#F5F0E8] font-bold mb-4 flex items-center gap-2 text-sm">
                        <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Stratégie de Communication de Crise
                    </h4>
                    <p class="text-sm text-[#7A6E5E] leading-relaxed mb-6">
                        La transparence est votre meilleure alliée. En cas d'incident, l'objectif est d'informer sans propager la panique. Présentez les faits de manière factuelle : <strong>ce qui s'est passé</strong>, <strong>ce que nous faisons</strong>, et <strong>ce que le client doit faire</strong>.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-5 bg-emerald-500/10 border border-emerald-500/30 rounded-sm shadow-[0_0_20px_rgba(16,185,129,0.05)]">
                            <span class="text-emerald-400 font-bold text-xs uppercase tracking-widest block mb-3 border-b border-emerald-500/20 pb-2">À privilégier</span>
                            <ul class="text-xs text-emerald-100/70 space-y-2 leading-relaxed">
                                <li class="flex items-start gap-2"><span class="text-emerald-400 mt-1">•</span> Phrases courtes, claires et sans ambiguïté</li>
                                <li class="flex items-start gap-2"><span class="text-emerald-400 mt-1">•</span> Reconnaissance immédiate de la situation</li>
                                <li class="flex items-start gap-2"><span class="text-emerald-400 mt-1">•</span> Actions concrètes déjà entreprises (PRI)</li>
                            </ul>
                        </div>
                        <div class="p-5 bg-red-500/10 border border-red-500/30 rounded-sm shadow-[0_0_20px_rgba(239,68,68,0.05)]">
                            <span class="text-red-400 font-bold text-xs uppercase tracking-widest block mb-3 border-b border-red-500/20 pb-2">À éviter</span>
                            <ul class="text-xs text-red-100/70 space-y-2 leading-relaxed">
                                <li class="flex items-start gap-2"><span class="text-red-400 mt-1">•</span> Jargon technique anxiogène ou imprécis</li>
                                <li class="flex items-start gap-2"><span class="text-red-400 mt-1">•</span> Promesses de résolution sans certitude absolue</li>
                                <li class="flex items-start gap-2"><span class="text-red-400 mt-1">•</span> Silences prolongés (création d'un vide informationnel)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                @foreach($commsTemplates as $template)
                <div class="p-6 border border-[#C9A84C]/20 bg-black/20 rounded-sm group hover:border-[#C9A84C]/40 transition-colors">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <span class="text-xs font-bold text-[#C9A84C] uppercase tracking-widest block mb-1">Modèle</span>
                            <h4 class="text-[#F5F0E8] font-bold">{{ $template['title'] }}</h4>
                        </div>
                        <button @click="copyToClipboard(`{{ str_replace('`', '\`', $template['content']) }}`)" class="px-4 py-2 bg-[#C9A84C]/10 text-[#C9A84C] text-[10px] font-bold uppercase tracking-widest border border-[#C9A84C]/20 hover:bg-[#C9A84C] hover:text-black transition-all">
                            Copier le texte
                        </button>
                    </div>
                    <div class="bg-black/40 p-4 border border-white/5 rounded-sm mb-2">
                        <div class="text-[10px] text-[#7A6E5E] uppercase mb-2">Sujet : {{ $template['subject'] }}</div>
                        <p class="text-xs text-[#7A6E5E] leading-relaxed whitespace-pre-wrap">{{ $template['content'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- TAB: PROOFS --}}
            <div x-show="activeTab === 'proofs'" x-transition class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-6 border border-[#C9A84C]/10 bg-black/20 rounded-sm">
                        <h4 class="text-[#F5F0E8] text-sm font-bold mb-4 uppercase tracking-wider">État du RBAC</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#7A6E5E]">Administrateurs actifs</span>
                                <span class="text-[#F5F0E8]">{{ User::where('role', 'admin')->count() }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#7A6E5E]">Accès SSH production</span>
                                <span class="text-green-500 font-bold">Restreint (IP whitelist)</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border border-[#C9A84C]/10 bg-black/20 rounded-sm">
                        <h4 class="text-[#F5F0E8] text-sm font-bold mb-4 uppercase tracking-wider">Sécurité Infrastructure</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#7A6E5E]">Chiffrement au repos</span>
                                <span class="text-green-500">AES-256</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#7A6E5E]">Certificat SSL</span>
                                <span class="text-green-500">A+ Qualys</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Placeholder Diagramme --}}
                <div class="h-64 border border-dashed border-[#C9A84C]/20 rounded-sm flex items-center justify-center bg-black/10">
                    <div class="text-center">
                        <svg class="w-12 h-12 text-[#C9A84C]/20 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                        <span class="text-[10px] uppercase tracking-widest text-[#7A6E5E]">Diagramme d'Architecture Cloud OmnyRestore</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
