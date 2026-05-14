<?php

use function Livewire\Volt\{state, layout, title};
use App\Models\Order;
use App\Models\User;

layout('layouts.app');
title('Cellule de Crise — OmnyRestore');

state([
    'activeTab' => request()->query('tab', 'emergency'),
    'lastBackup' => now()->subHours(4)->format('d/m/Y H:i'),
    'dpoContact' => [
        'name' => 'Alain GUILLON (DPO Interne)',
        'email' => 'dpo@omnyrestore.fr',
        'phone' => '+33 (0)6 XX XX XX XX'
    ],
    'emergencyContacts' => [
        ['label' => 'ANSSI (Incident Majeur)', 'value' => '01 71 21 01 21', 'type' => 'phone', 'logo' => 'https://www.ssi.gouv.fr/favicon.ico'],
        ['label' => 'CNIL (Notification)', 'value' => 'cnil.fr/notifier', 'type' => 'url', 'logo' => 'https://www.cnil.fr/favicon.ico'],
        ['label' => 'OVHcloud (Support VIP)', 'value' => '08 203 203 63', 'type' => 'phone', 'logo' => 'https://www.ovhcloud.com/favicon.ico'],
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
    activeTab: @entangle('activeTab'),
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
            <button @click="activeTab = 'moderation'" :class="activeTab === 'moderation' ? 'bg-red-900/20 text-red-400 border-red-500/30' : 'text-[#7A6E5E] border-transparent hover:text-[#F5F0E8]'" class="w-full text-left px-4 py-3 rounded-sm border transition-all text-sm font-medium flex items-center justify-between">
                5. Lexique Modération IA
                <svg x-show="activeTab === 'moderation'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
                        <img src="{{ $contact['logo'] }}" alt="" class="w-8 h-8 rounded-sm shrink-0 bg-white p-1">
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
            <div x-show="activeTab === 'comms'" x-transition class="space-y-4">
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

            {{-- TAB: MODERATION --}}
            <div x-show="activeTab === 'moderation'" x-transition class="space-y-6">
                <div class="bg-red-900/5 border border-red-900/20 p-6 rounded-sm">
                    <h3 class="text-[#F5F0E8] font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Lexique des Catégories de Modération (OpenAI)
                    </h3>
                    <p class="text-sm text-[#7A6E5E] mb-6">
                        Toutes les images uploadées sur OmnyRestore sont analysées par le modèle <code class="text-red-400">omni-moderation-latest</code>. 
                        Voici la liste des flags possibles et leur signification légale ou éthique.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Catégorie Sexuelle --}}
                        <div class="p-4 bg-black/40 border border-red-500/10 rounded-sm">
                            <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                                Contenu Sexuel
                            </h4>
                            <ul class="space-y-3">
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">sexual</strong>
                                    Pornographie ou contenu sexuellement explicite. Non toléré (Action: Destruction).
                                </li>
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-500 block">sexual/minors (CSAM) 🚨</strong>
                                    Matériel pédocriminel. **Action Critique** : Rapport PHAROS obligatoire + Bannissement immédiat.
                                </li>
                            </ul>
                        </div>

                        {{-- Catégorie Haine & Harcèlement --}}
                        <div class="p-4 bg-black/40 border border-red-500/10 rounded-sm">
                            <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                Haine & Harcèlement
                            </h4>
                            <ul class="space-y-3">
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">hate / hate/threatening</strong>
                                    Discours de haine (race, religion, genre) ou menaces directes de violence.
                                </li>
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">harassment / harassment/threatening</strong>
                                    Comportement répétitif abusif ou menaçant envers des individus.
                                </li>
                            </ul>
                        </div>

                        {{-- Catégorie Violence --}}
                        <div class="p-4 bg-black/40 border border-red-500/10 rounded-sm">
                            <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-600"></span>
                                Violence & Gore
                            </h4>
                            <ul class="space-y-3">
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">violence / violence/graphic</strong>
                                    Promotion de la violence ou images macabres, gores et choquantes.
                                </li>
                            </ul>
                        </div>

                        {{-- Catégorie Activités Illicites --}}
                        <div class="p-4 bg-black/40 border border-red-500/10 rounded-sm">
                            <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                Activités Illicites
                            </h4>
                            <ul class="space-y-3">
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">illicit / illicit/violent</strong>
                                    Promotion d'activités illégales ou instructions pour la création d'armes.
                                </li>
                            </ul>
                        </div>

                        {{-- Catégorie Automutilation --}}
                        <div class="p-4 bg-black/40 border border-red-500/10 rounded-sm">
                            <h4 class="text-white font-bold text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Automutilation
                            </h4>
                            <ul class="space-y-3">
                                <li class="text-[11px] text-[#7A6E5E]">
                                    <strong class="text-red-400 block">self-harm / intent / instructions</strong>
                                    Promotion ou instructions concernant le suicide ou l'automutilation.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-red-900/10 border-l-4 border-red-600">
                        <p class="text-xs text-red-200 leading-relaxed">
                            <strong>Note de conformité :</strong> Tout signalement <code class="text-white">FLAGGED</code> dans le système bloque automatiquement la commande. L'administrateur conserve le dernier mot (Faux positif vs Action de Crise).
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
