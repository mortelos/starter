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

    public ?string $trustConfigComponent = null;

    public ?string $learningPatternsComponent = null;

    public ?string $channelStatusComponent = null;

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->roles = $this->governanceResolver()->roles();
        $this->selectedRoleId = $this->roles[0]['id'] ?? '';
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
        $resolver = config('starter.governance.resolver');

        if (! is_string($resolver) || $resolver === '') {
            throw new LogicException('Missing starter governance resolver config [starter.governance.resolver].');
        }

        return app($resolver);
    }

    private function configuredComponent(string $key): ?string
    {
        $component = config($key);

        return is_string($component) && $component !== '' ? $component : null;
    }
}; ?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Governance</h1>
        @if($roles !== [])
            <select wire:model.live="selectedRoleId" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach($roles as $role)
                    <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if($roles === [])
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">Nog geen AI-rollen geconfigureerd.</p>
        </div>
    @else
        <div class="space-y-6">
            @if($statsComponent !== null)
                <livewire:dynamic-component :component="$statsComponent" :role-id="$selectedRoleId" :key="'governance-stats-'.$selectedRoleId" />
            @endif
            @if($trustConfigComponent !== null)
                <livewire:dynamic-component :component="$trustConfigComponent" :role-id="$selectedRoleId" :key="'governance-trust-config-'.$selectedRoleId" />
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
