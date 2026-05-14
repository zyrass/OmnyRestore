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

    public function mount()
    {
        $settings = \Illuminate\Support\Facades\Cache::get('admin_simulator_settings', []);
        $this->targetNetDirigeant = $settings['dirigeant'] ?? 2500;
        $this->targetNetCollab = $settings['collab'] ?? 1800;
        $this->fixedCosts = $settings['fixed'] ?? 150;
        
        // Calcul du panier moyen réel sur les 30 derniers jours
        $recentOrders = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays(30))
            ->get();
            
        if ($recentOrders->count() > 0) {
            $totalCaTtc = $recentOrders->sum(fn($o) => $o->total_price_cents + round($o->total_price_cents * 0.2));
            
            // Si le cache ne contient pas les valeurs techniques, on les calcule
            $this->averageOrderPrice = $settings['averageOrderPrice'] ?? round(($totalCaTtc / 100) / $recentOrders->count(), 2);
            
            $totalIaCost = $recentOrders->sum('photo_count') * 15;
            $this->iaRatio = $settings['iaRatio'] ?? round(($totalIaCost / $totalCaTtc) * 100, 1);
        }
    }

    public function updated()
    {
        // Se déclenche à chaque fois qu'un champ 'wire:model.live' est modifié
        \Illuminate\Support\Facades\Cache::forever('admin_simulator_settings', [
            'dirigeant' => (float) ($this->targetNetDirigeant ?: 0),
            'collab' => (float) ($this->targetNetCollab ?: 0),
            'fixed' => (float) ($this->fixedCosts ?: 0),
            'averageOrderPrice' => (float) ($this->averageOrderPrice ?: 0),
            'iaRatio' => (float) ($this->iaRatio ?: 0),
        ]);
    }

    public function with(): array
    {
        $targetNetDirigeant = (float) ($this->targetNetDirigeant ?: 0);
        $targetNetCollab = (float) ($this->targetNetCollab ?: 0);
        $averageOrderPrice = (float) ($this->averageOrderPrice ?: 0);
        $iaRatio = (float) ($this->iaRatio ?: 0);
        $fixedCosts = (float) ($this->fixedCosts ?: 0);

        // Le collab (BNC à 21.2%) doit facturer:
        $collabInvoice = $targetNetCollab / (1 - 0.212); 
        
        $ratioIaDecimal = $iaRatio / 100;
        
        // Frais Stripe : 1.5% + 0.25€ / transaction
        $stripePct = 0.015;
        $stripeFixedRatio = $averageOrderPrice > 0 ? (0.25 / $averageOrderPrice) : 0;
        
        // Marge effective = 100% - URSSAF(21.2%) - IA(%) - Stripe(1.5%) - StripeFixe(rapporté au panier moyen)
        $effectiveMarginRate = 1 - 0.212 - $ratioIaDecimal - $stripePct - $stripeFixedRatio;
        
        $targetCaTtc = 0;
        if ($effectiveMarginRate > 0) {
            // CA_TTC * MargeEffective = DirigeantNet + CollabInvoice + FixedCosts
            $targetCaTtc = ($targetNetDirigeant + $collabInvoice + $fixedCosts) / $effectiveMarginRate;
        }
        
        $targetOrders = $averageOrderPrice > 0 ? ceil($targetCaTtc / $averageOrderPrice) : 0;
        $estimatedStripeFees = ($targetCaTtc * $stripePct) + ($targetOrders * 0.25);

        return [
            'collabInvoice' => $collabInvoice,
            'targetCaTtc' => $targetCaTtc,
            'targetOrders' => $targetOrders,
            'effectiveMarginRate' => $effectiveMarginRate * 100,
            'estimatedStripeFees' => $estimatedStripeFees,
            'safeTargetNetDirigeant' => $targetNetDirigeant,
            'safeTargetNetCollab' => $targetNetCollab,
            'safeIaRatio' => $iaRatio,
            'safeFixedCosts' => $fixedCosts,
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Colonne Formulaire --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="card-glass p-6 border-t-2 border-t-[#C9A84C]">
                <h3 class="text-[#F5F0E8] font-bold mb-4">Objectifs de Rémunération</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Salaire Net Dirigeant (€)</label>
                        <input type="number" wire:model.live="targetNetDirigeant" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Salaire Net Collaborateur (€)</label>
                        <input type="number" wire:model.live="targetNetCollab" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                    </div>
                </div>
            </div>

            <div class="card-glass p-6">
                <h3 class="text-[#F5F0E8] font-bold mb-4">Variables Métier</h3>
                
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

            <div class="card-glass p-6">
                <h3 class="text-[#F5F0E8] font-bold mb-4">Frais de Structure & Sécurité</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-[#7A6E5E] mb-1">Frais fixes & BFR (€)</label>
                        <input type="number" step="10" wire:model.live="fixedCosts" class="w-full bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm text-[#F5F0E8] p-2.5 focus:border-[#C9A84C] focus:ring-1 focus:ring-[#C9A84C] transition-all">
                        <p class="text-[#7A6E5E] text-[10px] mt-1.5 leading-relaxed">Sert à payer les serveurs, le nom de domaine, ou à garder un matelas de sécurité en banque.</p>
                    </div>

                    <div class="p-3 bg-[#1A160F] border border-[#C9A84C]/10 rounded-sm">
                        <p class="text-[#7A6E5E] text-xs font-semibold mb-1">Frais Stripe estimés :</p>
                        <p class="text-red-400 font-bold">{{ number_format($estimatedStripeFees, 2, ',', ' ') }} €</p>
                        <p class="text-[#7A6E5E] text-[10px] mt-1">Calculé auto. (1,5% + 0,25€/cmd)</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Colonne Résultats --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="card-glass p-8 bg-[#120F0A] border border-[#C9A84C]/30 text-center flex flex-col justify-center">
                    <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Chiffre d'Affaires Cible (TTC)</p>
                    <p class="text-[#C9A84C] text-5xl font-black mb-2">{{ number_format($targetCaTtc, 2, ',', ' ') }} €</p>
                    <p class="text-[#7A6E5E] text-sm">à générer sur la plateforme</p>
                </div>
                
                <div class="card-glass p-8 bg-[#120F0A] border border-[#C9A84C]/10 text-center flex flex-col justify-center">
                    <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Volume de Commandes</p>
                    <p class="text-[#F5F0E8] text-5xl font-black mb-2">{{ $targetOrders }} <span class="text-xl font-normal text-[#7A6E5E]">cmd</span></p>
                    <p class="text-[#7A6E5E] text-sm">soit environ {{ ceil($targetOrders / 30) }} cmd/jour</p>
                </div>
            </div>

            <div class="card-glass p-8 bg-black/20">
                <h3 class="text-[#F5F0E8] font-bold text-lg mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Détail et Preuve par le Calcul
                </h3>
                
                <div class="space-y-4">
                    {{-- Ligne CA --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5">
                        <span class="text-[#7A6E5E]">Chiffre d'affaires encaissé (TTC)</span>
                        <span class="text-[#F5F0E8] font-bold">{{ number_format($targetCaTtc, 2, ',', ' ') }} €</span>
                    </div>
                    
                    {{-- Ligne URSSAF Plateforme --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5 pl-4 relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-px bg-red-500/50"></div>
                        <span class="text-[#7A6E5E] text-sm">URSSAF Plateforme (21,2% du TTC)</span>
                        <span class="text-red-400 font-medium">- {{ number_format($targetCaTtc * 0.212, 2, ',', ' ') }} €</span>
                    </div>
                    
                    {{-- Ligne Coûts IA --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5 pl-4 relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-px bg-red-500/50"></div>
                        <span class="text-[#7A6E5E] text-sm">Coûts d'IA ({{ $safeIaRatio }}%)</span>
                        <span class="text-red-400 font-medium">- {{ number_format($targetCaTtc * ($safeIaRatio / 100), 2, ',', ' ') }} €</span>
                    </div>

                    {{-- Ligne Stripe --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5 pl-4 relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-px bg-red-500/50"></div>
                        <span class="text-[#7A6E5E] text-sm">Frais Stripe</span>
                        <span class="text-red-400 font-medium">- {{ number_format($estimatedStripeFees, 2, ',', ' ') }} €</span>
                    </div>

                    {{-- Ligne Serveurs / Fonds --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5 pl-4 relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-px bg-red-500/50"></div>
                        <span class="text-[#7A6E5E] text-sm">Frais Fixes & BFR</span>
                        <span class="text-red-400 font-medium">- {{ number_format($safeFixedCosts, 2, ',', ' ') }} €</span>
                    </div>
                    
                    {{-- Ligne Facture Collab --}}
                    <div class="flex items-center justify-between py-3 border-b border-white/5 pl-4 relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-px bg-red-500/50"></div>
                        <span class="text-[#7A6E5E] text-sm">Facture du Collaborateur</span>
                        <span class="text-red-400 font-medium">- {{ number_format($collabInvoice, 2, ',', ' ') }} €</span>
                    </div>

                    {{-- Résultat Dirigeant --}}
                    <div class="flex items-center justify-between py-4 mt-2">
                        <span class="text-[#F5F0E8] font-bold">Reste Net pour le Dirigeant</span>
                        <span class="text-emerald-400 font-bold text-xl">{{ number_format($safeTargetNetDirigeant, 2, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>

            <div class="card-glass p-6 border-l-4 border-l-blue-500 bg-blue-900/10">
                <h4 class="text-blue-400 font-bold text-sm mb-2">Et pour le collaborateur ?</h4>
                <p class="text-[#7A6E5E] text-sm leading-relaxed">
                    Le collaborateur facture <strong>{{ number_format($collabInvoice, 2, ',', ' ') }} €</strong> à la plateforme. En tant qu'auto-entrepreneur, il paie lui-même 21,2% d'URSSAF sur cette facture ({{ number_format($collabInvoice * 0.212, 2, ',', ' ') }} €). Il lui reste donc exactement <strong>{{ number_format($safeTargetNetCollab, 2, ',', ' ') }} €</strong> net dans sa poche.
                </p>
            </div>
        </div>
    </div>
</div>
