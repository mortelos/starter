<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;
use Mortel\Models\InboxItem;

new class extends Component
{
    use WithPagination;

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

    #[Reactive]
    public string $selectedItemId = '';

    #[Reactive]
    public bool $isReturning = false;

    /** A new filter starts on the first page again; selecting an item keeps the page. */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, InboxItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $query = InboxItem::query()->where('user_id', auth()->id());

        if ($this->filterArchive) {
            in_array($this->filterStatus, ['approved', 'rejected'], true)
                ? $query->where('status', $this->filterStatus)
                : $query->archived();
            $query->orderByRaw('COALESCE(approved_at, rejected_at, created_at) DESC');
        } else {
            $query->whereIn('status', ['pending', 'executing'])->byUrgency();
        }

        if ($this->filterType !== '') {
            $query->ofType($this->filterType);
        }

        if ($this->filterRiskLevel !== '') {
            $query->withRiskLevel($this->filterRiskLevel);
        }

        if ($this->filterSource !== '') {
            $query->fromSource($this->filterSource);
        }

        return $query->paginate(25);
    }
}; ?>

<div class="mt-3">
    @php $filtered = $filterType !== '' || $filterRiskLevel !== '' || $filterSource !== '' || $filterStatus !== ''; @endphp

    @if ($this->items->isEmpty())
        @if ($filtered)
            <x-mortel::empty icon="funnel" heading="Geen items voor dit filter" description="Wis het filter om alles weer te zien.">
                <x-mortel::button size="sm" wire:click="$dispatch('set-filter', { filterStatus: '' })">Wis filter</x-mortel::button>
            </x-mortel::empty>
        @elseif ($filterArchive)
            <x-mortel::empty icon="archive-box" heading="Nog niets afgehandeld" description="Items die je goedkeurt of afwijst, komen hier te staan." />
        @else
            <x-mortel::empty icon="inbox" heading="Je inbox is leeg" description="Hier komen items te staan die je aandacht vragen." />
        @endif
    @else
        @if ($isReturning && ! $filterArchive)
            <x-mortel::text size="sm" class="mb-2 px-1">Welkom terug. Dit staat er voor je klaar.</x-mortel::text>
        @endif

        <ul class="space-y-1" aria-label="Inbox items">
            @foreach ($this->items as $item)
                <li wire:key="inbox-item-{{ $item->id }}">
                    <x-mortel::button
                        variant="ghost"
                        align="start"
                        class="h-auto! w-full py-2 text-start aria-[current=true]:bg-zinc-800/5 dark:aria-[current=true]:bg-white/15"
                        :loading="false"
                        aria-current="{{ $selectedItemId === $item->id ? 'true' : 'false' }}"
                        wire:click="$dispatch('item-selected', { itemId: '{{ $item->id }}' })"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $item->title }}</span>
                            <span class="block truncate text-xs font-normal text-zinc-600 dark:text-zinc-300">
                                {{ ucfirst(str_replace('_', ' ', $item->type)) }} · {{ ($filterArchive ? ($item->approved_at ?? $item->rejected_at ?? $item->created_at) : $item->created_at)?->diffForHumans() }}
                            </span>
                        </span>
                        @if ($filterArchive)
                            <x-mortel::badge size="sm" :color="$item->status === 'approved' ? 'green' : 'red'">{{ $item->status === 'approved' ? 'Goedgekeurd' : 'Afgewezen' }}</x-mortel::badge>
                        @elseif ($item->risk_level === 'high')
                            <x-mortel::badge size="sm" color="red">Hoog</x-mortel::badge>
                        @endif
                    </x-mortel::button>
                </li>
            @endforeach
        </ul>

        @if ($this->items->hasPages())
            <x-mortel::pagination class="mt-3" :paginator="$this->items" />
        @endif
    @endif
</div>
