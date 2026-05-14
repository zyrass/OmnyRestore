<?php
/**
 * Admin — Liste de tous les tickets support
 * Route: GET /admin/tickets
 */

use App\Models\SupportTicket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Tickets support — Admin')]
class extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function with(): array
    {
        $counts = [
            'all'     => SupportTicket::count(),
            'open'    => SupportTicket::where('status', 'open')->count(),
            'pending' => SupportTicket::where('status', 'pending')->count(),
            'closed'  => SupportTicket::where('status', 'closed')->count(),
        ];

        $tickets = SupportTicket::with(['user', 'order'])
            ->withCount(['messages as unread_count' => fn($q) => $q->where('is_admin', false)->where('is_read', false)])
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->latest('updated_at')
            ->paginate(25);

        return compact('tickets', 'counts');
    }

    public function updatedStatus(): void { $this->resetPage(); }
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8]">Tickets support</h1>
        <p class="text-[#7A6E5E] text-sm mt-1">Demandes d'assistance clients</p>
    </div>

    {{-- Filtres statut --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach([
            ['value' => '',        'label' => 'Tous',                  'count' => $counts['all'],     'color' => ''],
            ['value' => 'open',    'label' => 'À prendre en charge',   'count' => $counts['open'],    'color' => 'yellow'],
            ['value' => 'pending', 'label' => 'Attente client',        'count' => $counts['pending'], 'color' => 'blue'],
            ['value' => 'closed',  'label' => 'Fermés',                'count' => $counts['closed'],  'color' => 'gray'],
        ] as $f)
        <button wire:click="$set('status', '{{ $f['value'] }}')"
                class="px-3 py-1.5 text-xs rounded-sm border transition-all
                       {{ $status === $f['value']
                          ? 'border-[#C9A84C]/60 bg-[#C9A84C]/15 text-[#C9A84C]'
                          : 'border-[#C9A84C]/15 text-[#7A6E5E] hover:border-[#C9A84C]/35 hover:text-[#F5F0E8]' }}">
            {{ $f['label'] }}
            @if ($f['count'] > 0)
            <span class="ml-1 opacity-70">{{ $f['count'] }}</span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Table tickets --}}
    <div class="card-glass overflow-hidden">
        @if ($tickets->isEmpty())
        <div class="p-16 text-center">
            <svg class="w-10 h-10 text-[#C9A84C]/20 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <p class="text-[#7A6E5E] text-sm">Aucun ticket {{ $status ? "avec le statut «{$status}»" : '' }}</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    @foreach(['Référence', 'Client', 'Sujet', 'Commande', 'Priorité', 'Statut', 'Mis à jour', ''] as $h)
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-5 py-4 font-medium
                               {{ in_array($h, ['Commande', 'Priorité']) ? 'hidden lg:table-cell' : '' }}">
                        {{ $h }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/8">
                @foreach ($tickets as $ticket)
                @php
                    $statusColors  = ['open' => 'bg-yellow-900/40 text-yellow-400 border-yellow-500/30', 'pending' => 'bg-blue-900/40 text-blue-400 border-blue-500/30', 'closed' => 'bg-[#1A1510] text-[#7A6E5E] border-[#7A6E5E]/20'];
                    $statusLabels  = ['open' => 'Attente prise en charge', 'pending' => 'En attente client', 'closed' => 'Fermé'];
                    $priorityColors = ['low' => 'text-[#7A6E5E]', 'normal' => 'text-[#C9A84C]', 'high' => 'text-amber-400', 'urgent' => 'text-red-400'];
                    $priorityLabels = ['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => '🔥 Urgent'];
                @endphp
                <tr class="hover:bg-[#C9A84C]/3 transition-colors cursor-pointer"
                    onclick="window.location='{{ route('admin.tickets.show', $ticket) }}'">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-[#C9A84C] text-xs">{{ $ticket->reference }}</span>
                            @if ($ticket->unread_count > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] bg-[#C9A84C] text-black font-bold rounded-full">
                                {{ $ticket->unread_count }}
                            </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <p class="text-[#F5F0E8] text-xs">{{ $ticket->user->name }}</p>
                        <p class="text-[#7A6E5E] text-[10px]">{{ $ticket->user->email }}</p>
                    </td>
                    <td class="px-5 py-3.5">
                        <p class="text-[#F5F0E8] text-xs truncate max-w-[200px]">{{ $ticket->subject }}</p>
                    </td>
                    <td class="px-5 py-3.5 hidden lg:table-cell">
                        @if ($ticket->order)
                        <span class="font-mono text-[#C9A84C]/70 text-xs">{{ $ticket->order->reference }}</span>
                        @else
                        <span class="text-[#7A6E5E] text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 hidden lg:table-cell">
                        <span class="text-xs {{ $priorityColors[$ticket->priority] ?? 'text-[#7A6E5E]' }}">
                            {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex px-2 py-0.5 text-[10px] font-medium border rounded-full {{ $statusColors[$ticket->status] ?? '' }}">
                            {{ $statusLabels[$ticket->status] ?? $ticket->status }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-[#7A6E5E] text-xs">{{ $ticket->updated_at->diffForHumans() }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <svg class="w-4 h-4 text-[#7A6E5E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if ($tickets->hasPages())
        <div class="px-5 py-4 border-t border-[#C9A84C]/10">{{ $tickets->links() }}</div>
        @endif
        @endif
    </div>
</div>
