<?php

use Livewire\Attributes\Reactive;
use Livewire\Component;
use Mortel\Enums\SourceType;

new class extends Component
{
    #[Reactive]
    public string $filterType = '';

    #[Reactive]
    public string $filterRiskLevel = '';

    #[Reactive]
    public string $filterSource = '';

    #[Reactive]
    public bool $filterArchive = false;

    #[Reactive]
    public string $filterStatus = '';

    /** Picks a filter value; picking the active value again clears that filter. */
    public function toggle(string $filter, string $value): void
    {
        $filters = [
            'filterType' => $this->filterType,
            'filterRiskLevel' => $this->filterRiskLevel,
            'filterSource' => $this->filterSource,
            'filterStatus' => $this->filterStatus,
        ];

        if (! array_key_exists($filter, $filters)) {
            return;
        }

        $filters[$filter] = $filters[$filter] === $value ? '' : $value;

        $this->dispatch('set-filter', ...$filters);
    }

    public function setArchive(bool $archive): void
    {
        $this->dispatch(
            'set-filter',
            filterType: $this->filterType,
            filterRiskLevel: $this->filterRiskLevel,
            filterSource: $this->filterSource,
            filterArchive: $archive,
        );
    }

    /**
     * @return array<string, array{label: string, value: string, options: array<string, string>}>
     */
    public function groups(): array
    {
        $groups = [
            'filterType' => ['label' => 'Type', 'value' => $this->filterType, 'options' => [
                'voorstel' => 'Voorstel',
                'notificatie' => 'Notificatie',
                'actie' => 'Actie',
            ]],
            'filterRiskLevel' => ['label' => 'Urgentie', 'value' => $this->filterRiskLevel, 'options' => [
                'low' => 'Laag',
                'medium' => 'Gemiddeld',
                'high' => 'Hoog',
            ]],
            'filterSource' => ['label' => 'Bron', 'value' => $this->filterSource, 'options' => [
                SourceType::AgentRun->value => 'Agent',
                SourceType::WorkflowRun->value => 'Workflow',
                SourceType::AppRegistration->value => 'App',
                SourceType::InboxItem->value => 'Correctie',
                SourceType::Entity->value => 'Optimize',
                SourceType::IngestDecision->value => 'Ingest',
                SourceType::Memory->value => 'Memory',
            ]],
        ];

        if ($this->filterArchive) {
            $groups['filterStatus'] = ['label' => 'Status', 'value' => $this->filterStatus, 'options' => [
                'approved' => 'Goedgekeurd',
                'rejected' => 'Afgewezen',
            ]];
        }

        return $groups;
    }
}; ?>

<div class="relative space-y-3" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    @php $activeCount = count(array_filter([$filterType, $filterRiskLevel, $filterSource, $filterStatus])); @endphp

    <div class="flex items-center gap-1" role="group" aria-label="Inbox modus">
        <x-mortel::filters.chip :active="! $filterArchive" wire:click="setArchive(false)">Actief</x-mortel::filters.chip>
        <x-mortel::filters.chip :active="$filterArchive" wire:click="setArchive(true)">Archief</x-mortel::filters.chip>
    </div>

    <div class="flex items-center gap-2">
        <x-mortel::button size="sm" icon="funnel" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="inbox-filter-groups">
            Filters{{ $activeCount > 0 ? ' ('.$activeCount.')' : '' }}
        </x-mortel::button>
        @if ($activeCount > 0)
            <x-mortel::button size="sm" variant="ghost" wire:click="$dispatch('set-filter', { filterStatus: '' })">Wis filters</x-mortel::button>
        @endif
    </div>

    {{-- Over the list instead of above it, so opening the filters does not push the list down. --}}
    <div id="inbox-filter-groups" class="absolute inset-x-0 top-full z-30 mt-2 space-y-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-800 dark:bg-zinc-900" x-show="open" x-cloak>
        @foreach ($this->groups() as $filter => $group)
            <div wire:key="inbox-filter-{{ $filter }}" role="group" aria-label="{{ $group['label'] }}">
                <x-mortel::text size="sm" class="mb-1">{{ $group['label'] }}</x-mortel::text>
                <div class="flex flex-wrap gap-1">
                    @foreach ($group['options'] as $value => $label)
                        <x-mortel::filters.chip wire:key="inbox-filter-{{ $filter }}-{{ $value }}" :active="$group['value'] === $value" :loading="false" wire:click="toggle('{{ $filter }}', '{{ $value }}')" x-on:click="open = false">{{ $label }}</x-mortel::filters.chip>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
