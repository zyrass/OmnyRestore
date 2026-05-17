<?php
/**
 * Admin — Gestion de l'Équipe & Rôles (RBAC)
 * Route: GET /admin/team/roles
 * Middleware: auth, admin (Super Admin uniquement)
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Gestion de l\'Équipe & Rôles — Admin')]
class extends Component
{
    use WithPagination;

    // Filtres
    public string $search = '';
    public string $roleFilter = 'all'; // all | super-admin | operator | marketing
    public string $activeTab = 'members'; // members | rbac | diagrams

    // Modal de création
    public bool $showCreateModal = false;
    public string $newMemberName = '';
    public string $newMemberEmail = '';
    public string $newMemberContactEmail = '';
    public string $newMemberRole = 'operator';
    public string $newMemberPassword = '';

    // Édition rapide
    public ?string $editingUserId = null;
    public string $editingRole = '';
    public string $editingContactEmail = '';

    protected $rules = [
        'newMemberName' => 'required|string|min:2|max:50',
        'newMemberEmail' => 'required|email|unique:users,email',
        'newMemberContactEmail' => 'nullable|email',
        'newMemberRole' => 'required|in:super-admin,operator,marketing',
        'newMemberPassword' => 'required|string|min:8',
    ];

    protected $validationAttributes = [
        'newMemberName' => 'nom complet',
        'newMemberEmail' => 'adresse e-mail',
        'newMemberContactEmail' => 'e-mail de contact (sécurité)',
        'newMemberRole' => 'rôle',
        'newMemberPassword' => 'mot de passe',
    ];

    /**
     * Calcule le nombre actuel de sièges occupés
     */
    public function getSeatsCountProperty(): int
    {
        return User::whereIn('role', ['super-admin', 'operator', 'marketing'])
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Ajoute un collaborateur dans la limite des 10 sièges autorisés
     */
    public function addMember(): void
    {
        // 1. Vérification du quota (10 sièges max)
        if ($this->seatsCount >= 10) {
            $this->addError('quota', 'Le quota maximum de 10 sièges de collaborateurs est atteint.');
            return;
        }

        $this->validate();

        // 2. Création du collaborateur
        $user = User::create([
            'name' => $this->newMemberName,
            'email' => $this->newMemberEmail,
            'contact_email' => $this->newMemberContactEmail ?: null,
            'password' => $this->newMemberPassword, // Hashé automatiquement par le cast du modèle
            'role' => $this->newMemberRole,
            'email_verified_at' => now(), // validé d'office par l'admin
            'rgpd_consent_at' => now(),   // consentement RGPD pour l'outil interne
        ]);

        Log::info("RBAC: Collaborateur créé par Admin " . Auth::id() . " -> id={$user->id}, rôle={$user->role}");

        // 3. Reset du formulaire & Fermeture du modal
        $this->reset(['newMemberName', 'newMemberEmail', 'newMemberContactEmail', 'newMemberRole', 'newMemberPassword', 'showCreateModal']);
        session()->flash('success', '✅ Collaborateur ajouté avec succès.');
    }

    /**
     * Active ou suspend un compte collaborateur
     */
    public function toggleSuspension(string $userId): void
    {
        // Interdire de se suspendre soi-même
        if ($userId === Auth::id()) {
            session()->flash('error', '❌ Vous ne pouvez pas suspendre votre propre compte.');
            return;
        }

        $user = User::findOrFail($userId);
        
        if ($user->isSuspended()) {
            $user->update(['suspended_at' => null]);
            Log::info("RBAC: Collaborateur réactivé -> id={$user->id}");
            session()->flash('success', "🔓 Le compte de {$user->name} a été réactivé.");
        } else {
            $user->update(['suspended_at' => now()]);
            Log::info("RBAC: Collaborateur suspendu -> id={$user->id}");
            session()->flash('success', "🔒 Le compte de {$user->name} a été suspendu.");
        }
    }

    /**
     * Ouvre le mode édition de rôle
     */
    public function startEditRole(string $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $userId;
        $this->editingRole = $user->role;
        $this->editingContactEmail = $user->contact_email ?? '';
    }

    /**
     * Enregistre le nouveau rôle d'un collaborateur
     */
    public function saveRole(): void
    {
        if (!$this->editingUserId) return;

        // Interdire de changer son propre rôle pour éviter le lockout accidentel
        if ($this->editingUserId === Auth::id()) {
            session()->flash('error', '❌ Vous ne pouvez pas modifier votre propre rôle pour préserver les accès Admin.');
            $this->reset(['editingUserId', 'editingRole', 'editingContactEmail']);
            return;
        }

        if (!in_array($this->editingRole, ['super-admin', 'operator', 'marketing'])) {
            session()->flash('error', 'Rôle invalide.');
            return;
        }

        $this->validate([
            'editingContactEmail' => 'nullable|email'
        ], [
            'editingContactEmail.email' => 'L\'adresse e-mail de contact doit être valide.'
        ]);

        $user = User::findOrFail($this->editingUserId);
        $oldRole = $user->role;
        $user->update([
            'role' => $this->editingRole,
            'contact_email' => $this->editingContactEmail ?: null,
        ]);

        Log::info("RBAC: Rôle et e-mail de contact modifiés par Admin -> id={$user->id}, ancien_role={$oldRole}, nouveau_role={$user->role}");
        session()->flash('success', "🎭 Le compte de {$user->name} a été mis à jour.");

        $this->reset(['editingUserId', 'editingRole', 'editingContactEmail']);
    }

    /**
     * Supprime définitivement un collaborateur avec anonymisation complète (Conforme RGPD)
     */
    public function deleteMember(string $userId): void
    {
        if ($userId === Auth::id()) {
            session()->flash('error', '❌ Vous ne pouvez pas supprimer votre propre compte Super-Admin.');
            return;
        }

        $user = User::findOrFail($userId);

        Log::info("RBAC: Début anonymisation et suppression du collaborateur {$user->id}");

        // Format unique de tag pour masquer l'identité réelle (RGPD) tout en conservant l'historique
        $shortTag = strtoupper(substr(md5($user->id), 0, 4));
        $shortHash = substr(md5($user->id), 0, 16);

        $user->forceFill([
            'name'               => "Ex-Collaborateur {$shortTag}",
            'email'              => "deleted_staff_{$shortHash}@data.deleted",
            'password'           => Hash::make(Str::random(64)), // Invalider le login
            'remember_token'     => null,
            'stripe_id'          => null,
            'suspended_at'       => null, // Plus besoin de suspendre un compte supprimé
            'anonymized_at'      => now(),
        ])->save();

        // Soft-delete
        $user->delete();

        Log::info("RBAC: Collaborateur {$userId} supprimé et anonymisé.");
        session()->flash('success', '🗑 Collaborateur supprimé et anonymisé avec succès (RGPD).');
    }

    public function with(): array
    {
        $query = User::whereIn('role', ['super-admin', 'operator', 'marketing']);

        // Recherche par nom/email
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Filtre par rôle
        if ($this->roleFilter !== 'all') {
            $query->where('role', $this->roleFilter);
        }

        return [
            'collaborators' => $query->latest()->paginate(10),
        ];
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedRoleFilter(): void { $this->resetPage(); }
}; ?>

<div class="pb-12">
    {{-- En-tête --}}
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8] flex items-center gap-2">
                <svg class="w-7 h-7 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Gestion de l'Équipe & Rôles (RBAC)
            </h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Gérez les comptes de vos collaborateurs, configurez leurs rôles et suivez vos quotas de sièges actifs.</p>
        </div>
        
        {{-- Quota Widget --}}
        <div class="card-glass px-5 py-4 flex flex-col justify-center border-[#C9A84C]/20 bg-[#0F0C08] max-w-sm">
            <div class="flex items-center justify-between text-xs mb-2">
                <span class="text-[#7A6E5E] font-medium uppercase tracking-wider">Sièges collaborateurs :</span>
                <span class="font-bold {{ $this->seatsCount >= 9 ? 'text-red-400' : 'text-[#C9A84C]' }}">{{ $this->seatsCount }} / 10</span>
            </div>
            <div class="w-48 sm:w-60 bg-white/5 h-2 rounded-full overflow-hidden border border-[#C9A84C]/10">
                <div class="h-full rounded-full transition-all duration-500
                            {{ $this->seatsCount >= 9 ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]' : 'bg-gradient-to-r from-[#C9A84C] to-[#E5C158]' }}"
                     style="width: {{ ($this->seatsCount / 10) * 100 }}%"></div>
            </div>
            @if($this->seatsCount >= 10)
            <span class="text-[10px] text-red-400 font-bold mt-1.5 uppercase tracking-wide">⚠️ Limite de licence atteinte</span>
            @endif
        </div>
    </div>

    {{-- Onglets Premium --}}
    <div class="flex items-center gap-2 border-b border-[#C9A84C]/10 mb-8 pb-px overflow-x-auto no-scrollbar">
        <button wire:click="$set('activeTab', 'members')" 
                class="px-5 py-3 text-xs tracking-wider uppercase font-bold border-b-2 transition-all whitespace-nowrap
                       {{ $activeTab === 'members' ? 'border-[#C9A84C] text-[#C9A84C] bg-[#C9A84C]/5 font-black' : 'border-transparent text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
            Membres de l'Equipe
        </button>
        <button wire:click="$set('activeTab', 'rbac')" 
                class="px-5 py-3 text-xs tracking-wider uppercase font-bold border-b-2 transition-all whitespace-nowrap
                       {{ $activeTab === 'rbac' ? 'border-[#C9A84C] text-[#C9A84C] bg-[#C9A84C]/5 font-black' : 'border-transparent text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
            Matrice RBAC
        </button>
        <button wire:click="$set('activeTab', 'diagrams')" 
                class="px-5 py-3 text-xs tracking-wider uppercase font-bold border-b-2 transition-all whitespace-nowrap
                       {{ $activeTab === 'diagrams' ? 'border-[#C9A84C] text-[#C9A84C] bg-[#C9A84C]/5 font-black' : 'border-transparent text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
            Cycle de vie & Diagrammes
        </button>
    </div>

    {{-- Alertes Flash --}}
    @if (session('success'))
    <div class="card-glass p-4 mb-6 border border-emerald-500/30 bg-emerald-500/5 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if (session('error'))
    <div class="card-glass p-4 mb-6 border border-red-500/30 bg-red-500/5 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    @if($activeTab === 'members')
    {{-- Zone de Filtres et Action --}}
    <div class="flex flex-col lg:flex-row gap-4 justify-between items-stretch lg:items-center mb-6">
        <div class="flex flex-col sm:flex-row gap-3 flex-1 max-w-2xl">
            {{-- Barre de recherche --}}
            <div class="relative flex-1">
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Rechercher par nom ou email..."
                       class="w-full bg-[#0F0C08] border border-[#C9A84C]/25 text-[#F5F0E8] placeholder-[#7A6E5E] text-xs px-4 py-3 rounded-sm focus:outline-none focus:border-[#C9A84C] transition-all font-mono" />
                @if($search)
                <button wire:click="$set('search', '')" class="absolute right-3 top-3.5 text-[#7A6E5E] hover:text-[#F5F0E8]">✕</button>
                @endif
            </div>

            {{-- Filtre Rôle --}}
            <select wire:model.live="roleFilter"
                    class="bg-[#0F0C08] border border-[#C9A84C]/25 text-[#F5F0E8] text-xs px-4 py-3 rounded-sm focus:outline-none focus:border-[#C9A84C] font-mono">
                <option value="all">Tous les rôles</option>
                <option value="super-admin">Super Admin</option>
                <option value="operator">Opérateur</option>
                <option value="marketing">Marketing</option>
            </select>
        </div>

        {{-- Bouton d'ajout --}}
        <div>
            @if($this->seatsCount < 10)
            <button @click="$wire.showCreateModal = true"
                    class="btn-gold text-xs px-8 py-3.5 tracking-[0.2em] font-black uppercase transition-all hover:scale-105 active:scale-95 flex items-center justify-center gap-2 w-full lg:w-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Ajouter un membre
            </button>
            @else
            <button disabled 
                    class="bg-white/5 border border-white/10 text-white/40 cursor-not-allowed text-xs px-8 py-3.5 tracking-[0.2em] font-black uppercase rounded-sm w-full lg:w-auto flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Quota Exdu
            </button>
            @endif
        </div>
    </div>

    {{-- Liste des Collaborateurs --}}
    <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/50 overflow-hidden mb-12">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-[#C9A84C]/10 bg-[#0F0C08] text-[#7A6E5E] text-[10px] tracking-widest font-black uppercase">
                        <th class="py-4 px-6">Collaborateur</th>
                        <th class="py-4 px-6">Rôle principal</th>
                        <th class="py-4 px-6">Dernière Connexion</th>
                        <th class="py-4 px-6">Statut</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#C9A84C]/5 text-sm text-[#F5F0E8]/90">
                    @forelse($collaborators as $col)
                    <tr class="hover:bg-[#C9A84C]/5 transition-colors duration-150 {{ $col->isSuspended() ? 'opacity-60 bg-red-950/5' : '' }}">
                        {{-- Avatar & Nom/Email --}}
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs shrink-0
                                            {{ $col->role === 'super-admin'
                                               ? 'border-2 border-[#C9A84C] bg-[#C9A84C]/20 text-[#C9A84C]'
                                               : 'border border-[#C9A84C]/30 bg-[#1A1510] text-[#7A6E5E]' }}">
                                    {{ strtoupper(substr($col->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold truncate text-[#F5F0E8]">{{ $col->name }}</p>
                                    <p class="text-[#7A6E5E] text-xs font-mono truncate mt-0.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-[#7A6E5E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                        {{ $col->email }}
                                    </p>
                                    @if($col->contact_email)
                                    <p class="text-[#C9A84C]/95 text-[10px] font-bold font-mono truncate mt-1 flex items-center gap-1 bg-[#C9A84C]/5 border border-[#C9A84C]/15 px-1.5 py-0.5 rounded-sm max-w-max">
                                        <svg class="w-3 h-3 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Contact : {{ $col->contact_email }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Rôle (éditable ou badge) --}}
                        <td class="py-4 px-6 font-mono text-xs">
                            @if($editingUserId === $col->id)
                            <div class="flex flex-col gap-2 bg-[#1A1510]/50 p-2 border border-[#C9A84C]/15 rounded-sm">
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] uppercase tracking-wider text-[#7A6E5E] font-bold">Rôle</label>
                                    <select wire:model="editingRole"
                                            class="bg-[#0F0C08] border border-[#C9A84C]/30 text-[#F5F0E8] px-2 py-1.5 rounded-sm focus:outline-none focus:border-[#C9A84C] text-xs font-mono">
                                        <option value="super-admin">Super Admin</option>
                                        <option value="operator">Opérateur</option>
                                        <option value="marketing">Marketing</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] uppercase tracking-wider text-[#7A6E5E] font-bold">E-mail de Contact (Réel)</label>
                                    <input type="email"
                                           wire:model="editingContactEmail"
                                           placeholder="alain@gmail.com (optionnel)"
                                           class="bg-[#0F0C08] border border-[#C9A84C]/30 text-[#F5F0E8] px-2 py-1.5 rounded-sm focus:outline-none focus:border-[#C9A84C] text-xs font-mono w-full" />
                                    @error('editingContactEmail')
                                    <span class="text-red-400 text-[10px] font-mono mt-0.5">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="flex items-center gap-1.5 mt-1 border-t border-[#C9A84C]/10 pt-2">
                                    <button wire:click="saveRole" class="px-2.5 py-1.5 bg-[#C9A84C] text-[#0D0B08] font-black rounded-sm text-[10px] uppercase hover:bg-[#E5C158]">✓ Sauver</button>
                                    <button wire:click="$reset(['editingUserId', 'editingRole', 'editingContactEmail'])" class="px-2.5 py-1.5 border border-[#3A3028] text-[#7A6E5E] rounded-sm text-[10px] uppercase hover:text-[#F5F0E8]">✕</button>
                                </div>
                            </div>
                            @else
                            <div class="flex items-center gap-2">
                                @if($col->role === 'super-admin')
                                <span class="bg-[#C9A84C]/10 border border-[#C9A84C]/30 text-[#C9A84C] text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider">Super Admin</span>
                                @elseif($col->role === 'operator')
                                <span class="bg-blue-900/10 border border-blue-800/30 text-blue-400 text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider">Opérateur</span>
                                @else
                                <span class="bg-emerald-900/10 border border-emerald-800/30 text-emerald-400 text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider">Marketing</span>
                                @endif

                                @if($col->id !== Auth::id())
                                <button wire:click="startEditRole('{{ $col->id }}')" 
                                        class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors p-1"
                                        title="Modifier le collaborateur">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                @endif
                            </div>
                            @endif
                        </td>

                        {{-- Dernière Connexion --}}
                        <td class="py-4 px-6 text-xs font-mono text-[#7A6E5E]">
                            {{ $col->last_login_at ? $col->last_login_at->format('d/m/Y à H:i') : 'Jamais' }}
                        </td>

                        {{-- Statut --}}
                        <td class="py-4 px-6">
                            @if($col->isSuspended())
                            <span class="inline-flex items-center gap-1.5 text-xs text-red-400 font-bold bg-red-950/20 border border-red-800/30 px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                Suspendu
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 text-xs text-emerald-400 font-bold bg-emerald-950/20 border border-emerald-800/30 px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Actif
                            </span>
                            @endif
                        </td>

                        {{-- Actions rapides --}}
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($col->id !== Auth::id())
                                {{-- Suspendre / Activer --}}
                                <button wire:click="toggleSuspension('{{ $col->id }}')"
                                        class="px-3 py-1.5 text-xs rounded-sm transition-all duration-150 border sm:w-28 text-center
                                               {{ $col->isSuspended() 
                                                  ? 'border-emerald-500/30 text-emerald-400/90 hover:bg-emerald-500/10' 
                                                  : 'border-red-500/30 text-red-400/90 hover:bg-red-500/10' }}">
                                    {{ $col->isSuspended() ? 'Réactiver' : 'Suspendre' }}
                                </button>

                                {{-- Supprimer définitivement (RGPD) --}}
                                <button @click="const wire = $wire; omnyConfirm({
                                            title: 'Supprimer ce collaborateur',
                                            message: 'Le collaborateur sera définitivement retiré de l\'équipe. Ses données personnelles seront anonymisées de façon irréversible sous le tag Ex-Collaborateur, tandis que son historique d\'actions sera conservé de manière anonyme (Art. 17 RGPD).',
                                            confirmLabel: 'Supprimer',
                                            danger: true
                                        }).then(() => wire.deleteMember('{{ $col->id }}'))"
                                        class="p-2 border border-red-500/20 text-red-500/60 hover:text-red-400 hover:border-red-500/50 hover:bg-red-500/5 rounded-sm transition-all"
                                        title="Supprimer définitivement (RGPD)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                @else
                                <span class="text-[10px] font-mono text-[#7A6E5E] italic">Votre compte</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-[#7A6E5E] text-xs">
                            Aucun collaborateur trouvé correspondant à vos critères.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($collaborators->hasPages())
        <div class="px-6 py-4 border-t border-[#C9A84C]/10">
            {{ $collaborators->links() }}
        </div>
        @endif
    </div>
    @elseif($activeTab === 'rbac')
    {{-- Permissions Matrix (Matrice de Permissions) --}}
    <div class="mb-12">
        <h2 class="text-lg font-bold text-[#F5F0E8] mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Matrice de Permissions Auditable (RBAC)
        </h2>
        
        <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/30 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[#C9A84C]/10 bg-[#0F0C08] text-[#7A6E5E] text-[9px] tracking-widest font-black uppercase">
                            <th class="py-4 px-6 w-1/3">Fonctionnalité / Permission</th>
                            <th class="py-4 px-6 text-center">Super Admin</th>
                            <th class="py-4 px-6 text-center">Opérateur</th>
                            <th class="py-4 px-6 text-center">Marketing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#C9A84C]/5 text-xs text-[#F5F0E8]/80 font-mono">
                        {{-- 1. Finances --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Visualiser les revenus (Finances / CA)</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Accès total</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                        </tr>
                        {{-- 2. Gouvernance --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Gérer l'équipe & les rôles (RBAC)</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Accès total</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                        </tr>
                        {{-- 3. Commandes --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Gérer et livrer les Commandes</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Accès total</td>
                            <td class="py-4 px-6 text-center text-blue-400 font-bold">✓ Traitement</td>
                            <td class="py-4 px-6 text-center text-[#C9A84C] font-bold">✓ Lecture</td>
                        </tr>
                        {{-- 4. Support --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Traiter les tickets support client</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Accès total</td>
                            <td class="py-4 px-6 text-center text-blue-400 font-bold">✓ Support client</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                        </tr>
                        {{-- 5. Coupons & Avis --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Gérer les coupons de réduction & Avis</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Accès total</td>
                            <td class="py-4 px-6 text-center text-red-500/50">✕ Aucun accès</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Marketing</td>
                        </tr>
                        {{-- 6. Transparence --}}
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-4 px-6 font-bold text-[#F5F0E8]">Accéder au dashboard de Transparence (Loi UE)</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Lecture</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Lecture</td>
                            <td class="py-4 px-6 text-center text-emerald-400 font-bold">✓ Lecture</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif($activeTab === 'diagrams')
    {{-- Diagrammes & Cycle de vie --}}
    <div class="mb-12" 
         x-data="{ 
             initMermaid() {
                 this.$nextTick(() => {
                     if (window.mermaid) {
                         window.mermaid.init(undefined, document.querySelectorAll('.mermaid'));
                     }
                 });
             }
         }" 
         x-init="
             if (!window.mermaid) {
                 let script = document.createElement('script');
                 script.src = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js';
                 script.onload = () => {
                     window.mermaid.initialize({ 
                         startOnLoad: false, 
                         theme: 'dark',
                         themeVariables: {
                             background: '#0F0C08',
                             primaryColor: '#1A1510',
                             primaryTextColor: '#F5F0E8',
                             primaryBorderColor: '#C9A84C',
                             lineColor: '#C9A84C',
                             secondaryColor: '#151C15',
                             tertiaryColor: '#1F1313'
                         }
                     });
                     window.mermaid.init(undefined, document.querySelectorAll('.mermaid'));
                 };
                 document.head.appendChild(script);
             } else {
                 window.mermaid.init(undefined, document.querySelectorAll('.mermaid'));
             }
             $watch('activeTab', () => initMermaid());
             document.addEventListener('livewire:navigated', () => initMermaid());
         }">
        <h2 class="text-lg font-bold text-[#F5F0E8] mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Documentation Technique & Workflows
        </h2>
        <p class="text-[#7A6E5E] text-xs mb-8">Visualisation des processus de gouvernance, des cycles opérationnels et de marketing de la plateforme.</p>

        <div class="space-y-12">
            {{-- 1. State Diagram / Cycle de vie --}}
            <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/30 p-6 sm:p-8">
                <h3 class="text-sm uppercase tracking-wider font-black text-[#C9A84C] mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#C9A84C] animate-pulse"></span>
                    Diagramme d'Etat : Cycle de Vie d'un Compte Staff
                </h3>

                <div wire:ignore class="mermaid flex justify-center bg-[#0F0C08]/50 p-6 rounded border border-[#C9A84C]/10 overflow-x-auto">
stateDiagram-v2
    [*] --> Invitation : Envoi du lien (Admin)
    Invitation --> Actif : Inscription validée
    Actif --> Suspendu : Action Admin (Quota atteint/Départ)
    Suspendu --> Actif : Réactivation
    Actif --> Anonymisé : Suppression RGPD (Art. 17)
    Anonymisé --> [*]
                </div>
            </div>

            {{-- 2. Sequence Diagram / Workflow Commande --}}
            <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/30 p-6 sm:p-8">
                <h3 class="text-sm uppercase tracking-wider font-black text-[#C9A84C] mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#C9A84C] animate-pulse"></span>
                    Diagramme de Sequence : Prise en charge d'une Commande
                </h3>

                <div wire:ignore class="mermaid flex justify-center bg-[#0F0C08]/50 p-6 rounded border border-[#C9A84C]/10 overflow-x-auto">
sequenceDiagram
    participant C as Client
    participant O as Opérateur
    participant S as Système (Audit Log)
    participant A as Admin

    C->>S: Crée une commande (PENDING)
    O->>S: Sélectionne "Me l'assigner"
    S-->>O: Marque operator_id = O.id
    S-->>A: Notifie l'Admin de la prise en charge
    O->>S: Upload les retouches HD
    O->>S: Valide le passage en DONE
    S->>C: Envoie l'email de paiement
    S->>S: Incrémente KPI (Completed) pour Opérateur O
                </div>
            </div>

            {{-- 3. Flowchart / Processus Marketing --}}
            <div class="card-glass border-[#C9A84C]/10 bg-[#0F0C08]/30 p-6 sm:p-8">
                <h3 class="text-sm uppercase tracking-wider font-black text-[#C9A84C] mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#C9A84C] animate-pulse"></span>
                    Flowchart : Processus de Campagne Promo (Mass Mail)
                </h3>

                <div wire:ignore class="mermaid flex justify-center bg-[#0F0C08]/50 p-6 rounded border border-[#C9A84C]/10 overflow-x-auto">
graph TD
    A[Début Campagne] --> B{Filtre Client}
    B -->|High Spend| C[Segment Premium]
    B -->|Inactif 30j| D[Segment Relance]
    C --> E[Appliquer Coupon Spécifique]
    D --> E
    E --> F[Vérifier Consentement RGPD]
    F -->|Oui| G[Envoi via Assistant IA]
    F -->|Non| H[Exclure du listing]
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- MODAL : AJOUT COMPTE COLLABORATEUR --}}
    <div x-show="$wire.showCreateModal" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-[#060504]/90 backdrop-blur-sm" @click="$wire.showCreateModal = false"></div>

        {{-- Modal Content --}}
        <div class="flex items-center justify-center min-h-screen p-4 relative">
            <div class="bg-[#0F0C08] border border-[#C9A84C]/25 rounded-sm p-8 max-w-md w-full shadow-2xl relative">
                <button @click="$wire.showCreateModal = false" class="absolute top-4 right-4 text-[#7A6E5E] hover:text-[#F5F0E8] text-sm">✕</button>

                <h3 class="text-[#F5F0E8] text-xl font-bold uppercase tracking-wider border-b border-[#C9A84C]/15 pb-4 mb-6">
                    Nouveau Collaborateur
                </h3>

                {{-- Quota check local error --}}
                @error('quota')
                <div class="p-3 bg-red-950/20 border border-red-800/30 text-red-400 text-xs rounded-sm mb-4">
                    {{ $message }}
                </div>
                @enderror

                <form wire:submit.prevent="addMember" class="space-y-4">
                    {{-- Nom --}}
                    <div>
                        <label class="block text-[10px] text-[#7A6E5E] font-bold uppercase tracking-widest mb-1.5">Nom Complet</label>
                        <input type="text" 
                               wire:model="newMemberName"
                               placeholder="Ex: Alain Guillon"
                               class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs px-3 py-2.5 rounded-sm focus:outline-none focus:border-[#C9A84C] transition-all font-mono" />
                        @error('newMemberName') <span class="text-red-400 text-[10px] mt-1 block font-mono">{{ $message }}</span> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-[10px] text-[#7A6E5E] font-bold uppercase tracking-widest mb-1.5">Adresse E-mail de Connexion (Fictive ou Interne)</label>
                        <input type="email" 
                               wire:model="newMemberEmail"
                               placeholder="Ex: collab1@omny.internal"
                               class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs px-3 py-2.5 rounded-sm focus:outline-none focus:border-[#C9A84C] transition-all font-mono" />
                        <span class="text-[10px] text-[#7A6E5E] mt-1 block">Sert uniquement d'identifiant pour se connecter.</span>
                        @error('newMemberEmail') <span class="text-red-400 text-[10px] mt-1 block font-mono">{{ $message }}</span> @enderror
                    </div>

                    {{-- Contact Email --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-[10px] text-[#7A6E5E] font-bold uppercase tracking-widest">Adresse E-mail de Sécurité (Réelle)</label>
                            <span class="text-[9px] text-[#C9A84C] font-bold">Optionnel</span>
                        </div>
                        <input type="email" 
                               wire:model="newMemberContactEmail"
                               placeholder="Ex: alain.guillon.contact@gmail.com"
                               class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs px-3 py-2.5 rounded-sm focus:outline-none focus:border-[#C9A84C] transition-all font-mono" />
                        <span class="text-[10px] text-[#7A6E5E] mt-1 block">Reçoit toutes les notifications réelles (réinitialisation, invitations) pour éviter toute compromission.</span>
                        @error('newMemberContactEmail') <span class="text-red-400 text-[10px] mt-1 block font-mono">{{ $message }}</span> @enderror
                    </div>

                    {{-- Rôle --}}
                    <div>
                        <label class="block text-[10px] text-[#7A6E5E] font-bold uppercase tracking-widest mb-1.5">Rôle Principal</label>
                        <select wire:model="newMemberRole"
                                class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs px-3 py-2.5 rounded-sm focus:outline-none focus:border-[#C9A84C] font-mono">
                            <option value="operator">Opérateur (Traitement & Support)</option>
                            <option value="marketing">Marketing (Coupons & Avis)</option>
                            <option value="super-admin">Super Admin (Direction & Finances)</option>
                        </select>
                        @error('newMemberRole') <span class="text-red-400 text-[10px] mt-1 block font-mono">{{ $message }}</span> @enderror
                    </div>

                    {{-- Mot de passe --}}
                    <div>
                        <label class="block text-[10px] text-[#7A6E5E] font-bold uppercase tracking-widest mb-1.5">Mot de Passe Provisoire</label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" 
                                   wire:model="newMemberPassword"
                                   placeholder="8 caractères minimum..."
                                   class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs px-3 py-2.5 pr-16 rounded-sm focus:outline-none focus:border-[#C9A84C] transition-all font-mono" />
                            <button type="button" @click="show = !show" class="absolute right-3 top-2.5 text-[9px] uppercase font-black tracking-widest text-[#C9A84C] hover:text-[#E5C158] transition-colors h-7 flex items-center">
                                <span x-show="!show">Afficher</span>
                                <span x-show="show">Masquer</span>
                            </button>
                        </div>
                        @error('newMemberPassword') <span class="text-red-400 text-[10px] mt-1 block font-mono">{{ $message }}</span> @enderror
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-3 pt-4">
                        <button type="button" 
                                @click="$wire.showCreateModal = false"
                                class="flex-1 px-4 py-3 border border-[#3A3028] text-[#7A6E5E] text-xs font-bold uppercase tracking-wider rounded-sm hover:text-[#F5F0E8] transition-all">
                            Annuler
                        </button>
                        <button type="submit"
                                class="flex-1 px-4 py-3 bg-[#C9A84C] text-[#0D0B08] text-xs font-black uppercase tracking-[0.1em] rounded-sm hover:bg-[#E5C158] transition-all">
                            Créer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
