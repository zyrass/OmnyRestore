<?php
/**
 * Admin — Simulateur de Chiffre d'Affaire
 * Route: GET /admin/revenue/simulation
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Simulation Objectifs — Admin')]
class extends Component
{
    public $targetNetDirigeant = 2500;
    public $targetNetCollab = 1800;
    public $averageOrderPrice = 19;
    public $iaRatio = 8.0; // en %
    public $fixedCosts = 150; // Serveurs, BFR, frais fixes mensuels en €
    public $securityReserve = 1000; // Plafond de sécurité impératif à laisser en banque
    public $isSasu = false; // Toggle Micro-entreprise vs SASU
    public $isCollabSalaried = false; // Toggle Freelance vs Salarié
    public $socialChargeRate = 80; // Taux de charges sociales en % (Net -> Coût Total)

    public function mount()
    {
        $settings = \Illuminate\Support\Facades\Cache::get('admin_simulator_settings', []);
        $this->targetNetDirigeant = $settings['dirigeant'] ?? 2500;
        $this->targetNetCollab = $settings['collab'] ?? 1800;
        $this->fixedCosts = $settings['fixed'] ?? 150;
        $this->securityReserve = $settings['reserve'] ?? 1000;
        $this->isSasu = $settings['isSasu'] ?? false;
        $this->isCollabSalaried = $settings['isCollabSalaried'] ?? false;
        $this->socialChargeRate = $settings['socialChargeRate'] ?? 80;
        
        // Calcul du panier moyen réel sur les 30 derniers jours
        $recentOrders = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays(30))
            ->get();
            
        if ($recentOrders->count() > 0) {
            $totalCaTtc = $recentOrders->sum('total_price_cents');
            
            // Si le cache ne contient pas les valeurs techniques, on les calcule
            $this->averageOrderPrice = $settings['averageOrderPrice'] ?? round(($totalCaTtc / 100) / $recentOrders->count(), 2);
            
            $totalIaCost = $recentOrders->sum('photo_count') * 15;
            $this->iaRatio = $settings['iaRatio'] ?? round(($totalIaCost / $totalCaTtc) * 100, 1);
        }
    }

    public function updated()
    {
        // Force le SMIC si CDI actif et montant saisi (autorise 0 pour "pas de salarié")
        if ($this->isCollabSalaried && $this->targetNetCollab > 0 && $this->targetNetCollab < 1398.69) {
            $this->targetNetCollab = 1398.69;
        }

        // Se déclenche à chaque fois qu'un champ 'wire:model.live' est modifié
        \Illuminate\Support\Facades\Cache::forever('admin_simulator_settings', [
            'dirigeant' => (float) ($this->targetNetDirigeant ?: 0),
            'collab' => (float) ($this->targetNetCollab ?: 0),
            'fixed' => (float) ($this->fixedCosts ?: 0),
            'reserve' => (float) ($this->securityReserve ?: 0),
            'averageOrderPrice' => (float) ($this->averageOrderPrice ?: 0),
            'iaRatio' => (float) ($this->iaRatio ?: 0),
            'isSasu' => (bool) $this->isSasu,
            'isCollabSalaried' => (bool) $this->isCollabSalaried,
            'socialChargeRate' => (float) $this->socialChargeRate,
        ]);
    }

    public function with(): array
    {
        $targetNetDirigeant = (float) ($this->targetNetDirigeant ?: 0);
        $targetNetCollab = (float) ($this->targetNetCollab ?: 0);
        $averageOrderPrice = (float) ($this->averageOrderPrice ?: 0);
        $iaRatio = (float) ($this->iaRatio ?: 0);
        $fixedCosts = (float) ($this->fixedCosts ?: 0);
        $securityReserve = (float) ($this->securityReserve ?: 0);

        // --- CALCUL COUT COLLABORATEUR ---
        $smicNet = 1398.69; // SMIC Net exact 2024 (35h)
        $effectiveNetCollab = $targetNetCollab;
        if ($this->isCollabSalaried && $targetNetCollab > 0 && $targetNetCollab < $smicNet) {
            $effectiveNetCollab = $smicNet;
        }
        
        // Vrai taux collaborateur : 32% au SMIC, remonte vers 80% ensuite
        $collabRate = ($this->isCollabSalaried && $effectiveNetCollab > 0) 
            ? ($effectiveNetCollab <= $smicNet + 10 ? 0.32 : 0.80) 
            : 0;

        $collabTotalCost = 0;
        if ($this->isCollabSalaried) {
            $collabTotalCost = $effectiveNetCollab * (1 + $collabRate);
        } else {
            // Freelance AE : Net / (1 - 0.212)
            $collabTotalCost = $targetNetCollab / (1 - 0.212); 
        }
        
        $ratioIaDecimal = $iaRatio / 100;
        
        // Frais Stripe : 1.5% + 0.25€ / transaction
        $stripePct = 0.015;
        $stripeFixedRatio = $averageOrderPrice > 0 ? (0.25 / $averageOrderPrice) : 0;
        
        // --- LOGIQUE SPECIFIQUE AU REGIME FISCAL ---
        $targetCaTtc = 0;
        $dirigeantTotalCost = 0;
        $additionalFixedCosts = 0;
        $isRate = 0.15; // Provision IS
        $isProvision = 0;
        $effectiveMarginRate = 0;

        if ($this->isSasu) {
            // SASU : Le dirigeant est "assimilé salarié" (Mandataire = ~82% de charges)
            $dirigeantTotalCost = $targetNetDirigeant * 1.82;
            $additionalFixedCosts = 225;
            
            // Calcul de la marge après coûts variables (IA, Stripe)
            $marginAfterVariable = 1 - $ratioIaDecimal - $stripePct - $stripeFixedRatio;
            
            if ($marginAfterVariable > 0) {
                /**
                 * LOGIQUE FISCALE SASU RÉELLE :
                 * Les salaires (Dirigeant + Collab) et les Frais Fixes sont DÉDUCTIBLES.
                 * L'IS (15%) ne s'applique que sur le BÉNÉFICE restant (Security Reserve).
                 * Pour avoir X de réserve NETTE d'impôt, il faut X / 0.85 de bénéfice BRUT.
                 */
                $sumOfDeductibleCosts = $dirigeantTotalCost + $collabTotalCost + $fixedCosts + $additionalFixedCosts;
                $neededProfitBeforeIs = $securityReserve / (1 - $isRate);
                
                $targetCaTtc = ($sumOfDeductibleCosts + $neededProfitBeforeIs) / $marginAfterVariable;
                $isProvision = $neededProfitBeforeIs * $isRate;
            }
            $effectiveMarginRate = $marginAfterVariable;
        } else {
            // MICRO-ENTREPRISE : URSSAF de 21.2% sur le CA TTC (Non déductible)
            $effectiveMarginRate = 1 - 0.212 - $ratioIaDecimal - $stripePct - $stripeFixedRatio;
            if ($effectiveMarginRate > 0) {
                $targetCaTtc = ($targetNetDirigeant + $collabTotalCost + $fixedCosts + $securityReserve) / $effectiveMarginRate;
            }
        }
        
        $targetOrders = $averageOrderPrice > 0 ? ceil($targetCaTtc / $averageOrderPrice) : 0;
        $estimatedStripeFees = ($targetCaTtc * $stripePct) + ($targetOrders * 0.25);

        // --- CALCUL DU CUMUL ANNUEL RÉEL + PROJETÉ ---
        $currentYear = now()->year;
        $currentMonth = now()->month; 
        
        // CA Réel encaissé (De Janvier jusqu'à la FIN DU MOIS DERNIER uniquement)
        $ytdRevenue = \App\Models\Order::whereYear('paid_at', $currentYear)
            ->whereMonth('paid_at', '<', $currentMonth)
            ->where('payment_status', 'paid')
            ->sum('total_price_cents') / 100;

        // Mois à simuler : du mois en cours jusqu'à Décembre
        $remainingMonths = 12 - $currentMonth + 1; 
        
        // Plafond Micro-Entreprise (BNC Prestations)
        $microCeiling = 77700;
        
        // Cumul Annuel Estimé = Déjà encaissé + (Simulé * Mois restants)
        $projectedAnnualRevenue = $ytdRevenue + ($targetCaTtc * $remainingMonths);
        $microUsagePercentage = min(100, ($projectedAnnualRevenue / $microCeiling) * 100);

        return [
            'collabTotalCost' => $collabTotalCost,
            'targetCaTtc' => $targetCaTtc,
            'targetOrders' => $targetOrders,
            'effectiveMarginRate' => $effectiveMarginRate * 100,
            'estimatedStripeFees' => $estimatedStripeFees,
            'safeTargetNetDirigeant' => $targetNetDirigeant,
            'safeTargetNetCollab' => $targetNetCollab,
            'safeIaRatio' => $iaRatio,
            'safeFixedCosts' => $fixedCosts,
            'safeSecurityReserve' => $securityReserve,
            'dirigeantTotalCost' => $dirigeantTotalCost,
            'additionalFixedCosts' => $additionalFixedCosts,
            'isProvision' => $isProvision,
            'projectedAnnualRevenue' => $projectedAnnualRevenue,
            'microUsagePercentage' => $microUsagePercentage,
            'ytdRevenue' => $ytdRevenue,
            'remainingMonths' => $remainingMonths,
            'isSmicWarning' => $this->isCollabSalaried && $targetNetCollab > 0 && $targetNetCollab < $smicNet,
            'smicNet' => $smicNet,
            'collabRate' => $collabRate,
            'collabNet' => $effectiveNetCollab,
            'collabBrut' => $this->isCollabSalaried ? ($effectiveNetCollab / 0.78) : $effectiveNetCollab,
            'collabPatronales' => $this->isCollabSalaried ? ($collabTotalCost - ($effectiveNetCollab / 0.78)) : 0,
            'dirigeantBrut' => $this->isSasu ? ($targetNetDirigeant / 0.78) : $targetNetDirigeant,
            'dirigeantPatronales' => $this->isSasu ? ($dirigeantTotalCost - ($targetNetDirigeant / 0.78)) : 0,
            'lastRealMonthName' => now()->subMonth()->translatedFormat('M'),
        ];
    }
}; ?>

