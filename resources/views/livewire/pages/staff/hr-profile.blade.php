<?php
/**
 * Staff — Espace RH Personnel
 * Route: GET /staff/hr-profile
 *
 * Espace personnel du collaborateur pour visualiser ses informations contractuelles,
 * télécharger ses fiches de paie et visualiser l'évolution de son salaire.
 */

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\SalaryHistory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new 
#[Layout('layouts.app')]
#[Title('Mon Espace Personnel')]
class extends Component {
    public User $user;
    public array $salaryHistory = [];
    public float $smic2026 = 1460.00; // Estimation SMIC net mensuel pour 2026
    
    public function mount(): void
    {
        $this->user = Auth::user();
        
        // Ensure user is staff
        if (!in_array($this->user->role, ['super-admin', 'operator', 'marketing', 'rh'])) {
            abort(403, 'Accès refusé.');
        }

        // Fetch salary history
        $history = SalaryHistory::where('user_id', $this->user->id)->orderBy('effective_date', 'asc')->get();
        
        // Calculate max salary for chart scaling
        $maxSalary = $history->max('new_salary') ?? $this->user->net_salary ?? 0;
        
        // Add current salary if history is empty but user has a salary
        if ($history->isEmpty() && $this->user->net_salary) {
            $this->salaryHistory[] = [
                'date' => $this->user->hire_date ? $this->user->hire_date->format('M Y') : now()->format('M Y'),
                'amount' => $this->user->net_salary,
                'height' => 100 // 100% height since it's the only value
            ];
        } else {
            foreach ($history as $record) {
                $height = $maxSalary > 0 ? ($record->new_salary / $maxSalary) * 100 : 0;
                // Minimum height for visibility
                $height = max(10, $height);
                
                $this->salaryHistory[] = [
                    'date' => Carbon::parse($record->effective_date)->format('M Y'),
                    'amount' => $record->new_salary,
                    'height' => $height
                ];
            }
        }
    }
};
?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-2 flex items-center gap-3">
            <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            {{ Auth::user()->role === 'super-admin' ? 'Mon Espace Admin' : (Auth::user()->role === 'operator' ? 'Mon Espace Opérateur' : (Auth::user()->role === 'rh' ? 'Mon Espace RH' : 'Mon Espace Marketing')) }}
        </h1>
        <p class="text-[#7A6E5E] text-sm">Consultez vos informations contractuelles, vos fiches de paie et l'évolution de votre rémunération.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Colonne Principale --}}
        <div class="lg:col-span-2 space-y-8">
            
            {{-- Informations Contrat --}}
            <div class="card-glass p-6 sm:p-8 border-[#C9A84C]/10 bg-[#0F0C08]/50">
                <h2 class="text-xs uppercase tracking-wider font-bold text-[#C9A84C] mb-6 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Informations Contractuelles
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <span class="text-[10px] text-[#7A6E5E] uppercase tracking-widest block mb-1">Type de Contrat</span>
                        <div class="text-lg font-bold text-[#F5F0E8]">{{ $user->contract_type ?? 'Non défini' }}</div>
                    </div>
                    <div>
                        <span class="text-[10px] text-[#7A6E5E] uppercase tracking-widest block mb-1">Date d'Entrée</span>
                        <div class="text-lg font-bold text-[#F5F0E8]">{{ $user->hire_date ? $user->hire_date->format('d/m/Y') : 'Non définie' }}</div>
                    </div>
                    
                    @if($user->contract_type === 'CDD' && $user->contract_end_date)
                    <div class="sm:col-span-2 bg-orange-950/20 border border-orange-500/30 p-4 rounded-sm flex items-start gap-4 mt-2">
                        <div class="mt-0.5 text-orange-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <span class="text-orange-400 font-bold text-sm block mb-1">Fin de contrat prévue</span>
                            <span class="text-orange-200/80 text-xs block">Votre CDD actuel prendra fin le <strong>{{ $user->contract_end_date->format('d/m/Y') }}</strong>. Pour toute question, veuillez contacter les Ressources Humaines.</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Evolution KPI (Graphique CSS) --}}
            <div class="card-glass p-6 sm:p-8 border-[#C9A84C]/10 bg-[#0F0C08]/50">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-xs uppercase tracking-wider font-bold text-[#C9A84C] flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Évolution du Salaire Net
                    </h2>
                    <div class="text-right">
                        <span class="text-[10px] text-[#7A6E5E] uppercase tracking-widest block mb-0.5">Salaire Actuel</span>
                        <div class="text-xl font-mono font-bold text-[#C9A84C]">{{ $user->net_salary ? number_format($user->net_salary, 2, ',', ' ') . ' €' : '--' }}</div>
                    </div>
                </div>

                @if(count($salaryHistory) > 0)
                <div class="h-48 flex items-end justify-between gap-2 mt-4 px-2 pb-2 border-b border-[#C9A84C]/20">
                    @foreach($salaryHistory as $data)
                    <div class="w-full max-w-[40px] flex flex-col items-center gap-2 group relative">
                        {{-- Tooltip au survol --}}
                        <div class="absolute -top-10 bg-[#1A1510] border border-[#C9A84C]/30 text-[#C9A84C] text-[10px] font-mono font-bold px-2 py-1 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                            {{ number_format($data['amount'], 0, ',', ' ') }} €
                        </div>
                        {{-- Barre --}}
                        <div class="w-full bg-gradient-to-t from-[#C9A84C]/20 to-[#C9A84C] rounded-t-sm transition-all duration-500 hover:brightness-125" style="height: {{ $data['height'] }}%;"></div>
                    </div>
                    @endforeach
                </div>
                {{-- Labels X --}}
                <div class="flex items-center justify-between gap-2 px-2 mt-2">
                    @foreach($salaryHistory as $data)
                    <div class="w-full max-w-[40px] text-center text-[9px] text-[#7A6E5E] uppercase tracking-widest transform -rotate-45 origin-top-left mt-2">
                        {{ $data['date'] }}
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-12 flex flex-col items-center justify-center text-center opacity-50">
                    <svg class="w-12 h-12 text-[#7A6E5E] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <p class="text-xs text-[#7A6E5E]">Aucune donnée d'historique de salaire disponible.</p>
                </div>
                @endif
                
                {{-- SMIC Validation pour CDI --}}
                @if($user->contract_type === 'CDI' && $user->net_salary)
                <div class="mt-12">
                    @if($user->net_salary >= $this->smic2026)
                    <div class="bg-emerald-950/20 border border-emerald-500/30 p-4 rounded-sm flex items-start gap-4">
                        <div class="mt-0.5 text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <span class="text-emerald-400 font-bold text-sm block mb-1">Conformité Rémunération 2026</span>
                            <span class="text-emerald-200/80 text-xs block">Votre salaire net ({{ number_format($user->net_salary, 2, ',', ' ') }} €) est conforme au seuil légal estimé (≥ {{ number_format($this->smic2026, 2, ',', ' ') }} €).</span>
                        </div>
                    </div>
                    @else
                    <div class="bg-red-950/20 border border-red-500/30 p-4 rounded-sm flex items-start gap-4">
                        <div class="mt-0.5 text-red-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <span class="text-red-400 font-bold text-sm block mb-1">Alerte Rémunération 2026</span>
                            <span class="text-red-200/80 text-xs block">Votre salaire net ({{ number_format($user->net_salary, 2, ',', ' ') }} €) est inférieur au seuil légal estimé ({{ number_format($this->smic2026, 2, ',', ' ') }} €). Veuillez contacter les Ressources Humaines.</span>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            
        </div>

        {{-- Colonne Latérale: Documents & Transparence --}}
        <div class="space-y-6">
            
            {{-- Encadrement Transparence Salariale --}}
            <div class="card-glass border-[#C9A84C]/20 bg-gradient-to-br from-[#0F0C08] to-[#1A1510] overflow-hidden group">
                <a href="{{ route('admin.transparency.index') }}" wire:navigate class="block p-6 transition-all hover:bg-[#C9A84C]/5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xs uppercase tracking-wider font-bold text-[#F5F0E8] flex items-center gap-2">
                            <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                            Transparence Salariale
                        </h2>
                        <svg class="w-4 h-4 text-[#7A6E5E] group-hover:text-[#C9A84C] group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                    <p class="text-[11px] text-[#7A6E5E] leading-relaxed mb-4">
                        Consultez la grille des salaires de l'équipe conformément à la Directive UE 2023/970 sur l'équité salariale.
                    </p>
                    <div class="inline-flex items-center gap-2 text-[10px] uppercase tracking-widest font-bold text-[#C9A84C]">
                        Accéder au tableau de bord
                    </div>
                </a>
            </div>

            <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/50 overflow-hidden">
                <div class="p-6 border-b border-[#C9A84C]/10">
                    <h2 class="text-xs uppercase tracking-wider font-bold text-[#F5F0E8] flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Documents & Contrats
                    </h2>
                </div>
                <div class="p-2 space-y-1">
                    {{-- CG --}}
                    <button class="w-full flex items-center justify-between p-3 hover:bg-[#C9A84C]/5 rounded-sm transition-colors text-left group">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-[#C9A84C]/10 text-[#C9A84C] rounded-sm group-hover:bg-[#C9A84C] group-hover:text-[#1A1510] transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-[#F5F0E8] block">Conditions Générales</span>
                                <span class="text-[10px] text-[#7A6E5E]">Règlement intérieur</span>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-[#7A6E5E] group-hover:text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </button>
                    
                    {{-- Contrat Actuel --}}
                    <button disabled class="w-full flex items-center justify-between p-3 bg-white/5 opacity-50 cursor-not-allowed rounded-sm text-left group" title="Intégration PayFit à venir">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-[#7A6E5E]/20 text-[#7A6E5E] rounded-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-[#F5F0E8] block">Mon Contrat ({{ $user->contract_type ?? 'N/A' }})</span>
                                <span class="text-[10px] text-purple-400">Génération PayFit en attente</span>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-[#7A6E5E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </button>
                </div>
            </div>
            
            <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/50 overflow-hidden">
                <div class="p-6 border-b border-[#C9A84C]/10">
                    <h2 class="text-xs uppercase tracking-wider font-bold text-[#F5F0E8] flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Mes Fiches de Paie
                    </h2>
                </div>
                <div class="p-6 text-center">
                    <svg class="w-12 h-12 text-[#7A6E5E]/50 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <p class="text-xs text-[#7A6E5E] leading-relaxed">
                        L'intégration avec <strong>PayFit</strong> est en cours de configuration par le pôle RH.<br><br>Vos fiches de paie seront disponibles ici très prochainement.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
