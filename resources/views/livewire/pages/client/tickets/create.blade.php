<?php
/**
 * Client — Nouveau ticket support
 * Route: GET /client/tickets/create
 */

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Nouveau ticket')]
class extends Component
{
    public string $subject   = '';
    public string $body      = '';
    public string $priority  = 'normal';
    public ?string $order_id = null;

    public function mount(): void
    {
        $orderId = request()->query('order_id');
        if ($orderId) {
            $owns = \App\Models\Order::where('id', $orderId)
                ->where('user_id', auth()->id())
                ->exists();
            if ($owns) {
                $this->order_id = $orderId;
            }
        }
    }

    public function with(): array
    {
        return [
            'orders' => Order::where('user_id', auth()->id())
                ->whereNotIn('status', ['CANCELLED'])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'reference', 'status', 'created_at']),
        ];
    }

    public function submit(): void
    {
        $this->validate([
            'subject'  => ['required', 'string', 'min:5', 'max:200'],
            'body'     => ['required', 'string', 'min:20', 'max:3000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
        ]);

        $ticket = SupportTicket::create([
            'user_id'  => auth()->id(),
            'order_id' => $this->order_id ?: null,
            'subject'  => $this->subject,
            'priority' => $this->priority,
            'status'   => 'open',
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => auth()->id(),
            'body'      => $this->body,
            'is_admin'  => false,
        ]);

        $this->redirect(route('client.tickets.show', $ticket), navigate: true);
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('client.tickets.index') }}" wire:navigate
           class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Nouveau ticket</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Notre équipe vous répond généralement sous 24h</p>
        </div>
    </div>

    <div class="max-w-2xl">
        <form wire:submit="submit" class="space-y-4">

            {{-- ── Commande liée ── --}}
            <div class="card-glass p-5">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-3">
                    Commande concernée
                    <span class="ml-1 text-[#C9A84C]/50 normal-case tracking-normal">(optionnel)</span>
                </label>
                {{-- Le select nécessite style inline car Tailwind bg- est souvent surchargé par les defaults navigateur --}}
                <div class="relative">
                    <select wire:model="order_id"
                            style="background-color:#0F0C08;color:#F5F0E8;-webkit-appearance:none;-moz-appearance:none;appearance:none;border:1px solid rgba(201,168,76,0.2);width:100%;padding:12px 40px 12px 16px;font-size:0.875rem;outline:none;cursor:pointer;">
                        <option value="" style="background:#0F0C08;color:#7A6E5E;">
                            — Aucune commande associée —
                        </option>
                        @foreach ($orders as $order)
                        <option value="{{ $order->id }}" style="background:#0F0C08;color:#F5F0E8;">
                            {{ $order->reference }} · {{ $order->created_at->format('d/m/Y') }} · {{ $order->status }}
                        </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="w-4 h-4 text-[#C9A84C]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                @error('order_id')<p class="text-red-400 text-xs mt-2">{{ $message }}</p>@enderror
            </div>

            {{-- ── Sujet ── --}}
            <div class="card-glass p-5">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-3">Sujet *</label>
                <input wire:model="subject" type="text"
                       placeholder="Ex : Résultat insatisfaisant sur la photo de 1952…"
                       style="background-color:#0F0C08;color:#F5F0E8;border:1px solid rgba(201,168,76,0.2);width:100%;padding:12px 16px;font-size:0.875rem;outline:none;"
                       onfocus="this.style.borderColor='rgba(201,168,76,0.5)'"
                       onblur="this.style.borderColor='rgba(201,168,76,0.2)'">
                @error('subject')<p class="text-red-400 text-xs mt-2">{{ $message }}</p>@enderror
            </div>

            {{-- ── Priorité (Alpine pour le style sélectionné) ── --}}
            <div class="card-glass p-5"
                 x-data="{ selected: @entangle('priority') }">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-3">Priorité</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach([
                        'low'    => ['Faible',  '#9CA3AF'],
                        'normal' => ['Normale', '#C9A84C'],
                        'high'   => ['Élevée',  '#F59E0B'],
                        'urgent' => ['Urgent',  '#EF4444'],
                    ] as $val => [$label, $color])
                    <button type="button"
                            @click="selected = '{{ $val }}'"
                            :style="selected === '{{ $val }}'
                                ? 'border-color:{{ $color }};background-color:{{ $color }}22;color:{{ $color }};'
                                : 'border-color:rgba(201,168,76,0.15);background-color:transparent;color:#7A6E5E;'"
                            style="padding:10px 8px;border-width:1px;border-style:solid;font-size:0.75rem;font-weight:500;text-align:center;transition:all 0.15s;cursor:pointer;">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
                <input type="hidden" wire:model="priority" x-bind:value="selected">
            </div>

            {{-- ── Message ── --}}
            <div class="card-glass p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <label class="text-[#7A6E5E] text-xs uppercase tracking-widest">Votre message *</label>
                    <span class="text-[#7A6E5E]/50 text-xs">min. 20 caractères</span>
                </div>
                <textarea wire:model="body" rows="7"
                          placeholder="Décrivez votre problème en détail. Indiquez tout contexte utile (date, photos concernées, résultat attendu…)"
                          style="background-color:#0F0C08;color:#F5F0E8;border:1px solid rgba(201,168,76,0.2);width:100%;padding:12px 16px;font-size:0.875rem;resize:none;outline:none;display:block;"
                          onfocus="this.style.borderColor='rgba(201,168,76,0.5)'"
                          onblur="this.style.borderColor='rgba(201,168,76,0.2)'">
                </textarea>
                <div class="flex items-center justify-between mt-2">
                    @error('body')
                    <p class="text-red-400 text-xs">{{ $message }}</p>
                    @else
                    <span></span>
                    @enderror
                    <p class="text-[#7A6E5E] text-xs">{{ strlen($body) }} / 3000</p>
                </div>
            </div>

            {{-- ── Actions ── --}}
            <div class="flex items-center gap-4 pt-1">
                <button type="submit" wire:loading.attr="disabled" class="btn-gold">
                    <span wire:loading.remove wire:target="submit">Envoyer le ticket</span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Envoi…
                    </span>
                </button>
                <a href="{{ route('client.tickets.index') }}" wire:navigate
                   class="text-[#7A6E5E] text-sm hover:text-[#F5F0E8] transition-colors">
                    Annuler
                </a>
            </div>

        </form>
    </div>
</div>
