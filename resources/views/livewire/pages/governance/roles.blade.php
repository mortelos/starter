<?php

declare(strict_types=1);

use App\Contracts\GovernanceGate;
use App\Models\Policy;
use App\Models\Role;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Rollen & policies')]
class extends Component {
    /** @var array<int, array{id: string, name: string, description: ?string, policies: array<int, array{id: string, action: string, effect: string}>}> */
    public array $roles = [];

    public string $newRoleName = '';

    public string $newRoleDescription = '';

    public string $editingRoleId = '';

    public string $editRoleName = '';

    public string $editRoleDescription = '';

    /** @var array<string, string> action draft, keyed by role id */
    public array $policyAction = [];

    /** @var array<string, string> effect draft, keyed by role id */
    public array $policyEffect = [];

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (! app(GovernanceGate::class)->canManage(auth()->user())) {
            abort(403);
        }

        $this->loadRoles();
    }

    public function loadRoles(): void
    {
        $this->roles = Role::query()
            ->with('policies')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => (string) $role->getKey(),
                'name' => (string) $role->name,
                'description' => $role->description,
                'policies' => $role->policies
                    ->map(fn (Policy $policy): array => [
                        'id' => (string) $policy->getKey(),
                        'action' => (string) $policy->action,
                        'effect' => (string) $policy->effect,
                    ])
                    ->all(),
            ])
            ->all();
    }

    public function createRole(): void
    {
        $validated = $this->validate([
            'newRoleName' => ['required', 'string', 'max:255'],
            'newRoleDescription' => ['nullable', 'string', 'max:255'],
        ]);

        Role::create([
            'id' => (string) Str::ulid(),
            'name' => $validated['newRoleName'],
            'description' => $validated['newRoleDescription'] ?: null,
        ]);

        $this->newRoleName = '';
        $this->newRoleDescription = '';
        $this->loadRoles();
    }

    public function startEditRole(string $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        $this->editingRoleId = (string) $role->getKey();
        $this->editRoleName = (string) $role->name;
        $this->editRoleDescription = (string) ($role->description ?? '');
    }

    public function cancelEditRole(): void
    {
        $this->editingRoleId = '';
        $this->editRoleName = '';
        $this->editRoleDescription = '';
    }

    public function updateRole(): void
    {
        $validated = $this->validate([
            'editRoleName' => ['required', 'string', 'max:255'],
            'editRoleDescription' => ['nullable', 'string', 'max:255'],
        ]);

        Role::query()->findOrFail($this->editingRoleId)->update([
            'name' => $validated['editRoleName'],
            'description' => $validated['editRoleDescription'] ?: null,
        ]);

        $this->cancelEditRole();
        $this->loadRoles();
    }

    public function deleteRole(string $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);
        $role->policies()->delete();
        $role->users()->detach();
        $role->delete();

        if ($this->editingRoleId === $roleId) {
            $this->cancelEditRole();
        }

        $this->loadRoles();
    }

    public function addPolicy(string $roleId): void
    {
        $action = trim($this->policyAction[$roleId] ?? '');
        $effect = $this->policyEffect[$roleId] ?? 'allow';

        $this->validate([
            "policyAction.{$roleId}" => ['required', 'string', 'max:255'],
            "policyEffect.{$roleId}" => ['required', 'in:allow,deny'],
        ]);

        Role::query()->findOrFail($roleId);

        Policy::create([
            'id' => (string) Str::ulid(),
            'role_id' => $roleId,
            'action' => $action,
            'effect' => $effect,
        ]);

        $this->policyAction[$roleId] = '';
        $this->policyEffect[$roleId] = 'allow';
        $this->loadRoles();
    }

    public function deletePolicy(string $policyId): void
    {
        Policy::query()->findOrFail($policyId)->delete();

        $this->loadRoles();
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Mortel Policy Studio</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-900">Rollen &amp; policies</h1>
            <p class="mt-1 text-sm text-zinc-500">Beheer wie wat mag. Toegang is deny-by-default: niemand mag iets totdat een rol een expliciete <span class="font-medium text-zinc-700">allow</span>-policy krijgt.</p>
        </div>
        <a href="{{ route('governance') }}" wire:navigate
            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50">
            <flux:icon name="arrow-left" variant="micro" />
            Terug naar Governance
        </a>
    </div>

    <flux:callout class="mb-6" icon="shield-check" variant="secondary">
        <flux:callout.text>
            Een rol zonder <span class="font-medium">allow</span>-policy verleent geen enkele bevoegdheid. Voeg de actie
            <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs">governance.manage</code> toe om beheer van dit scherm te geven,
            of <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs">users.manage</code> voor gebruikersbeheer.
        </flux:callout.text>
    </flux:callout>

    {{-- Nieuwe rol --}}
    <div class="mb-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900">Nieuwe rol</h2>

        <form wire:submit="createRole" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:input label="Naam" wire:model="newRoleName" placeholder="bijv. Owner" required />
            </div>
            <div class="flex-1">
                <flux:input label="Omschrijving" wire:model="newRoleDescription" placeholder="Optioneel" />
            </div>
            <flux:button type="submit" variant="primary">Rol toevoegen</flux:button>
        </form>
    </div>

    {{-- Rollen --}}
    @forelse($roles as $role)
        <div class="mb-6 rounded-xl border border-zinc-200 bg-white shadow-sm" wire:key="role-{{ $role['id'] }}">
            <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-6 py-4">
                @if($editingRoleId === $role['id'])
                    <form wire:submit="updateRole" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <flux:input label="Naam" wire:model="editRoleName" required />
                        </div>
                        <div class="flex-1">
                            <flux:input label="Omschrijving" wire:model="editRoleDescription" />
                        </div>
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary" size="sm">Opslaan</flux:button>
                            <flux:button type="button" variant="ghost" size="sm" wire:click="cancelEditRole">Annuleren</flux:button>
                        </div>
                    </form>
                @else
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900">{{ $role['name'] }}</h3>
                        @if($role['description'])
                            <p class="mt-0.5 text-sm text-zinc-500">{{ $role['description'] }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button type="button" variant="ghost" size="sm" wire:click="startEditRole('{{ $role['id'] }}')">Bewerken</flux:button>
                        <flux:button type="button" variant="ghost" size="sm"
                            wire:click="deleteRole('{{ $role['id'] }}')"
                            wire:confirm="Rol '{{ $role['name'] }}' en alle bijbehorende policies verwijderen?"
                            class="text-red-600 hover:text-red-700">Verwijderen</flux:button>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Policies</p>

                @if($role['policies'] === [])
                    <p class="mb-4 text-sm text-zinc-500">Geen policies. Deze rol verleent niets (deny-by-default).</p>
                @else
                    <div class="mb-4 space-y-2">
                        @foreach($role['policies'] as $policy)
                            <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-100 bg-zinc-50/60 px-4 py-2.5" wire:key="policy-{{ $policy['id'] }}">
                                <div class="flex items-center gap-3">
                                    @if($policy['effect'] === 'allow')
                                        <flux:badge color="teal" size="sm">allow</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">deny</flux:badge>
                                    @endif
                                    <code class="text-sm text-zinc-700">{{ $policy['action'] }}</code>
                                </div>
                                <button type="button" wire:click="deletePolicy('{{ $policy['id'] }}')"
                                    class="text-sm font-medium text-red-600 transition hover:text-red-700">Verwijderen</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form wire:submit="addPolicy('{{ $role['id'] }}')" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <flux:input label="Actie" wire:model="policyAction.{{ $role['id'] }}" placeholder="bijv. governance.manage" />
                    </div>
                    <div class="w-full sm:w-44">
                        <flux:select label="Effect" wire:model="policyEffect.{{ $role['id'] }}" placeholder="allow">
                            <flux:select.option value="allow">allow</flux:select.option>
                            <flux:select.option value="deny">deny</flux:select.option>
                        </flux:select>
                    </div>
                    <flux:button type="submit" variant="filled" size="sm">Policy toevoegen</flux:button>
                </form>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <p class="text-zinc-500">Nog geen rollen. Maak hierboven een rol aan om toegang te kunnen verlenen.</p>
        </div>
    @endforelse
</div>
