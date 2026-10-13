<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mortel\Contracts\TenantResolver;
use Mortel\Models\Policy;
use Mortel\Models\Role;

new class extends Component {
    public string $userId = '';

    public ?array $userAccess = null;

    public function mount(string $userId): void
    {
        $this->userId = trim($userId);
        $this->loadUserAccess();
    }

    public function loadUserAccess(): void
    {
        if (! $this->canInspectUser()) {
            $this->userAccess = null;

            return;
        }

        $user = User::query()->whereKey($this->userId)->first();

        if (! $user instanceof User) {
            $this->userAccess = null;

            return;
        }

        // De rol van een lid staat op de tenant-membership (tenant_user.role_id),
        // precies waar het framework hem ook leest (ActorContextResolver).
        $roleId = DB::table('tenant_user')
            ->where('user_id', $user->getKey())
            ->where('tenant_id', app(TenantResolver::class)->id())
            ->value('role_id');
        $role = is_string($roleId) ? Role::query()->find($roleId) : null;

        $this->userAccess = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $role instanceof Role ? [[
                'id' => (string) $role->getKey(),
                'name' => $role->name,
                'description' => $role->description,
                'policies' => Policy::query()
                    ->where('role_id', $role->getKey())
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Policy $policy): array => [
                        'id' => (string) $policy->getKey(),
                        'name' => (string) $policy->name,
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
            ]] : [],
        ];
    }

    private function canInspectUser(): bool
    {
        $resolver = config('starter.users.access_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return false;
        }

        $service = app($resolver);

        return method_exists($service, 'canInspect')
            && (bool) $service->canInspect($this->userId);
    }
}; ?>

<div class="flex h-full flex-col">
    <div class="border-b border-gray-100 px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Toegang</p>
        <h2 class="mt-1 text-xl font-semibold text-gray-950">
            {{ $userAccess['name'] ?? 'Gebruiker' }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ $userAccess['email'] ?? 'Geen gegevens beschikbaar.' }}
        </p>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
        @if($userAccess === null)
            <x-mortel::callout variant="warning" icon="exclamation-triangle" heading="Je hebt geen toegang tot deze gebruiker of de gebruiker bestaat niet meer." />
        @elseif($userAccess['roles'] === [])
            <x-mortel::empty icon="shield-check" heading="Deze gebruiker heeft nog geen rol" />
        @else
            <div class="space-y-4">
                @foreach($userAccess['roles'] as $role)
                    <section class="rounded-lg border border-gray-200 bg-white" wire:key="access-role-{{ $role['id'] }}">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <h3 class="text-sm font-semibold text-gray-950">{{ $role['name'] }}</h3>
                            @if($role['description'])
                                <p class="mt-1 text-sm text-gray-500">{{ $role['description'] }}</p>
                            @endif
                        </div>

                        <div class="p-4">
                            @if($role['policies'] === [])
                                <p class="text-sm text-gray-500">Geen expliciete policies.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($role['policies'] as $policy)
                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm" wire:key="access-policy-{{ $policy['id'] }}">
                                            @foreach($policy['actions'] as $ability)
                                                <span class="flex items-center gap-2" wire:key="access-policy-{{ $policy['id'] }}-{{ $ability['action'] }}">
                                                    <code class="text-xs text-gray-700">{{ $ability['action'] }}</code>
                                                    <x-mortel::badge :color="$ability['effect'] === 'allow' ? 'emerald' : 'red'" size="sm">{{ $ability['effect'] }}</x-mortel::badge>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</div>
