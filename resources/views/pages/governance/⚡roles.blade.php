<?php

declare(strict_types=1);

use App\Contracts\GovernanceGate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Actions\Policy\DeletePolicy;
use Mortel\Actions\Role\CreateRole;
use Mortel\Actions\Role\DeleteRole;
use Mortel\Actions\Role\UpdateRole;
use Mortel\Contracts\TenantResolver;
use Mortel\Enums\PolicyEffect;
use Mortel\Enums\PolicyScope;
use Mortel\Exceptions\RoleInUseException;
use Mortel\Models\Policy;
use Mortel\Models\Role;

new
#[Layout('layouts::app')]
#[Title('Rollen & policies')]
class extends Component {
    /** @var array<int, array{id: string, name: string, description: ?string, policies: array<int, array{id: string, name: string, scope: string, actions: array<int, array{action: string, effect: string}>}>}> */
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

    /** @var array<string, string> scope draft, keyed by role id */
    public array $policyScope = [];

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
        $roles = Role::query()->orderBy('name')->get();
        $policies = Policy::query()
            ->whereIn('role_id', $roles->modelKeys())
            ->orderBy('name')
            ->get()
            ->groupBy('role_id');

        $this->roles = $roles
            ->map(fn (Role $role): array => [
                'id' => (string) $role->getKey(),
                'name' => (string) $role->name,
                'description' => $role->description,
                'policies' => $policies->get((string) $role->getKey(), collect())
                    ->map(fn (Policy $policy): array => [
                        'id' => (string) $policy->getKey(),
                        'name' => (string) $policy->name,
                        'scope' => (string) $policy->scope,
                        'actions' => collect(is_array($policy->actions) ? $policy->actions : [])
                            ->map(fn (mixed $effect, string $action): array => [
                                'action' => $action,
                                'effect' => is_string($effect) ? $effect : 'deny',
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
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

        app(CreateRole::class)->handle(
            name: $validated['newRoleName'],
            description: $validated['newRoleDescription'] ?: null,
            trustConfig: [],
            scope: ['all_branches' => true],
            orgId: $this->orgId(),
            branchId: $this->branchId(),
            actor: $this->actor(),
        );

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

        app(UpdateRole::class)->handle(
            roleId: $this->editingRoleId,
            changes: [
                'name' => $validated['editRoleName'],
                'description' => $validated['editRoleDescription'] ?: null,
            ],
            orgId: $this->orgId(),
            branchId: $this->branchId(),
            actor: $this->actor(),
        );

        $this->cancelEditRole();
        $this->loadRoles();
    }

    public function deleteRole(string $roleId): void
    {
        try {
            app(DeleteRole::class)->handle($roleId, $this->orgId(), $this->branchId(), $this->actor());
        } catch (RoleInUseException) {
            $this->addError('roles', 'Deze rol is nog toegewezen aan een gebruiker. Wijs die eerst een andere rol toe.');

            return;
        }

        if ($this->editingRoleId === $roleId) {
            $this->cancelEditRole();
        }

        $this->loadRoles();
    }

    public function addPolicy(string $roleId): void
    {
        $this->policyScope[$roleId] ??= PolicyScope::Policy->value;
        $this->policyEffect[$roleId] ??= PolicyEffect::Allow->value;

        $this->validate([
            "policyAction.{$roleId}" => ['required', 'string', 'max:255'],
            "policyEffect.{$roleId}" => ['required', 'in:allow,deny'],
            "policyScope.{$roleId}" => ['required', 'in:'.implode(',', array_column(PolicyScope::cases(), 'value'))],
        ]);

        Role::query()->findOrFail($roleId);

        $action = trim($this->policyAction[$roleId]);
        $effect = PolicyEffect::from($this->policyEffect[$roleId]);

        app(CreatePolicy::class)->handle(
            name: $action,
            description: null,
            scope: PolicyScope::from($this->policyScope[$roleId]),
            resourceType: null,
            resourceId: null,
            roleId: $roleId,
            actions: [$action => $effect->value],
            effect: $effect,
            priority: 0,
            conditions: null,
            orgId: $this->orgId(),
            branchId: $this->branchId(),
            actor: $this->actor(),
        );

        $this->policyAction[$roleId] = '';
        $this->policyEffect[$roleId] = PolicyEffect::Allow->value;
        $this->policyScope[$roleId] = PolicyScope::Policy->value;
        $this->loadRoles();
    }

    public function deletePolicy(string $policyId): void
    {
        app(DeletePolicy::class)->handle($policyId, $this->orgId(), $this->branchId(), $this->actor());

        $this->loadRoles();
    }

    private function orgId(): string
    {
        $tenantId = app(TenantResolver::class)->id();

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : (string) config('starter.tenancy.default_tenant_id', 'default');
    }

    private function branchId(): string
    {
        return (string) config('starter.tenancy.default_branch_id', 'main');
    }

    private function actor(): string
    {
        return (string) auth()->id();
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-blue-600">Mortel Policy Studio</p>
            <x-mortel::heading size="xl" level="1">Rollen &amp; policies</x-mortel::heading>
            <x-mortel::subheading>Beheer wie wat mag. Toegang is deny-by-default: niemand mag iets totdat een rol een expliciete <span class="font-medium text-zinc-700">allow</span>-policy krijgt.</x-mortel::subheading>
        </div>
        <x-mortel::button :href="route('governance')" wire:navigate icon="arrow-left" variant="ghost" size="sm" class="shrink-0">
            Terug naar Governance
        </x-mortel::button>
    </div>

    <x-mortel::callout class="mb-6" icon="shield-check" variant="secondary">
        <x-mortel::callout.text>
            Een rol zonder <span class="font-medium">allow</span>-policy verleent geen enkele bevoegdheid. Voeg de actie
            <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs">governance.manage</code> toe om beheer van dit scherm te geven,
            of <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs">users.manage</code> voor gebruikersbeheer.
        </x-mortel::callout.text>
    </x-mortel::callout>

    {{-- Nieuwe rol --}}
    <x-mortel::card class="mb-8">
        <x-mortel::heading size="lg" level="2" class="mb-4">Nieuwe rol</x-mortel::heading>

        <form wire:submit="createRole" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <x-mortel::input label="Naam" wire:model="newRoleName" placeholder="bijv. Owner" required />
            </div>
            <div class="flex-1">
                <x-mortel::input label="Omschrijving" wire:model="newRoleDescription" placeholder="Optioneel" />
            </div>
            <x-mortel::button type="submit" variant="primary">Rol toevoegen</x-mortel::button>
        </form>
    </x-mortel::card>

    {{-- Rollen --}}
    @error('roles')
        <x-mortel::callout variant="danger" class="mb-6">{{ $message }}</x-mortel::callout>
    @enderror
    @forelse($roles as $role)
        <x-mortel::card class="mb-6 p-0" wire:key="role-{{ $role['id'] }}">
            <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-6 py-4">
                @if($editingRoleId === $role['id'])
                    <form wire:submit="updateRole" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <x-mortel::input label="Naam" wire:model="editRoleName" required />
                        </div>
                        <div class="flex-1">
                            <x-mortel::input label="Omschrijving" wire:model="editRoleDescription" />
                        </div>
                        <div class="flex gap-2">
                            <x-mortel::button type="submit" variant="primary" size="sm">Opslaan</x-mortel::button>
                            <x-mortel::button type="button" variant="ghost" size="sm" wire:click="cancelEditRole">Annuleren</x-mortel::button>
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
                        <x-mortel::button type="button" variant="ghost" size="sm" wire:click="startEditRole('{{ $role['id'] }}')">Bewerken</x-mortel::button>
                        <x-mortel::button type="button" variant="ghost" size="sm"
                            wire:click="deleteRole('{{ $role['id'] }}')"
                            wire:confirm="Rol '{{ $role['name'] }}' en alle bijbehorende policies verwijderen?"
                            class="text-red-600 hover:text-red-700">Verwijderen</x-mortel::button>
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
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-xs text-zinc-400">{{ $policy['scope'] }}</span>
                                    @foreach($policy['actions'] as $ability)
                                        <span class="flex items-center gap-1.5" wire:key="policy-{{ $policy['id'] }}-{{ $ability['action'] }}">
                                            @if($ability['effect'] === 'allow')
                                                <x-mortel::badge color="teal" size="sm">allow</x-mortel::badge>
                                            @else
                                                <x-mortel::badge color="red" size="sm">deny</x-mortel::badge>
                                            @endif
                                            <code class="text-sm text-zinc-700">{{ $ability['action'] }}</code>
                                        </span>
                                    @endforeach
                                </div>
                                <x-mortel::button type="button" variant="ghost" size="sm"
                                    wire:click="deletePolicy('{{ $policy['id'] }}')"
                                    class="text-red-600 hover:text-red-700">Verwijderen</x-mortel::button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form wire:submit="addPolicy('{{ $role['id'] }}')" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <x-mortel::input label="Actie" wire:model="policyAction.{{ $role['id'] }}" placeholder="bijv. governance.manage" />
                    </div>
                    <div class="w-full sm:w-44">
                        <x-mortel::select label="Scope" wire:model="policyScope.{{ $role['id'] }}" placeholder="policy">
                            @foreach(\Mortel\Enums\PolicyScope::cases() as $scope)
                                <x-mortel::select.option value="{{ $scope->value }}">{{ $scope->value }}</x-mortel::select.option>
                            @endforeach
                        </x-mortel::select>
                    </div>
                    <div class="w-full sm:w-44">
                        <x-mortel::select label="Effect" wire:model="policyEffect.{{ $role['id'] }}" placeholder="allow">
                            <x-mortel::select.option value="allow">allow</x-mortel::select.option>
                            <x-mortel::select.option value="deny">deny</x-mortel::select.option>
                        </x-mortel::select>
                    </div>
                    <x-mortel::button type="submit" variant="filled" size="sm">Policy toevoegen</x-mortel::button>
                </form>
            </div>
        </x-mortel::card>
    @empty
        <x-mortel::empty icon="shield-check" heading="Nog geen rollen" description="Maak hierboven een rol aan om toegang te kunnen verlenen." />
    @endforelse
</div>
