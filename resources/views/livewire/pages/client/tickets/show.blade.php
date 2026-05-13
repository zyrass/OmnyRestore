<?php
/**
 * Client — Fil de conversation d'un ticket
 * Route: GET /client/tickets/{ticket}
 */

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Ticket support')]
class extends Component
{
    public SupportTicket $ticket;
    public string $reply = '';

    public function mount(SupportTicket $ticket): void
    {
        // IDOR: seul le propriétaire peut voir son ticket
        abort_if($ticket->user_id !== auth()->id(), 403);

        $this->ticket = $ticket->load(['order', 'messages.user']);

        // Marquer les messages admin comme lus
        $ticket->messages()->where('is_admin', true)->where('is_read', false)->update(['is_read' => true]);
    }

    public function sendReply(): void
    {
        abort_if($this->ticket->isClosed(), 403, 'Ce ticket est fermé.');

        $this->validate(['reply' => ['required', 'string', 'min:5', 'max:3000']]);

        SupportTicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'user_id'   => auth()->id(),
            'body'      => $this->reply,
            'is_admin'  => false,
        ]);

        // Repasser en "open" pour notifier l'admin
        $this->ticket->update(['status' => 'open', 'updated_at' => now()]);

        $this->reply = '';
        $this->ticket->refresh()->load(['order', 'messages.user']);
    }

    public function closeTicket(): void
    {
        abort_if($this->ticket->user_id !== auth()->id(), 403);
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);
        $this->ticket->refresh();
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('client.tickets.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center flex-wrap gap-2">
                <span class="font-mono text-[#C9A84C] text-sm">{{ $ticket->reference }}</span>
                @php
                    $sc = ['open' => 'text-yellow-400 border-yellow-500/30 bg-yellow-900/30', 'pending' => 'text-blue-400 border-blue-500/30 bg-blue-900/30', 'closed' => 'text-[#7A6E5E] border-[#7A6E5E]/30'];
                    $sl = ['open' => 'Ouvert', 'pending' => 'En attente de réponse', 'closed' => 'Fermé'];
                @endphp
                <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $sc[$ticket->status] }}">{{ $sl[$ticket->status] }}</span>
                @if ($ticket->order)
                <a href="{{ route('client.orders.show', $ticket->order) }}" wire:navigate class="text-xs text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    → Commande {{ $ticket->order->reference }}
                </a>
                @endif
            </div>
            <h1 class="text-lg font-bold text-[#F5F0E8] mt-0.5">{{ $ticket->subject }}</h1>
        </div>

        @if (!$ticket->isClosed())
        <button @click="const wire = $wire; omnyConfirm({
                    title: 'Clore ce ticket ?',
                    message: 'Vous ne pourrez plus répondre. Contactez-nous si le problème persiste.',
                    confirmLabel: 'Clore le ticket',
                    danger: false
                }).then(() => wire.closeTicket())"
                class="text-xs text-[#7A6E5E] border border-[#7A6E5E]/25 hover:border-[#7A6E5E]/60 px-3 py-1.5 rounded-sm transition-all">
            Clore le ticket
        </button>
        @endif
    </div>

    {{-- Fil de messages --}}
    <div class="space-y-4 mb-8 max-w-3xl">
        @foreach ($ticket->messages->sortBy('created_at') as $message)
        @php $isAdmin = $message->is_admin; @endphp
        <div class="flex {{ $isAdmin ? 'justify-start' : 'justify-end' }}">
            <div class="max-w-[80%]">
                {{-- Bulle --}}
                <div class="px-4 py-3 rounded-sm {{ $isAdmin
                    ? 'bg-[#1A1510] border border-[#C9A84C]/15 text-[#F5F0E8]'
                    : 'bg-[#C9A84C]/15 border border-[#C9A84C]/30 text-[#F5F0E8]' }}">
                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $message->body }}</p>
                </div>
                {{-- Méta --}}
                <div class="flex items-center gap-2 mt-1 {{ $isAdmin ? 'justify-start' : 'justify-end' }}">
                    @if ($isAdmin)
                    <span class="text-[10px] text-[#C9A84C] font-semibold tracking-wider uppercase">Équipe OmnyRestore</span>
                    <span class="text-[#7A6E5E]/50 text-[10px]">·</span>
                    @endif
                    <span class="text-[#7A6E5E] text-[10px]">{{ $message->created_at->format('d/m/Y à H:i') }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Zone de réponse --}}
    @if (!$ticket->isClosed())
    <div class="max-w-3xl">
        <div class="card-glass p-5">
            <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-3">Votre réponse</label>
            <textarea wire:model="reply" rows="4"
                      placeholder="Ajoutez des informations ou répondez à l'équipe…"
                      class="w-full bg-[#0F0C08] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                             placeholder-[#7A6E5E]/50 resize-none focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all mb-3">
            </textarea>
            @error('reply') <p class="text-red-400 text-xs mb-2">{{ $message }}</p> @enderror
            <button wire:click="sendReply" wire:loading.attr="disabled" class="btn-gold text-sm">
                <span wire:loading.remove wire:target="sendReply">Envoyer</span>
                <span wire:loading wire:target="sendReply" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Envoi…
                </span>
            </button>
        </div>
    </div>
    @else
    <div class="max-w-3xl bg-[#1A1510]/60 border border-[#7A6E5E]/15 rounded-sm p-4 text-center">
        <p class="text-[#7A6E5E] text-sm">Ce ticket est fermé. <a href="{{ route('client.tickets.create') }}" wire:navigate class="text-[#C9A84C] hover:underline">Ouvrir un nouveau ticket</a> si besoin.</p>
    </div>
    @endif
</div>
