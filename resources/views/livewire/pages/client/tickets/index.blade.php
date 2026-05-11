<?php
/**
 * Client — Liste des tickets support
 * Route: GET /client/tickets
 */

use App\Models\SupportTicket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Mes tickets support')]
class extends Component
{
    public function with(): array
    {
        return [
            'tickets' => SupportTicket::where('user_id', auth()->id())
                ->with(['order', 'messages' => fn($q) => $q->latest()->limit(1)])
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Mes tickets support</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Suivez vos demandes d'assistance</p>
        </div>
        <a href="{{ route('client.tickets.create') }}" wire:navigate class="btn-gold text-sm">
            + Nouveau ticket
        </a>
    </div>

    @if ($tickets->isEmpty())
    <div class="card-glass p-16 text-center">
        <svg class="w-12 h-12 text-[#C9A84C]/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <p class="text-[#7A6E5E] text-sm mb-6">Aucun ticket ouvert pour l'instant</p>
        <a href="{{ route('client.tickets.create') }}" wire:navigate class="btn-gold text-sm">
            Contacter le support
        </a>
    </div>
    @else
    <div class="space-y-3">
        @foreach ($tickets as $ticket)
        @php
            $statusColors = ['open' => 'text-yellow-400 border-yellow-500/30 bg-yellow-900/30', 'pending' => 'text-blue-400 border-blue-500/30 bg-blue-900/30', 'closed' => 'text-[#7A6E5E] border-[#7A6E5E]/30 bg-[#1A1510]'];
            $statusLabels = ['open' => 'Ouvert', 'pending' => 'En cours', 'closed' => 'Fermé'];
            $unread = $ticket->unreadCount(false);
        @endphp
        <a href="{{ route('client.tickets.show', $ticket) }}" wire:navigate
           class="block card-glass p-5 hover:border-[#C9A84C]/30 transition-all duration-200">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono text-[#C9A84C] text-xs">{{ $ticket->reference }}</span>
                        @if ($ticket->order)
                        <span class="text-[#7A6E5E] text-xs">· Commande {{ $ticket->order->reference }}</span>
                        @endif
                        @if ($unread > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs bg-[#C9A84C] text-black font-bold rounded-full">{{ $unread }}</span>
                        @endif
                    </div>
                    <p class="text-[#F5F0E8] text-sm font-medium truncate">{{ $ticket->subject }}</p>
                    @if ($ticket->messages->first())
                    <p class="text-[#7A6E5E] text-xs mt-1 truncate">{{ Str::limit($ticket->messages->first()->body, 80) }}</p>
                    @endif
                </div>
                <div class="flex flex-col items-end gap-2 shrink-0">
                    <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $statusColors[$ticket->status] }}">
                        {{ $statusLabels[$ticket->status] }}
                    </span>
                    <span class="text-[#7A6E5E] text-xs">{{ $ticket->updated_at->diffForHumans() }}</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>
