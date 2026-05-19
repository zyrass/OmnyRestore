<?php
/**
 * Admin — Codes de réduction (Coupons)
 * Route: GET /admin/coupons
 * Middleware: auth, verified, admin
 */

use App\Models\Coupon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Codes de réduction — Admin')]
class extends Component
{
    // ── Formulaire de création ───────────────────────────────────────────────
    public string $code        = '';
    public string $description = '';
    public string $type        = 'percentage';
    public int    $value       = 10;
    public int    $min_order   = 0;
    public ?int   $max_uses    = null;
    public string $expires_at  = '';
    public string $starts_at   = '';
    public bool   $is_seasonal = false;
    public bool   $showForm    = false;
    public string $activeTab   = 'loyalty';

    // ── Données ────────────────────────────────────────────────────────────
    public function with(): array
    {
        $query = Coupon::query()->with(['user' => fn($u) => $u->withTrashed()]);

        if ($this->activeTab === 'loyalty') {
            $query->where('is_loyalty', true);
        } elseif ($this->activeTab === 'seasonal') {
            $query->where('is_seasonal', true)->where('is_loyalty', false);
        } else {
            $query->where('is_seasonal', false)->where('is_loyalty', false);
        }

        return [
            'coupons'        => $query->latest()->get(),
            'loyalty_count'  => Coupon::where('is_loyalty', true)->count(),
            'seasonal_count' => Coupon::where('is_seasonal', true)->where('is_loyalty', false)->count(),
            'promo_count'    => Coupon::where('is_seasonal', false)->where('is_loyalty', false)->count(),
        ];
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function openForm(): void
    {
        $this->showForm = true;
        $this->is_seasonal = ($this->activeTab === 'seasonal');
    }

    public function createCoupon(): void
    {
        $this->validate([
            'code'        => 'required|string|min:3|max:32|unique:coupons,code|alpha_dash',
            'type'        => 'required|in:percentage,fixed',
            'value'       => 'required|integer|min:1|max:10000',
            'min_order'   => 'integer|min:0',
            'max_uses'    => 'nullable|integer|min:1',
            'expires_at'  => $this->is_seasonal ? 'required|date' : 'required|date|after_or_equal:starts_at',
            'starts_at'   => $this->is_seasonal ? 'required|date' : 'nullable|date',
            'is_seasonal' => 'boolean',
            'description' => 'nullable|string|max:255',
        ], [
            'code.unique'     => 'Ce code existe déjà.',
            'code.alpha_dash' => 'Le code ne doit contenir que des lettres, chiffres et tirets.',
            'expires_at.after'=> 'La date d\'expiration doit être dans le futur.',
        ]);

        $is_seasonal_saved = $this->is_seasonal;

        Coupon::create([
            'code'            => strtoupper(trim($this->code)),
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'value'           => $this->value,
            'min_order_cents' => $this->min_order * 100,
            'max_uses'        => $this->max_uses,
            'starts_at'       => $this->starts_at ?: null,
            'expires_at'      => $this->expires_at ?: null,
            'is_active'       => true,
            'is_seasonal'     => $is_seasonal_saved,
            'is_loyalty'      => false,
        ]);

        $this->reset(['code', 'description', 'type', 'value', 'min_order', 'max_uses', 'expires_at', 'starts_at', 'is_seasonal']);
        $this->type = 'percentage';
        $this->value = 10;
        $this->showForm = false;
        $this->activeTab = $is_seasonal_saved ? 'seasonal' : 'promo';

        session()->flash('success', 'Code de réduction créé avec succès.');
    }

    public function toggleCoupon(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $status = $coupon->is_active ? 'activé' : 'désactivé';
        session()->flash('success', "Code « {$coupon->code} » {$status}.");
    }

    public function deleteCoupon(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();
        session()->flash('success', "Code « {$code} » supprimé.");
    }
}; ?>

<div>
    {{-- Messages flash --}}
    @if (session('success'))
    <div class="mb-6 flex items-center gap-3 bg-emerald-900/30 border border-emerald-500/30 text-emerald-400 text-sm px-4 py-3 rounded-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Codes de réduction</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">
                @if ($activeTab === 'loyalty')
                    {{ $loyalty_count }} bon{{ $loyalty_count > 1 ? 's' : '' }} fidélité (auto)
                @elseif ($activeTab === 'seasonal')
                    {{ $seasonal_count }} bon{{ $seasonal_count > 1 ? 's' : '' }} saisonnier{{ $seasonal_count > 1 ? 's' : '' }}
                @else
                    {{ $promo_count }} code{{ $promo_count > 1 ? 's' : '' }} promo
                @endif
                au total
            </p>
        </div>
        <button wire:click="openForm"
                class="px-4 py-2 text-sm font-semibold bg-[#C9A84C] text-black rounded-sm hover:bg-[#D4B55F] transition-colors">
            + Nouveau code
        </button>
    </div>

