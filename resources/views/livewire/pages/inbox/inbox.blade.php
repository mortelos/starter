<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Inbox')]
class extends Component {
    #[Url]
    public string $filterType = '';

    #[Url]
    public string $filterRiskLevel = '';

    #[Url]
    public string $filterSource = '';

    #[Url(as: 'archive')]
    public bool $filterArchive = false;

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'item')]
    public string $selectedItemId = '';

    public bool $isReturning = false;

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $lastVisit = session('last_inbox_at');
        if ($lastVisit !== null) {
            $this->isReturning = \Carbon\Carbon::parse($lastVisit)->lt(now()->subDays(5));
        }
        session(['last_inbox_at' => now()->toIso8601String()]);
    }

    #[On('set-filter')]
    public function onSetFilter(
        string $filterType = '',
        string $filterRiskLevel = '',
        string $filterSource = '',
        ?bool $filterArchive = null,
        ?string $filterStatus = null,
    ): void {
        $this->filterType = $filterType;
        $this->filterRiskLevel = $filterRiskLevel;
        $this->filterSource = $filterSource;

        if ($filterArchive !== null && $filterArchive !== $this->filterArchive) {
            $this->filterArchive = $filterArchive;
            $this->filterStatus = '';
            $this->selectedItemId = '';
        }

        if ($filterStatus !== null) {
            $this->filterStatus = $filterStatus;
        }
    }

    #[On('item-selected')]
    public function onItemSelected(string $itemId): void
    {
        $this->selectedItemId = $itemId;
    }

    #[On('item-approved')]
    public function onItemApproved(): void
    {
        $this->selectedItemId = '';
    }

    #[On('item-rejected')]
    public function onItemRejected(): void
    {
        $this->selectedItemId = '';
    }

    /**
     * Detail-type voor het geselecteerde item: intake-types krijgen
     * een chat+widgets-pane, andere types krijgen de bestaande
     * approval-flow.
     */
    #[Computed]
    public function selectedItemType(): string
    {
        $resolver = config('starter.inbox.item_type_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return '';
        }

        return (string) app($resolver)->resolve($this->selectedItemId);
    }

    public function isIntakeType(): bool
    {
        $intakeTypes = config('starter.inbox.intake_detail_types', []);

        if (! is_array($intakeTypes)) {
            return false;
        }

        return in_array($this->selectedItemType, $intakeTypes, true);
    }
}; ?>

<div class="-mx-6 -my-6 flex h-[calc(100dvh-3.5rem)] overflow-hidden lg:-mx-8 lg:-my-8 lg:h-dvh" x-data="{
    showDetail: @js($selectedItemId !== ''),
    isMobile: window.innerWidth < 768,
    isTablet: window.innerWidth >= 768 && window.innerWidth < 1024,
}"
    @resize.window="isMobile = window.innerWidth < 768; isTablet = window.innerWidth >= 768 && window.innerWidth < 1024"
    x-on:item-selected.window="showDetail = true"
    x-on:item-approved.window="showDetail = false"
    x-on:item-rejected.window="showDetail = false"
>
    {{-- Left panel: filters + list --}}
    <nav
        aria-label="Inbox navigatie"
        class="flex min-h-0 flex-col overflow-y-auto border-r border-zinc-200"
        :class="{
            'hidden': (isMobile || isTablet) && showDetail,
            'w-full': isMobile && !showDetail,
            'w-72': isTablet && !showDetail,
            'w-80 flex-none': !isMobile && !isTablet
        }"
    >
        <div class="px-3 pt-3">
            <livewire:inbox.inbox-filter
                :filter-type="$filterType"
                :filter-risk-level="$filterRiskLevel"
                :filter-source="$filterSource"
                :filter-archive="$filterArchive"
                :filter-status="$filterStatus"
            />
        </div>
        <div class="flex-1 overflow-y-auto px-3 pb-4">
            <livewire:inbox.inbox-list
                :filter-type="$filterType"
                :filter-risk-level="$filterRiskLevel"
                :filter-source="$filterSource"
                :filter-archive="$filterArchive"
                :filter-status="$filterStatus"
                :selected-item-id="$selectedItemId"
                :is-returning="$isReturning"
            />
        </div>
    </nav>

    {{-- Right panel: detail --}}
    <div
        class="min-h-0 min-w-0 flex-1 overflow-hidden"
        x-show="(!isMobile && !isTablet) || showDetail"
        x-cloak
    >
        <div class="border-b border-zinc-200 p-3 lg:hidden" x-show="showDetail">
            <button
                wire:click="$dispatch('item-selected', { itemId: '' })"
                class="flex items-center gap-1 text-sm text-zinc-600 hover:text-zinc-900 min-h-[44px]"
                x-on:click="showDetail = false"
            >
                <flux:icon.arrow-left class="size-4" />
                Terug naar lijst
            </button>
        </div>
        @if ($this->isIntakeType())
            <livewire:inbox.intake-detail
                :item-id="$selectedItemId"
                :key="'intake-'.$selectedItemId" />
        @else
            <livewire:inbox.inbox-detail :item-id="$selectedItemId" />
        @endif
    </div>
</div>
