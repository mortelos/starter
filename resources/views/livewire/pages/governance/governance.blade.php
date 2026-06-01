<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Governance')]
class extends Component {
    public string $selectedRoleId = '';

    public array $roles = [];

    public ?string $statsComponent = null;

    public ?string $proposalQueueComponent = null;

    public ?string $trustConfigComponent = null;

    public ?string $learningPatternsComponent = null;

    public ?string $channelStatusComponent = null;

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (! $this->canManageGovernance()) {
            abort(403);
        }

        // The governance resolver is an optional host integration. Until the
        // portal wires one there is no AI-role data source, so render the empty
        // state rather than fatal (the owner still reaches the roles screen via
        // the header link). A configured-but-invalid resolver still throws.
        $resolver = $this->optionalGovernanceResolver();
        $this->roles = $resolver !== null ? $resolver->roles() : [];
        $this->selectedRoleId = $this->roles[0]['id'] ?? '';
        $this->proposalQueueComponent = $this->configuredComponent('starter.governance.proposal_queue_component');
        $this->statsComponent = $this->configuredComponent('starter.governance.stats_component');
        $this->trustConfigComponent = $this->configuredComponent('starter.governance.trust_config_component');
        $this->learningPatternsComponent = $this->configuredComponent('starter.governance.learning_patterns_component');
        $this->channelStatusComponent = $this->configuredComponent('starter.governance.channel_status_component');
    }

    public function updatedSelectedRoleId(): void
    {
        $this->dispatch('role-selected', roleId: $this->selectedRoleId);
    }

    private function governanceResolver(): object
    {
        $resolver = $this->optionalGovernanceResolver();

        if ($resolver === null) {
            throw new LogicException('Missing starter governance resolver config [starter.governance.resolver].');
        }

        return $resolver;
    }

    private function optionalGovernanceResolver(): ?object
    {
        $resolver = config('starter.governance.resolver');

        if (! is_string($resolver) || $resolver === '') {
            return null;
        }

        return app($resolver);
    }

    private function canManageGovernance(): bool
    {
        $resolver = config('starter.governance.access_resolver');

        if (! is_string($resolver) || $resolver === '') {
            // No explicit resolver configured: fall back to the deny-by-default
            // governance gate when the host binds one (keeps config/starter.php
            // untouched). Absence of a binding stays deny-by-default.
            if (app()->bound(\App\Contracts\GovernanceGate::class)) {
                return app(\App\Contracts\GovernanceGate::class)->canManage(auth()->user());
            }

            return false;
        }

        $service = app($resolver);

        if (! method_exists($service, 'canManage')) {
            throw new LogicException("Starter governance access resolver [{$resolver}] must implement canManage().");
        }

        return (bool) $service->canManage(auth()->user());
    }

    private function configuredComponent(string $key): ?string
    {
        $component = config($key);

        return is_string($component) && $component !== '' ? $component : null;
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Mortel Policy Studio</p>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">Governance</h1>
            <p class="mt-1 text-sm text-gray-500">Ontwerp, review en trace policy voorstellen voordat ze actief worden.</p>
        </div>
        <a href="{{ route('governance.roles') }}" wire:navigate
            class="inline-flex items-center gap-1.5 self-start rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 lg:self-auto">
            Rollen &amp; policies
        </a>
        @if($roles !== [])
            <select wire:model.live="selectedRoleId" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach($roles as $role)
                    <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if($proposalQueueComponent !== null)
        <div class="mb-6">
            <livewire:dynamic-component :component="$proposalQueueComponent" :key="'governance-proposal-queue'" />
        </div>
    @endif

    @if($roles === [])
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">Nog geen AI-rollen geconfigureerd.</p>
        </div>
    @else
        <div class="space-y-6">
            @if($trustConfigComponent !== null)
                <livewire:dynamic-component :component="$trustConfigComponent" :role-id="$selectedRoleId" :key="'governance-trust-config-'.$selectedRoleId" />
            @endif
            @if($statsComponent !== null)
                <livewire:dynamic-component :component="$statsComponent" :role-id="$selectedRoleId" :key="'governance-stats-'.$selectedRoleId" />
            @endif
        </div>
    @endif

    {{-- Patronen zijn tenant-breed, niet rol-specifiek --}}
    <div class="mt-6">
        @if($learningPatternsComponent !== null)
            <livewire:dynamic-component :component="$learningPatternsComponent" :key="'governance-learning-patterns'" />
        @endif
    </div>

    {{-- Channel status is tenant-breed --}}
    <div class="mt-6">
        @if($channelStatusComponent !== null)
            <livewire:dynamic-component :component="$channelStatusComponent" :key="'governance-channel-status'" />
        @endif
    </div>
</div>