    {{-- Formulaire de création --}}
    @if ($showForm)
    <div class="card-glass p-6 mb-8 border border-[#C9A84C]/20">
        <h2 class="text-[#F5F0E8] font-semibold mb-5 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Créer un code de réduction
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- Code --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Code *</label>
                <input wire:model="code" type="text" placeholder="EX: BIENVENUE10"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 uppercase focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                @error('code') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Type --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Type *</label>
                <select wire:model="type"
                        class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                    <option value="percentage">Pourcentage (%)</option>
                    <option value="fixed">Montant fixe (€ HT)</option>
                </select>
            </div>

            {{-- Valeur --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">
                    Valeur * {{ $type === 'percentage' ? '(%)' : '(centimes HT)' }}
                </label>
                <input wire:model="value" type="number" min="1" max="{{ $type === 'percentage' ? 100 : 10000 }}"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                @if ($type === 'percentage')
                <p class="text-[#7A6E5E] text-xs mt-1">Ex: 10 = 10% de réduction</p>
                @else
                <p class="text-[#7A6E5E] text-xs mt-1">Ex: 50 = 0,50 € de réduction HT</p>
                @endif
                @error('value') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Min commande --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Commande minimum (€ HT)</label>
                <input wire:model="min_order" type="number" min="0" placeholder="0 = pas de minimum"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
            </div>

            {{-- Max utilisations --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Utilisations max</label>
                <input wire:model="max_uses" type="number" min="1" placeholder="Vide = illimité"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
            </div>

            {{-- Debut validite --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">
                    {{ $is_seasonal ? 'Début (Jour/Mois)' : 'Date de début' }}
                </label>
                <input wire:model="starts_at" type="date"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                @if($is_seasonal) <p class="text-[#7A6E5E] text-[10px] mt-1">L'année sera ignorée.</p> @endif
                @error('starts_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Expiration --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">
                    {{ $is_seasonal ? 'Fin (Jour/Mois)' : "Date d'expiration" }}
                </label>
                <input wire:model="expires_at" type="date"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                @if($is_seasonal) <p class="text-[#7A6E5E] text-[10px] mt-1">L'année sera ignorée.</p> @endif
                @error('expires_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Description --}}
            <div class="md:col-span-1 lg:col-span-2">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Description (usage interne)</label>
                <input wire:model="description" type="text" placeholder="Ex: Offre de Noël récurrente"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
            </div>

            {{-- Seasonal Toggle --}}
            <div class="flex items-center md:col-span-1 lg:col-span-1 pt-6">
                <label class="flex items-center cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" wire:model.live="is_seasonal" class="sr-only">
                        <div @class([
                            'w-10 h-5 border border-[#C9A84C]/30 rounded-full transition-all duration-200',
                            'bg-[#C9A84C]' => $is_seasonal,
                            'bg-[#1A1510]' => !$is_seasonal,
                        ])></div>
                        <div @class([
                            'absolute top-1 w-3 h-3 rounded-full transition-all duration-200',
                            'left-6 bg-black' => $is_seasonal,
                            'left-1 bg-[#7A6E5E]' => !$is_seasonal,
                        ])></div>
                    </div>
                    <span @class([
                        'ms-3 text-[10px] font-bold uppercase tracking-widest transition-colors',
                        'text-[#C9A84C]' => $is_seasonal,
                        'text-[#7A6E5E]' => !$is_seasonal,
                    ])>Récurrent chaque année</span>
                </label>
            </div>
        </div>

        <div class="flex gap-3 mt-5">
            <button wire:click="createCoupon" wire:loading.attr="disabled"
                    class="px-5 py-2 text-sm font-semibold bg-[#C9A84C] text-black rounded-sm hover:bg-[#D4B55F] transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="createCoupon">✓ Créer le code</span>
                <span wire:loading wire:target="createCoupon">Création…</span>
            </button>
            <button wire:click="$set('showForm', false)"
                    class="px-5 py-2 text-sm border border-[#C9A84C]/20 text-[#7A6E5E] rounded-sm hover:text-[#F5F0E8] hover:border-[#C9A84C]/40 transition-colors">
                Annuler
            </button>
        </div>
    </div>
    @endif

    {{-- Onglets --}}
    <div class="flex gap-2 p-1 bg-[#1A1510]/60 border border-[#C9A84C]/10 rounded-sm w-fit mb-6">
        <button wire:click="$set('activeTab', 'loyalty')"
                class="px-4 py-1.5 rounded-sm text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2
                       {{ $activeTab === 'loyalty' ? 'bg-[#C9A84C]/10 text-[#C9A84C] border border-[#C9A84C]/25 shadow-[0_0_15px_rgba(201,168,76,0.1)]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 border border-transparent' }}">
            <span>🎁 Bons Fidélité (Auto)</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C9A84C]/10 text-[#C9A84C]">
                {{ $loyalty_count }}
            </span>
        </button>
        <button wire:click="$set('activeTab', 'seasonal')"
                class="px-4 py-1.5 rounded-sm text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2
                       {{ $activeTab === 'seasonal' ? 'bg-[#C9A84C]/10 text-[#C9A84C] border border-[#C9A84C]/25 shadow-[0_0_15px_rgba(201,168,76,0.1)]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 border border-transparent' }}">
            <span>⏳ Bons Saisonniers</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C9A84C]/10 text-[#C9A84C]">
                {{ $seasonal_count }}
            </span>
        </button>
        <button wire:click="$set('activeTab', 'promo')"
                class="px-4 py-1.5 rounded-sm text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2
                       {{ $activeTab === 'promo' ? 'bg-[#C9A84C]/10 text-[#C9A84C] border border-[#C9A84C]/25 shadow-[0_0_15px_rgba(201,168,76,0.1)]' : 'text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 border border-transparent' }}">
            <span>🎟 Codes Promos</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C9A84C]/10 text-[#C9A84C]">
                {{ $promo_count }}
            </span>
        </button>
    </div>

    {{-- Table des coupons --}}
    <div class="card-glass overflow-hidden">
        @if ($coupons->isEmpty())
        <div class="px-6 py-16 text-center flex flex-col items-center justify-center" style="min-height: 400px;">
            <p class="text-[#7A6E5E]">Aucun code de réduction dans cette catégorie.</p>
        </div>
        @else
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    <th class="px-5 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Code</th>
                    <th class="px-4 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Réduction</th>
                    <th class="px-4 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Utilisations</th>
                    <th class="px-4 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Expiration</th>
                    <th class="px-4 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Statut</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/5">
                @foreach ($coupons as $coupon)
                @php
                    $now = now();
                    $todayMd = $now->format('m-d');
                    
                    if ($coupon->is_seasonal) {
                        $startMd = $coupon->starts_at->format('m-d');
                        $endMd = $coupon->expires_at->format('m-d');
                        
                        if ($startMd <= $endMd) {
                            $isCurrent = ($todayMd >= $startMd && $todayMd <= $endMd);
                        } else {
                            // Chevauchement année (ex: 12-15 au 01-01)
                            $isCurrent = ($todayMd >= $startMd || $todayMd <= $endMd);
                        }
                        $isValid = $coupon->is_active && $isCurrent && (! $coupon->max_uses || $coupon->used_count < $coupon->max_uses);
                        $isFuture = $coupon->is_active && ! $isCurrent;
                    } else {
                        $isValid = $coupon->is_active
                            && (! $coupon->starts_at || $coupon->starts_at->isPast() || $coupon->starts_at->isToday())
                            && (! $coupon->expires_at || $coupon->expires_at->isFuture())
                            && (! $coupon->max_uses || $coupon->used_count < $coupon->max_uses);

                        $isFuture = $coupon->is_active && $coupon->starts_at && $coupon->starts_at->isFuture();
                    }
                @endphp
                <tr class="hover:bg-[#C9A84C]/3 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="font-mono text-[#C9A84C] font-semibold flex flex-wrap items-center gap-2">
                            <span>{{ $coupon->code }}</span>
                            @if ($coupon->is_loyalty && $coupon->user)
                                <span class="text-[10px] bg-purple-950/40 text-purple-400 border border-purple-500/20 px-2 py-0.5 rounded-full font-sans font-normal tracking-wide">
                                    Client : {{ $coupon->user->name }}
                                </span>
                            @endif
                        </div>
                        @if ($coupon->description)
                        <div class="text-[#7A6E5E] text-xs mt-0.5">{{ $coupon->description }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="font-semibold text-[#F5F0E8]">{{ $coupon->discount_label }}</span>
                        @if ($coupon->min_order_cents > 0)
                        <div class="text-[#7A6E5E] text-xs mt-0.5">min. {{ number_format($coupon->min_order_cents / 100, 2, ',', ' ') }} € HT</div>
                        @endif
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="text-[#F5F0E8] text-sm">{{ $coupon->used_count }}</span>
                        @if ($coupon->max_uses)
                        <span class="text-[#7A6E5E] text-xs"> / {{ $coupon->max_uses }}</span>
                        @else
                        <span class="text-[#7A6E5E] text-xs"> / ∞</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-sm text-[#7A6E5E]">
                        @if ($coupon->is_seasonal)
                            <div class="flex items-center gap-1.5 text-[#C9A84C]/80">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>{{ $coupon->starts_at->format('d/m') }} au {{ $coupon->expires_at->format('d/m') }}</span>
                            </div>
                        @elseif ($coupon->expires_at)
                            <span class="{{ $coupon->expires_at->isPast() ? 'text-red-400' : '' }}">
                                {{ $coupon->expires_at->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-[#7A6E5E]/40">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5">
                        @if ($isValid)
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full bg-emerald-900/40 text-emerald-400 border-emerald-500/30">
                            Actif
                        </span>
                        @elseif ($isFuture)
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full bg-blue-900/40 text-blue-400 border-blue-500/30">
                            {{ $coupon->is_seasonal ? 'En attente' : 'Planifié' }}
                        </span>
                        @elseif (! $coupon->is_active)
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full bg-[#1A1510] text-[#7A6E5E] border-[#7A6E5E]/20">
                            Désactivé
                        </span>
                        @elseif ($coupon->expires_at?->isPast())
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full bg-red-900/40 text-red-400 border-red-500/30">
                            Expiré
                        </span>
                        @else
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full bg-orange-900/40 text-orange-400 border-orange-500/30">
                            Épuisé
                        </span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button wire:click="toggleCoupon({{ $coupon->id }})"
                                    class="text-xs px-2.5 py-1 border rounded-sm transition-all
                                           {{ $coupon->is_active ? 'border-orange-500/30 text-orange-400 hover:bg-orange-900/20' : 'border-emerald-500/30 text-emerald-400 hover:bg-emerald-900/20' }}">
                                {{ $coupon->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                            <button @click="const wire = $wire; omnyConfirm({
                                         title: 'Supprimer Code',
                                         message: 'Voulez-vous vraiment supprimer le code de réduction « {{ $coupon->code }} » ? Cette action est définitive.',
                                         confirmLabel: '🗑 Supprimer',
                                         danger: true
                                     }).then(() => wire.deleteCoupon({{ $coupon->id }}))"
                                    class="text-xs px-2.5 py-1 border border-red-500/30 text-red-400 hover:bg-red-900/20 rounded-sm transition-all">
                                Suppr.
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
