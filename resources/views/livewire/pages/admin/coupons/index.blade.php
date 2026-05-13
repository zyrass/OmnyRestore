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
    public bool   $showForm    = false;

    // ── Données ────────────────────────────────────────────────────────────
    public function with(): array
    {
        return [
            'coupons' => Coupon::latest()->get(),
        ];
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function createCoupon(): void
    {
        $this->validate([
            'code'        => 'required|string|min:3|max:32|unique:coupons,code|alpha_dash',
            'type'        => 'required|in:percentage,fixed',
            'value'       => 'required|integer|min:1|max:10000',
            'min_order'   => 'integer|min:0',
            'max_uses'    => 'nullable|integer|min:1',
            'expires_at'  => 'nullable|date|after:today',
            'description' => 'nullable|string|max:255',
        ], [
            'code.unique'     => 'Ce code existe déjà.',
            'code.alpha_dash' => 'Le code ne doit contenir que des lettres, chiffres et tirets.',
            'expires_at.after'=> 'La date d\'expiration doit être dans le futur.',
        ]);

        Coupon::create([
            'code'            => strtoupper(trim($this->code)),
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'value'           => $this->value,
            'min_order_cents' => $this->min_order * 100,
            'max_uses'        => $this->max_uses,
            'expires_at'      => $this->expires_at ?: null,
            'is_active'       => true,
        ]);

        $this->reset(['code', 'description', 'type', 'value', 'min_order', 'max_uses', 'expires_at']);
        $this->type = 'percentage';
        $this->value = 10;
        $this->showForm = false;

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
            <p class="text-[#7A6E5E] text-sm mt-1">{{ $coupons->count() }} code{{ $coupons->count() > 1 ? 's' : '' }} au total</p>
        </div>
        <button wire:click="$set('showForm', true)"
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

            {{-- Expiration --}}
            <div>
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Date d'expiration</label>
                <input wire:model="expires_at" type="date"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                @error('expires_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Description --}}
            <div class="md:col-span-2 lg:col-span-3">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Description (usage interne)</label>
                <input wire:model="description" type="text" placeholder="Ex: Code de bienvenue pour les nouveaux clients"
                       class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
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

    {{-- Table des coupons --}}
    <div class="card-glass overflow-hidden">
        @if ($coupons->isEmpty())
        <div class="px-6 py-16 text-center">
            <svg class="w-10 h-10 text-[#7A6E5E]/30 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            <p class="text-[#7A6E5E]">Aucun code de réduction. Créez votre premier code !</p>
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
                    $isValid = $coupon->is_active
                        && (! $coupon->expires_at || $coupon->expires_at->isFuture())
                        && (! $coupon->max_uses || $coupon->used_count < $coupon->max_uses);
                @endphp
                <tr class="hover:bg-[#C9A84C]/3 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="font-mono text-[#C9A84C] font-semibold">{{ $coupon->code }}</div>
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
                        @if ($coupon->expires_at)
                            <span class="{{ $coupon->expires_at->isPast() ? 'text-red-400' : '' }}">
                                {{ $coupon->expires_at->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-[#7A6E5E]/40">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5">
                        @if ($isValid)
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-400 bg-emerald-900/30 border border-emerald-500/25 px-2 py-0.5 rounded-full">
                            <span class="w-1 h-1 bg-emerald-400 rounded-full"></span> Actif
                        </span>
                        @elseif (! $coupon->is_active)
                        <span class="text-xs text-[#7A6E5E] bg-[#1A1510] border border-[#C9A84C]/10 px-2 py-0.5 rounded-full">Désactivé</span>
                        @elseif ($coupon->expires_at?->isPast())
                        <span class="text-xs text-red-400 bg-red-900/20 border border-red-500/20 px-2 py-0.5 rounded-full">Expiré</span>
                        @else
                        <span class="text-xs text-orange-400 bg-orange-900/20 border border-orange-500/20 px-2 py-0.5 rounded-full">Épuisé</span>
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