<div class="pb-12">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div>
            <a href="{{ route('admin.revenue') }}" wire:navigate class="text-[#7A6E5E] text-sm hover:text-[#C9A84C] flex items-center gap-2 mb-4 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Retour au Pilotage Financier
            </a>
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-3xl font-bold text-[#F5F0E8]">Simulateur d'Objectifs</h1>
                <span class="px-2.5 py-0.5 rounded-full bg-[#C9A84C]/10 border border-[#C9A84C]/20 text-[#C9A84C] text-[10px] font-bold uppercase tracking-wider">Admin</span>
            </div>
            <p class="text-[#7A6E5E] text-sm max-w-2xl">
                Calculez le chiffre d'affaires nécessaire pour garantir un salaire net au dirigeant et à un collaborateur, en prenant en compte la double imposition URSSAF et les coûts d'IA.
            </p>
        </div>
    </div>

    <div class="flex flex-col gap-12">
        {{-- Row 1: Key Metrics (Top Results) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="card-glass p-8 bg-[#120F0A] border border-[#C9A84C]/30 text-center flex flex-col justify-center overflow-hidden">
                <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Chiffre d'Affaires Cible (TTC)</p>
                <p class="text-[#C9A84C] text-4xl font-black mb-2 whitespace-nowrap">{{ number_format($targetCaTtc, 2, ',', ' ') }} €</p>
                <p class="text-[#7A6E5E] text-sm">à générer sur la plateforme</p>
            </div>
            
            <div class="card-glass p-8 bg-[#120F0A] border border-[#C9A84C]/10 text-center flex flex-col justify-center overflow-hidden">
                <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Volume de Commandes</p>
                <p class="text-[#F5F0E8] text-4xl font-black mb-2 whitespace-nowrap">{{ $targetOrders }} <span class="text-xl font-normal text-[#7A6E5E]">cmd</span></p>
                <p class="text-[#7A6E5E] text-sm">soit environ {{ ceil($targetOrders / 30) }} cmd/jour</p>
            </div>
        </div>

        {{-- Row 2: Inputs Configuration (1, 2, 3) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- 1. Régime Fiscal --}}
            <div class="card-glass p-6 border-t-2 border-t-[#C9A84C]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[#F5F0E8] font-bold">1. Régime Fiscal</h3>
                    <div class="flex bg-[#120F0A] p-1 rounded-sm border border-[#C9A84C]/20">
                        <button wire:click="$set('isSasu', false)" class="px-3 py-1 text-[10px] uppercase tracking-wider transition-all {{ !$isSasu ? 'bg-[#C9A84C] text-[#120F0A] font-bold' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">Micro</button>
                        <button wire:click="$set('isSasu', true)" class="px-3 py-1 text-[10px] uppercase tracking-wider transition-all {{ $isSasu ? 'bg-[#C9A84C] text-[#120F0A] font-bold' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">SASU</button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Salaire Net Dirigeant (€)</label>
                        <input type="number" wire:model.blur="targetNetDirigeant" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                    
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs uppercase tracking-wider text-[#7A6E5E]">Salaire Net Collaborateur (€)</label>
                            <div class="flex bg-[#120F0A] p-0.5 rounded-sm border border-[#C9A84C]/10">
                                <button wire:click="$set('isCollabSalaried', false)" class="px-2 py-0.5 text-[8px] uppercase transition-all {{ !$isCollabSalaried ? 'bg-[#C9A84C]/20 text-[#C9A84C] font-bold' : 'text-[#7A6E5E]' }}">Free</button>
                                <button wire:click="$set('isCollabSalaried', true)" class="px-2 py-0.5 text-[8px] uppercase transition-all {{ $isCollabSalaried ? 'bg-[#C9A84C]/20 text-[#C9A84C] font-bold' : 'text-[#7A6E5E]' }}">CDI</button>
                            </div>
                        </div>
                        <input type="number" wire:model.blur="targetNetCollab" @if($isCollabSalaried) min="1398.69" step="0.01" @endif class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                </div>
            </div>

            {{-- 2. Variables Métier --}}
            <div class="card-glass p-6 border-t-2 border-t-blue-500/50">
                <h3 class="text-[#F5F0E8] font-bold mb-4">2. Variables Métier</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Panier Moyen TTC (€)</label>
                        <input type="number" step="0.5" wire:model.live="averageOrderPrice" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Ratio Coût IA / CA (%)</label>
                        <input type="number" step="0.1" wire:model.live="iaRatio" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                </div>
            </div>

            {{-- 3. Frais de Structure --}}
            <div class="card-glass p-6 border-t-2 border-t-red-500/50">
                <h3 class="text-[#F5F0E8] font-bold mb-4">3. Frais & Sécurité</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Frais fixes & BFR (€)</label>
                        <input type="number" step="10" wire:model.live="fixedCosts" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-red-400/80 mb-1 font-bold">Plafond de Sécurité (€)</label>
                        <input type="number" step="50" wire:model.live="securityReserve" class="w-full bg-[#120F0A] border border-red-500/30 rounded-sm text-red-400 p-2.5 focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all font-bold">
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: Atterrissage Annuel (Full Width) --}}
        @if(!$isSasu)
        <div class="card-glass p-10 border-l-4 {{ $microUsagePercentage > 80 ? 'border-l-red-500 bg-red-900/5' : 'border-l-[#C9A84C] bg-[#C9A84C]/5' }} relative overflow-hidden">
            <div class="flex items-center gap-4 mb-8">
                <div class="p-3 rounded-xl {{ $microUsagePercentage > 80 ? 'bg-red-500/20 text-red-500' : 'bg-[#C9A84C]/20 text-[#C9A84C]' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#F5F0E8] tracking-tight">Atterrissage Annuel & Vigilance Plafond</h3>
                    <p class="text-xs text-[#7A6E5E] font-medium uppercase tracking-widest">Monitoring du seuil fiscal Micro-entreprise : 77 700 €</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-12 items-center">
                {{-- Jauge --}}
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between items-end mb-3">
                            <span class="text-xs uppercase tracking-widest text-[#7A6E5E]">Consommation du Plafond</span>
                            <span class="text-2xl font-black {{ $microUsagePercentage > 80 ? 'text-red-500' : 'text-[#C9A84C]' }}">{{ number_format($microUsagePercentage, 1) }}%</span>
                        </div>
                        <div class="w-full h-4 bg-white/5 rounded-full border border-white/10 p-1 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $microUsagePercentage > 80 ? 'bg-red-600 shadow-[0_0_15px_rgba(220,38,38,0.5)]' : 'bg-[#C9A84C]' }}" style="width: {{ round($microUsagePercentage) }}%"></div>
                        </div>
                    </div>
                    
                    @if($microUsagePercentage > 80)
                    <div class="p-5 bg-red-500/10 border border-red-500/20 rounded-xl flex gap-4">
                        <span class="text-2xl">⚠️</span>
                        <div class="text-xs text-red-200/80 leading-relaxed">
                            <strong class="text-red-500 uppercase block mb-1">Seuil Critique Atteint</strong>
                            Votre trajectoire dépasse les 80% du plafond. Le passage en <strong>SASU</strong> doit être anticipé.
                        </div>
                    </div>
                    @else
                    <div class="p-5 bg-emerald-500/5 border border-emerald-500/10 rounded-xl flex gap-4">
                        <span class="text-emerald-500">🛡️</span>
                        <div class="text-xs text-emerald-200/70 leading-relaxed">
                            <strong class="text-emerald-500 uppercase block mb-1">Zone de Sécurité</strong>
                            Votre volume d'affaires prévisionnel est en adéquation avec le régime Micro.
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Preuve --}}
                <div class="bg-black/20 rounded-lg p-6 border border-white/5">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-[#7A6E5E] font-black mb-4">Preuve par le calcul (annuel)</p>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-[#7A6E5E]">Réel encaissé (Jan à {{ $lastRealMonthName }})</span>
                            <span class="text-[#F5F0E8] font-bold">+ {{ number_format($ytdRevenue, 2, ',', ' ') }} €</span>
                        </div>

                        <div class="flex flex-col space-y-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-[#7A6E5E]">Projection {{ $remainingMonths }} mois</span>
                                <span class="text-[#F5F0E8] font-bold">+ {{ number_format($targetCaTtc * $remainingMonths, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="bg-white/5 rounded px-2 py-1 text-center">
                                <span class="text-[10px] text-[#C9A84C] font-mono font-bold tracking-wider">Détail : {{ $remainingMonths }} mois × {{ number_format($targetCaTtc, 2, ',', ' ') }} €</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-white/10 flex items-center justify-between">
                            <span class="text-[#C9A84C] text-[10px] uppercase font-black">Atterrissage estimé</span>
                            <span class="text-[#C9A84C] font-black text-xl">{{ number_format($projectedAnnualRevenue, 2, ',', ' ') }} €</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Row 4: Focus Collaborateur (New Row) --}}
        <div class="card-glass p-8 border-l-4 {{ $isCollabSalaried ? 'border-l-blue-500 bg-blue-900/5' : 'border-l-[#C9A84C] bg-[#C9A84C]/5' }}">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full {{ $isCollabSalaried ? 'bg-blue-500/20 text-blue-500' : 'bg-[#C9A84C]/20 text-[#C9A84C]' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#F5F0E8]">Focus Collaborateur</h3>
                        <p class="text-xs text-[#7A6E5E] uppercase tracking-widest">Analyse du poste et des charges sociales</p>
                    </div>
                </div>
                <div class="flex items-center gap-8 px-6 py-4 bg-black/30 rounded-xl border border-white/5">
                    <div class="text-center">
                        <p class="text-[10px] text-[#7A6E5E] uppercase mb-1">Salaire Net</p>
                        <p class="text-lg font-black text-[#F5F0E8]">{{ number_format($safeTargetNetCollab, 0, ',', ' ') }} €</p>
                    </div>
                    <div class="h-8 w-px bg-white/10"></div>
                    <div class="text-center">
                        <p class="text-[10px] text-[#7A6E5E] uppercase mb-1">Coût Entreprise</p>
                        <p class="text-lg font-black {{ $isCollabSalaried ? 'text-blue-400' : 'text-[#C9A84C]' }}">{{ number_format($collabTotalCost, 0, ',', ' ') }} €</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 p-4 bg-white/5 rounded-lg border border-white/5">
                <p class="text-sm text-[#7A6E5E] leading-relaxed italic">
                    @if($isCollabSalaried)
                        En tant que salarié (CDI), la société paie <strong>{{ number_format($collabTotalCost, 0, ',', ' ') }} €</strong> pour un net de <strong>{{ number_format($safeTargetNetCollab, 0, ',', ' ') }} €</strong>. 
                        @if($isSmicWarning) <span class="text-emerald-500 font-bold">Calculé sur la base légale du SMIC (1 398,69€ Net).</span> @endif
                        Le taux de charges effectif est de <strong>{{ $collabRate * 100 }}%</strong> après allègements Fillon.
                    @else
                        Le collaborateur facture <strong>{{ number_format($collabTotalCost, 0, ',', ' ') }} €</strong> en tant que Freelance. 
                        Après ses 21,2% d'URSSAF ({{ number_format($collabTotalCost * 0.212, 0, ',', ' ') }} €), son net disponible est de <strong>{{ number_format($safeTargetNetCollab, 0, ',', ' ') }} €</strong>.
                    @endif
                </p>
            </div>
        </div>

        {{-- Row 5: Détail et Preuve par le Calcul (Full Width) --}}
        <div class="card-glass p-8 bg-black/20">
            <h3 class="text-[#F5F0E8] font-bold text-lg mb-8 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Détail et Preuve par le Calcul (Objectif Mensuel)
            </h3>
            
            <div class="max-w-4xl mx-auto space-y-2">
                {{-- Entrée : CA --}}
                <div class="flex items-center justify-between py-4 border-b border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs uppercase tracking-widest text-[#7A6E5E] mb-1 font-bold">Encaissement Brut</span>
                        <span class="text-[#F5F0E8] font-bold">Chiffre d'affaires mensuel cible (TTC)</span>
                    </div>
                    <span class="text-[#F5F0E8] font-black text-xl">+ {{ number_format($targetCaTtc, 2, ',', ' ') }} €</span>
                </div>

                {{-- Bloc Déductions --}}
                <div class="py-6 space-y-4">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-[#7A6E5E] font-black mb-4">Charges & Prélèvements mensuels</p>
                    
                    @if(!$isSasu)
                        <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                            <span class="text-[#7A6E5E] text-sm">URSSAF Plateforme (21,2% du CA)</span>
                            <span class="text-red-400 font-mono">- {{ number_format($targetCaTtc * 0.212, 2, ',', ' ') }} €</span>
                        </div>
                    @else
                        <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                            <span class="text-[#7A6E5E] text-sm">Impôt sur les Sociétés (Provision IS)</span>
                            <span class="text-red-400 font-mono">- {{ number_format($isProvision, 2, ',', ' ') }} €</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                        <span class="text-[#7A6E5E] text-sm">Coûts d'IA (API Photos)</span>
                        <span class="text-red-400 font-mono">- {{ number_format($targetCaTtc * ($safeIaRatio / 100), 2, ',', ' ') }} €</span>
                    </div>

                    <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                        <span class="text-[#7A6E5E] text-sm">Frais Bancaires & Stripe</span>
                        <span class="text-red-400 font-mono">- {{ number_format($estimatedStripeFees, 2, ',', ' ') }} €</span>
                    </div>

                    <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                        <span class="text-[#7A6E5E] text-sm">Frais de Structure & Matelas de Sécurité</span>
                        <span class="text-red-400 font-mono">- {{ number_format($safeFixedCosts + $safeSecurityReserve, 2, ',', ' ') }} €</span>
                    </div>

                    @if($collabTotalCost > 0)
                        <div class="flex items-center justify-between pl-4 border-l-2 border-blue-500/30">
                            <span class="text-[#7A6E5E] text-sm">{{ $isCollabSalaried ? 'Salaire Brut Collaborateur (CDI)' : 'Facture Collaborateur (Freelance)' }}</span>
                            <span class="text-blue-400 font-mono">- {{ number_format($collabBrut, 2, ',', ' ') }} €</span>
                        </div>
                        @if($isCollabSalaried && $collabPatronales > 0)
                            <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                                <span class="text-[#7A6E5E] text-sm">Charges Patronales Collaborateur</span>
                                <span class="text-red-400 font-mono">- {{ number_format($collabPatronales, 2, ',', ' ') }} €</span>
                            </div>
                        @endif
                    @endif

                    @if($isSasu)
                        <div class="flex items-center justify-between pl-4 border-l-2 border-blue-500/30">
                            <span class="text-[#7A6E5E] text-sm">Salaire Brut Dirigeant (Mandataire)</span>
                            <span class="text-blue-400 font-mono">- {{ number_format($dirigeantBrut, 2, ',', ' ') }} €</span>
                        </div>
                        <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                            <span class="text-[#7A6E5E] text-sm">Charges Patronales Dirigeant</span>
                            <span class="text-red-400 font-mono">- {{ number_format($dirigeantPatronales, 2, ',', ' ') }} €</span>
                        </div>
                        <div class="flex items-center justify-between pl-4 border-l-2 border-red-500/30">
                            <span class="text-[#7A6E5E] text-sm">Comptabilité & Frais SASU</span>
                            <span class="text-red-400 font-mono">- {{ number_format($additionalFixedCosts, 2, ',', ' ') }} €</span>
                        </div>
                    @endif
                </div>

                {{-- Résultat Final --}}
                <div class="mt-6 p-6 bg-[#C9A84C] rounded-xl flex items-center justify-between shadow-[0_10px_40px_rgba(201,168,76,0.2)]">
                    <div class="flex flex-col">
                        <span class="text-[#120F0A] text-[10px] uppercase tracking-[0.2em] font-black">Revenu Net Final</span>
                        <span class="text-[#120F0A] font-bold text-lg">Salaire disponible pour le Dirigeant</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[#120F0A] font-black text-4xl block leading-none">{{ number_format($safeTargetNetDirigeant, 2, ',', ' ') }} €</span>
                        <span class="text-[#120F0A]/60 text-[9px] uppercase tracking-widest font-bold">Net après toutes charges</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
