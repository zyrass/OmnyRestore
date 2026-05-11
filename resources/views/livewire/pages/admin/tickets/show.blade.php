<?php
/**
 * Admin — Fil de conversation d'un ticket + réponse
 * Route: GET /admin/tickets/{ticket}
 */

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Ticket — Admin')]
class extends Component
{
    public SupportTicket $ticket;
    public string $reply = '';

    public function mount(SupportTicket $ticket): void
    {
        $this->ticket = $ticket->load(['user', 'order', 'messages.user']);

        // Marquer les messages client comme lus par l'admin
        $ticket->messages()
            ->where('is_admin', false)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Passer en "pending" si c'était "open" (admin a pris connaissance)
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'pending']);
            $this->ticket->refresh();
        }
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => ['required', 'string', 'min:5', 'max:3000']]);

        SupportTicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'user_id'   => auth()->id(),
            'body'      => $this->reply,
            'is_admin'  => true,
        ]);

        // Repasser en "pending" (en attente de la réponse du client)
        $this->ticket->update(['status' => 'pending', 'updated_at' => now()]);

        $this->reply = '';
        $this->ticket->refresh()->load(['user', 'order', 'messages.user']);
    }

    public function closeTicket(): void
    {
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);
        $this->ticket->refresh();
    }

    public function reopenTicket(): void
    {
        $this->ticket->update(['status' => 'open', 'closed_at' => null]);
        $this->ticket->refresh();
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('admin.tickets.index') }}" wire:navigate
           class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center flex-wrap gap-2">
                <span class="font-mono text-[#C9A84C] text-sm">{{ $ticket->reference }}</span>
                @php
                    $sc = ['open' => 'text-yellow-400 border-yellow-500/30 bg-yellow-900/30', 'pending' => 'text-blue-400 border-blue-500/30 bg-blue-900/30', 'closed' => 'text-[#7A6E5E] border-[#7A6E5E]/20'];
                    $sl = ['open' => 'Ouvert', 'pending' => 'En attente client', 'closed' => 'Fermé'];
                    $pc = ['low' => '#9CA3AF', 'normal' => '#C9A84C', 'high' => '#F59E0B', 'urgent' => '#EF4444'];
                    $pl = ['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => '🔥 Urgent'];
                @endphp
                <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $sc[$ticket->status] }}">
                    {{ $sl[$ticket->status] }}
                </span>
                <span style="color:{{ $pc[$ticket->priority] ?? '#7A6E5E' }}" class="text-xs font-medium">
                    {{ $pl[$ticket->priority] ?? $ticket->priority }}
                </span>
            </div>
            <h1 class="text-lg font-bold text-[#F5F0E8] mt-0.5">{{ $ticket->subject }}</h1>
            <p class="text-[#7A6E5E] text-xs mt-0.5">
                {{ $ticket->user->name }} · {{ $ticket->user->email }}
                @if ($ticket->order)
                · <a href="{{ route('admin.orders.show', $ticket->order) }}" wire:navigate
                      class="text-[#C9A84C]/70 hover:text-[#C9A84C] transition-colors">
                    Commande {{ $ticket->order->reference }}
                  </a>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if ($ticket->isClosed())
            <button @click="omnyConfirm({
                        title: 'Rouvrir le ticket ?',
                        message: 'Le ticket sera de nouveau actif et visible par le client.',
                        confirmLabel: 'Rouvrir',
                        danger: false
                    }).then(() => $wire.reopenTicket())"
                    class="text-xs px-3 py-1.5 border border-yellow-500/30 text-yellow-400 hover:bg-yellow-900/20 rounded-sm transition-all">
                Rouvrir
            </button>
            @else
            <button @click="omnyConfirm({
                        title: 'Fermer ce ticket ?',
                        message: 'Le client ne pourra plus répondre tant que le ticket est fermé.',
                        confirmLabel: 'Fermer le ticket',
                        danger: false
                    }).then(() => $wire.closeTicket())"
                    class="text-xs px-3 py-1.5 border border-[#7A6E5E]/25 text-[#7A6E5E] hover:border-[#7A6E5E]/60 rounded-sm transition-all">
                Fermer le ticket
            </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- ── Fil de messages ── --}}
        <div class="lg:col-span-2">
            <div class="space-y-4 mb-6">
                @foreach ($ticket->messages->sortBy('created_at') as $message)
                @php $isAdmin = $message->is_admin; @endphp
                <div class="flex {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%]">
                        <div class="px-4 py-3 rounded-sm {{ $isAdmin
                            ? 'bg-[#C9A84C]/15 border border-[#C9A84C]/30 text-[#F5F0E8]'
                            : 'bg-[#1A1510] border border-[#C9A84C]/15 text-[#F5F0E8]' }}">
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $message->body }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-1 {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
                            @if (!$isAdmin)
                            <span class="text-[10px] text-[#7A6E5E] font-medium">{{ $message->user->name }}</span>
                            <span class="text-[#7A6E5E]/50 text-[10px]">·</span>
                            @else
                            <span class="text-[10px] text-[#C9A84C] font-semibold tracking-wider uppercase">Support OmnyRestore</span>
                            <span class="text-[#7A6E5E]/50 text-[10px]">·</span>
                            @endif
                            <span class="text-[#7A6E5E] text-[10px]">{{ $message->created_at->format('d/m/Y à H:i') }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Zone de réponse admin --}}
            @if (!$ticket->isClosed())
            <div class="card-glass p-5">
                <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-3">Réponse admin</label>
                <textarea wire:model="reply" rows="5"
                          placeholder="Rédigez votre réponse au client…"
                          style="background-color:#0F0C08;color:#F5F0E8;border:1px solid rgba(201,168,76,0.2);width:100%;padding:12px 16px;font-size:0.875rem;resize:none;outline:none;display:block;"
                          onfocus="this.style.borderColor='rgba(201,168,76,0.5)'"
                          onblur="this.style.borderColor='rgba(201,168,76,0.2)'">
                </textarea>
                @error('reply') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                <div class="flex items-center gap-3 mt-3">
                    <button wire:click="sendReply" wire:loading.attr="disabled" class="btn-gold text-sm">
                        <span wire:loading.remove wire:target="sendReply">Envoyer la réponse</span>
                        <span wire:loading wire:target="sendReply" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Envoi…
                        </span>
                    </button>
                </div>
            </div>
            @else
            <div class="bg-[#1A1510]/60 border border-[#7A6E5E]/15 rounded-sm p-4 text-center">
                <p class="text-[#7A6E5E] text-sm">Ticket fermé —
                    <button @click="omnyConfirm({
                                title: 'Rouvrir le ticket ?',
                                message: 'Le ticket sera de nouveau actif.',
                                confirmLabel: 'Rouvrir',
                                danger: false
                            }).then(() => $wire.reopenTicket())"
                            class="text-[#C9A84C] hover:underline">Rouvrir</button>
                    pour répondre.
                </p>
            </div>
            @endif
        </div>

        {{-- ── Sidebar infos ── --}}
        <div class="space-y-4">

            {{-- Infos client --}}
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Client</h3>
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-[#7A6E5E] text-xs">Nom</dt>
                        <dd class="text-[#F5F0E8] font-medium">{{ $ticket->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#7A6E5E] text-xs">Email</dt>
                        <dd class="text-[#C9A84C] text-xs font-mono">{{ $ticket->user->email }}</dd>
                    </div>
                    @if ($ticket->order)
                    <div class="pt-2 border-t border-[#C9A84C]/10">
                        <dt class="text-[#7A6E5E] text-xs mb-1">Commande liée</dt>
                        <dd>
                            <a href="{{ route('admin.orders.show', $ticket->order) }}" wire:navigate
                               class="font-mono text-[#C9A84C] text-xs hover:underline">
                                {{ $ticket->order->reference }}
                            </a>
                            <span class="text-[#7A6E5E] text-xs ml-2">{{ $ticket->order->status }}</span>
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Infos ticket --}}
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Ticket</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E] text-xs">Messages</dt>
                        <dd class="text-[#F5F0E8]">{{ $ticket->messages->count() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E] text-xs">Ouvert le</dt>
                        <dd class="text-[#7A6E5E] text-xs">{{ $ticket->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @if ($ticket->closed_at)
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E] text-xs">Fermé le</dt>
                        <dd class="text-[#7A6E5E] text-xs">{{ $ticket->closed_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

        </div>
    </div>
</div>
