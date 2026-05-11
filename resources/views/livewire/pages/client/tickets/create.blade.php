<?php
/**
 * Client — Nouveau ticket support
 * Route: GET /client/tickets/create
 *
 * Formulaire avec :
 *   - Sujet libre
 *   - Commande liée (sélectionnable parmi les commandes du client)
 *   - Premier message
 *   - Priorité
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
        // Pré-sélectionne la commande si on arrive depuis /client/orders/{id} via ?order_id=
        $orderId = request()->query('order_id');
        if ($orderId) {
            // Vérifier que la commande appartient bien à l'utilisateur connecté (IDOR)
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
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('client.tickets.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Nouveau ticket</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Notre équipe vous répond généralement sous 24h</p>
        </div>
    </div>

    <div class="max-w-2xl">
        <form wire:submit="submit" class="space-y-5">

            {{-- Commande liée (optionnel) --}}
            <div class="card-glass p-5">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">
                    Commande concernée <span class="text-[#C9A84C]/60 lowercase tracking-normal">(optionnel)</span>
                </label>
                <select wire:model="order_id"
                        class="w-full bg-[#0F0C08] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                               focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all">
                    <option value="">— Aucune commande associée —</option>
                    @foreach ($orders as $order)
                    <option value="{{ $order->id }}">
                        {{ $order->reference }} · {{ $order->created_at->format('d/m/Y') }} · {{ $order->status }}
                    </option>
                    @endforeach
                </select>
                @error('order_id') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Sujet + Priorité --}}
            <div class="card-glass p-5 space-y-4">
                <div>
                    <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Sujet *</label>
                    <input wire:model="subject" type="text" placeholder="Ex : Résultat insatisfaisant sur la photo de 1952…"
                           class="w-full bg-[#0F0C08] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                                  placeholder-[#7A6E5E]/50 focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all">
                    @error('subject') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">Priorité</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => 'Urgent'] as $val => $label)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="priority" value="{{ $val }}" class="sr-only peer">
                            <div class="text-center px-2 py-2 rounded-sm border text-xs transition-all peer-checked:border-[#C9A84C] peer-checked:bg-[#C9A84C]/10 peer-checked:text-[#C9A84C] border-[#C9A84C]/15 text-[#7A6E5E] hover:border-[#C9A84C]/35">
                                {{ $label }}
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Message --}}
            <div class="card-glass p-5">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-2">
                    Votre message * <span class="text-[#7A6E5E]/50 lowercase tracking-normal">(min. 20 caractères)</span>
                </label>
                <textarea wire:model="body" rows="7"
                          placeholder="Décrivez votre problème en détail. Indiquez tout contexte utile (date, photos concernées, ce que vous attendiez…)"
                          class="w-full bg-[#0F0C08] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                                 placeholder-[#7A6E5E]/50 resize-none focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all">
                </textarea>
                <div class="flex justify-between mt-1">
                    @error('body') <p class="text-red-400 text-xs">{{ $message }}</p> @else <span></span> @enderror
                    <p class="text-[#7A6E5E] text-xs">{{ strlen($body) }} / 3000</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" wire:loading.attr="disabled" class="btn-gold">
                    <span wire:loading.remove wire:target="submit">Envoyer le ticket</span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Envoi…
                    </span>
                </button>
                <a href="{{ route('client.tickets.index') }}" wire:navigate class="text-[#7A6E5E] text-sm hover:text-[#F5F0E8] transition-colors">Annuler</a>
            </div>
        </form>
    </div>
</div>
