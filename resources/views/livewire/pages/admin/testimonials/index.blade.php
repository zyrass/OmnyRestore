<?php
/**
 * Admin — Modération des témoignages
 * Route: GET /admin/testimonials
 * Middleware: auth, admin
 *
 * 3 états possibles :
 *   - En attente  : is_published = false + rejected_at = null
 *   - Publié      : is_published = true  + rejected_at = null  → visible sur la vitrine
 *   - Rejeté      : rejected_at non-null (conservé mais masqué partout)
 */

use App\Models\Testimonial;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Modération — Témoignages')]
class extends Component
{
    public string $filter = 'pending'; // pending | published | rejected | ignored

    /** Publie un témoignage en attente → visible sur la vitrine */
    public function publish(int $id): void
    {
        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update([
            'is_published' => true,
            'rejected_at'  => null,
        ]);
        $this->dispatch('refresh-navbar-counts');
        session()->flash('success', '✅ Témoignage publié sur la vitrine.');
    }

    /** Met de côté un témoignage (ne compte plus comme "nouveau") */
    public function ignore(int $id): void
    {
        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update([
            'is_published' => false,
            'rejected_at'  => null,
            'ignored_at'   => now(),
        ]);
        $this->dispatch('refresh-navbar-counts');
        session()->flash('success', '📥 Témoignage mis de côté (archivé).');
    }

    /** Rejette un témoignage (non supprimé — audit trail) */
    public function reject(int $id): void
    {
        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update([
            'is_published' => false,
            'rejected_at'  => now(),
        ]);
        $this->dispatch('refresh-navbar-counts');
        session()->flash('success', '🗑 Témoignage rejeté.');
    }

    /** Supprime définitivement un témoignage rejeté */
    public function destroy(int $id): void
    {
        Testimonial::findOrFail($id)->delete();
        $this->dispatch('refresh-navbar-counts');
        session()->flash('success', 'Témoignage supprimé définitivement.');
    }

    /** Dépublie (remet en attente de modération) */
    public function unpublish(int $id): void
    {
        Testimonial::findOrFail($id)->update([
            'is_published' => false,
            'rejected_at'  => null,
            'ignored_at'   => null,
        ]);
        $this->dispatch('testimonial-moderated');
        session()->flash('success', 'Témoignage dépublié (en attente).');
    }

