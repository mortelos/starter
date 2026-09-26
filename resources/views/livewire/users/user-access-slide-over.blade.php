<?php

use App\Models\User;
use Livewire\Component;

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

        $user = User::query()
            ->with('roles.policies')
            ->whereKey($this->userId)
            ->first();

        if (! $user instanceof User) {
            $this->userAccess = null;

            return;
        }

        $this->userAccess = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles
                ->map(fn ($role): array => [
                    'name' => $role->name,
                    'description' => $role->description,
                    'policies' => $role->policies
                        ->sortBy('action')
                        ->map(fn ($policy): array => [
                            'action' => $policy->action,
                            'effect' => $policy->effect,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
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
                    <section class="rounded-lg border border-gray-200 bg-white">
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
                                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm">
                                            <code class="text-xs text-gray-700">{{ $policy['action'] }}</code>
                                            <x-mortel::badge :color="$policy['effect'] === 'allow' ? 'emerald' : 'red'" size="sm">{{ $policy['effect'] }}</x-mortel::badge>
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