    public function with(): array
    {
        $query = match ($this->filter) {
            'published' => Testimonial::published(),
            'rejected'  => Testimonial::rejected(),
            'ignored'   => Testimonial::ignored(),
            default     => Testimonial::pending(),
        };

        return [
            'items'        => $query->with(['order', 'user'])->latest()->get(),
            'pendingCount' => Testimonial::pending()->count(),
        ];
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Témoignages</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Modération des avis clients avant publication sur la vitrine.</p>
        </div>
        @if ($pendingCount > 0)
        <span class="bg-[#C9A84C]/20 border border-[#C9A84C]/40 text-[#C9A84C] text-xs font-bold px-3 py-1.5 rounded-full">
            {{ $pendingCount }} en attente
        </span>
        @endif
    </div>

    {{-- Flash --}}
    @if (session('success'))
    <div class="card-glass p-3 mb-6 border border-emerald-500/30 bg-emerald-500/5 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Filtres --}}
    <div class="flex gap-2 mb-6">
        @foreach (['pending' => 'En attente', 'published' => 'Publiés', 'rejected' => 'Rejetés', 'ignored' => 'Mis de côté'] as $key => $label)
        <button wire:click="$set('filter', '{{ $key }}')"
                class="px-4 py-2 text-xs uppercase tracking-widest rounded-sm transition-all duration-150
                       {{ $filter === $key
                            ? 'bg-[#C9A84C]/20 border border-[#C9A84C]/50 text-[#C9A84C]'
                            : 'border border-[#3A3028] text-[#7A6E5E] hover:border-[#C9A84C]/30 hover:text-[#C9A84C]/70' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Liste --}}
    @if ($items->isEmpty())
    <div class="card-glass p-12 text-center flex flex-col items-center justify-center" style="min-height: 600px;">
        <p class="text-[#7A6E5E] text-sm">Aucun témoignage dans cette catégorie.</p>
    </div>
    @else
    <div class="space-y-4">
        @foreach ($items as $t)
        <div class="card-glass p-5 flex flex-col sm:flex-row gap-5">

            {{-- Avatar + méta --}}
            <div class="flex items-start gap-4 sm:w-52 shrink-0">
                <div class="w-10 h-10 rounded-full bg-[#C9A84C]/15 border border-[#C9A84C]/30 flex items-center justify-center shrink-0">
                    <span class="text-[#C9A84C] text-sm font-bold">{{ $t->author_initials }}</span>
                </div>
                <div>
                    <p class="text-[#F5F0E8] text-sm font-semibold">{{ $t->author_name }}</p>
                    {{-- Étoiles --}}
                    <div class="flex gap-0.5 mt-0.5">
                        @for ($s = 1; $s <= 5; $s++)
                        <svg class="w-3 h-3 {{ $s <= $t->rating ? 'text-[#C9A84C]' : 'text-[#3A3028]' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        @endfor
                    </div>
                    @if ($t->order)
                    <p class="text-[#4A3E2E] text-xs mt-1 font-mono">{{ $t->order->reference }}</p>
                    @endif
                    <p class="text-[#4A3E2E] text-xs mt-0.5">{{ $t->created_at->format('d/m/Y') }}</p>
                </div>
            </div>

            {{-- Contenu --}}
            <div class="flex-1">
                <p class="text-[#9E9085] text-sm leading-relaxed italic">&ldquo;{{ $t->content }}&rdquo;</p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col gap-2 sm:items-end justify-start shrink-0">
                @if ($filter === 'pending')
                <button wire:click="publish({{ $t->id }})"
                        class="px-4 py-2 text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-400
                               hover:bg-emerald-500/20 hover:border-emerald-400 rounded-sm transition-all">
                    ✓ Publier
                </button>
                <button wire:click="ignore({{ $t->id }})"
                        class="px-4 py-2 text-xs border border-[#C9A84C]/30 text-[#C9A84C]/70
                               hover:bg-[#C9A84C]/10 hover:border-[#C9A84C]/50 hover:text-[#C9A84C] rounded-sm transition-all">
                    📥 Ignorer
                </button>
                <button @click="const wire = $wire; omnyConfirm({
                            title: 'Rejeter cet avis',
                            message: 'Le témoignage sera masqué de la vitrine et déplacé dans l\'onglet des rejets.',
                            confirmLabel: '✕ Rejeter',
                            danger: true
                        }).then(() => wire.reject({{ $t->id }}))"
                        class="px-4 py-2 text-xs border border-red-500/30 text-red-400/70
                               hover:bg-red-500/10 hover:border-red-400 hover:text-red-400 rounded-sm transition-all">
                    ✕ Rejeter
                </button>

                @elseif ($filter === 'published')
                <button wire:click="unpublish({{ $t->id }})"
                        class="px-4 py-2 text-xs border border-[#3A3028] text-[#7A6E5E]
                               hover:border-[#C9A84C]/30 hover:text-[#C9A84C]/70 rounded-sm transition-all">
                    Dépublier
                </button>

                @elseif ($filter === 'rejected' || $filter === 'ignored')
                <button wire:click="publish({{ $t->id }})"
                        class="px-4 py-2 text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-400
                               hover:bg-emerald-500/20 rounded-sm transition-all">
                    ✓ Publier quand même
                </button>
                @if ($filter === 'ignored')
                <button @click="const wire = $wire; omnyConfirm({
                            title: 'Rejeter cet avis',
                            message: 'Le témoignage sera déplacé dans l\'onglet des rejets.',
                            confirmLabel: '✕ Rejeter',
                            danger: true
                        }).then(() => wire.reject({{ $t->id }}))"
                        class="px-4 py-2 text-xs border border-red-500/30 text-red-400/70
                               hover:bg-red-500/10 hover:text-red-400 rounded-sm transition-all">
                    ✕ Rejeter
                </button>
                @endif
                <button @click="const wire = $wire; omnyConfirm({
                            title: 'Suppression Définitive',
                            message: 'Voulez-vous vraiment supprimer définitivement cet avis ? Cette action est irréversible.',
                            confirmLabel: '🗑 Supprimer',
                            danger: true
                        }).then(() => wire.destroy({{ $t->id }}))"
                        class="px-4 py-2 text-xs border border-red-500/30 text-red-400/70
                               hover:bg-red-500/10 hover:text-red-400 rounded-sm transition-all">
                    🗑 Supprimer
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
