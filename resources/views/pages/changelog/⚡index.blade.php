<?php

use App\Actions\ChangeLog\UndoChange;
use App\Enums\ChangeLogUndoState;
use App\Support\ChangeLog\ChangeLogAccessCheck;
use App\Support\ChangeLog\ChangeLogDescriptions;
use App\Support\ChangeLog\ChangeLogFilter;
use App\Support\ChangeLog\ChangeLogPage;
use App\Support\ChangeLog\ChangeLogReader;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mortel\Support\Actor;

new
#[Layout('layouts::app')]
#[Title('Wijzigingslog')]
class extends Component {
    #[Url]
    public string $who = '';

    #[Url]
    public string $part = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $cursor = '';

    /** An event id from a link ("Bekijk"): the page starts at that event and marks it. */
    #[Url]
    public string $focus = '';

    #[Url]
    public string $archive = '';

    /**
     * [created_at, id] of the newest event when the list was loaded. The list stays at that point while you work, so
     * an undo does not push the rows down; new rows show after a filter change or "Naar de nieuwste".
     *
     * @var array{0: string, 1: string}|null
     */
    #[Locked]
    public ?array $anchor = null;

    #[Locked]
    public ?string $message = null;

    #[Locked]
    public bool $failed = false;

    /** Counts the messages, so a repeated message shows again after it was closed in the browser. */
    #[Locked]
    public int $messageCount = 0;

    #[Locked]
    public ?string $messageLink = null;

    /** On every request, not only the first: a right taken away also closes an open page. */
    public function boot(): void
    {
        abort_unless(ChangeLogAccessCheck::allows(auth()->user()), 403);
    }

    public function mount(): void
    {
        $this->anchor = $this->focus === '' ? $this->page->newest : null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['who', 'part', 'from', 'to'], true)) {
            $this->newest();
        }
    }

    public function newest(): void
    {
        $this->reset('cursor', 'focus', 'archive', 'anchor');
        unset($this->page);
        $this->anchor = $this->page->newest;
    }

    public function resetFilters(): void
    {
        $this->reset('who', 'part', 'from', 'to');
        $this->newest();
    }

    public function show(string $cursor): void
    {
        $this->cursor = $cursor;
        $this->archive = '';
    }

    public function showArchive(string $cursor): void
    {
        $this->archive = $cursor;
    }

    public function undo(string $eventId): void
    {
        try {
            $result = app(UndoChange::class)->handle($eventId, Actor::current());
        } catch (AuthorizationException $denied) {
            $this->showMessage($denied->getMessage(), true, null);

            return;
        }

        $link = $result->data['undo_event_id'] ?? null;
        $this->showMessage($result->message, $result->isFailed(), is_string($link) && $link !== '' ? $link : null);
    }

    #[Computed]
    public function filter(): ChangeLogFilter
    {
        return ChangeLogFilter::fromInput($this->who, $this->part, $this->from, $this->to);
    }

    #[Computed]
    public function levels(): array
    {
        return app(ChangeLogReader::class)->levels(auth()->user());
    }

    #[Computed]
    public function page(): ChangeLogPage
    {
        $reader = app(ChangeLogReader::class);
        $bound = $this->focus !== '' ? $reader->boundOf($this->focus) : $this->anchor;

        return $reader->page(Actor::current(), $this->levels, $this->filter, $this->cursor ?: null, $bound, $this->archive ?: null);
    }

    /** @return array<string, string> part key => label, the parts this user may see */
    #[Computed]
    public function partOptions(): array
    {
        return array_intersect_key(app(ChangeLogDescriptions::class)->parts(), $this->levels);
    }

    /** @return array<string, string> value => label of the "Wie" filter */
    #[Computed]
    public function whoOptions(): array
    {
        $reader = app(ChangeLogReader::class);
        $options = ['' => 'Iedereen', 'people' => 'Alle personen'];

        foreach ($reader->people() as $id => $name) {
            $options['user:'.$id] = $name;
        }

        $options['agents'] = 'Alle agents';

        foreach ($reader->agents() as $agent) {
            $options['agent:'.$agent->key] = $agent->name;
        }

        return $options + ['system' => 'Systeem'];
    }

    private function showMessage(string $message, bool $failed, ?string $link): void
    {
        $this->message = $message;
        $this->failed = $failed;
        $this->messageCount++;
        $this->messageLink = $link;
    }
}; ?>

<div class="space-y-6 p-4 sm:p-6">
    <x-mortel::page-header heading="Wijzigingslog" subheading="Wie wat wanneer veranderde. Draai een wijziging terug waar dat kan.">
        <x-slot:actions>
            <x-mortel::button icon="arrow-down-tray" href="{{ route('changelog.export', $this->filter->query()) }}" data-changelog-export>CSV exporteren</x-mortel::button>
        </x-slot:actions>
    </x-mortel::page-header>

    <x-mortel::filters aria-label="Wijzigingslog filteren">
        <x-mortel::select wire:model.live="who" label="Wie" placeholder="Iedereen" searchable class="w-full sm:w-56">
            @foreach ($this->whoOptions as $value => $label)
                <x-mortel::select.option value="{{ $value }}" wire:key="who-{{ $value }}">{{ $label }}</x-mortel::select.option>
            @endforeach
        </x-mortel::select>
        <x-mortel::select wire:model.live="part" label="Onderdeel" class="w-full sm:w-48">
            <x-mortel::select.option value="">Alle onderdelen</x-mortel::select.option>
            @foreach ($this->partOptions as $key => $label)
                <x-mortel::select.option value="{{ $key }}" wire:key="part-{{ $key }}">{{ $label }}</x-mortel::select.option>
            @endforeach
        </x-mortel::select>
        <x-mortel::input type="date" wire:model.live="from" label="Van" class="w-full sm:w-40" />
        <x-mortel::input type="date" wire:model.live="to" label="Tot en met" class="w-full sm:w-40" />
        {{-- Always rendered, hidden while there is nothing to clear: appearing would push the list down. --}}
        <x-slot:reset>
            <x-mortel::button variant="ghost" size="sm" wire:click="resetFilters" :class="$this->filter->isEmpty() ? 'invisible' : ''" :aria-hidden="$this->filter->isEmpty() ? 'true' : 'false'" :tabindex="$this->filter->isEmpty() ? '-1' : '0'">Filters wissen</x-mortel::button>
        </x-slot:reset>
    </x-mortel::filters>

    @if ($cursor !== '' || $focus !== '')
        <x-mortel::button size="sm" variant="ghost" icon="arrow-up" wire:click="newest">Naar de nieuwste</x-mortel::button>
    @endif

    @if ($this->page->isEmpty())
        <x-mortel::card>
            @if ($this->filter->isEmpty())
                <x-mortel::empty icon="clock" heading="Nog geen wijzigingen" description="Elke wijziging in de app komt hier te staan, met wie hem deed." />
            @else
                <x-mortel::empty icon="magnifying-glass" heading="Geen wijzigingen gevonden" description="Pas het filter aan.">
                    <x-mortel::button wire:click="resetFilters">Filters wissen</x-mortel::button>
                </x-mortel::empty>
            @endif
        </x-mortel::card>
    @endif

    @foreach ([['rows' => $this->page->rows, 'archived' => false], ['rows' => $this->page->archive, 'archived' => true]] as $block)
        @if ($block['archived'] && $block['rows'] !== [])
            <x-mortel::heading level="2" size="lg" class="pt-2" data-changelog-archive>Vóór de overstap</x-mortel::heading>
        @endif

        @foreach (ChangeLogReader::byDay($block['rows']) as $day => $rows)
            <x-mortel::section :heading="$day" :count="count($rows)" level="3" wire:key="day-{{ $block['archived'] ? 'archive' : 'live' }}-{{ $rows[0]->id }}">
                <x-mortel::card padding="none">
                    {{-- On a phone a row's action wraps under its text (kit hooks data-mortel-list-*): there is no room beside it. --}}
                    <x-mortel::list aria-label="Wijzigingen van {{ $day }}" class="max-sm:[&_[data-mortel-list-row]]:flex-wrap max-sm:[&_[data-mortel-list-trailing]]:basis-full max-sm:[&_[data-mortel-list-trailing]]:pl-11">
                        @foreach ($rows as $row)
                            {{-- Compact on purpose: 50 rows per page, every byte of indentation counts toward the 150 KB budget. --}}
                            <x-mortel::list.item wire:key="change-{{ $row->id }}" id="change-{{ $row->id }}" :class="$row->id === $focus ? 'bg-muted' : ''" data-changelog-row="{{ $row->id }}">
                                <x-slot:leading><x-mortel::avatar :name="$row->actor->name" size="sm" /></x-slot:leading>
                                {{ $row->sentence }}
                                <x-slot:description>{{ $row->actor->full() }} · {{ $row->part->label }}{{ $row->detail === null ? '' : ' · '.$row->detail }}</x-slot:description>
                                <x-slot:meta>{{ $row->at->setTimezone(config('app.timezone'))->format('H:i') }}</x-slot:meta>
                                @if ($row->undo === ChangeLogUndoState::Available)
                                <x-slot:trailing><x-mortel::button size="sm" wire:click="undo('{{ $row->id }}')">Ongedaan maken</x-mortel::button></x-slot:trailing>
                                @elseif ($row->undo === ChangeLogUndoState::Undone)
                                <x-slot:trailing><span class="flex items-center gap-2"><x-mortel::badge size="sm">Ongedaan gemaakt</x-mortel::badge><x-mortel::link href="{{ route('changelog', ['focus' => $row->undoneBy]) }}" wire:navigate class="text-sm">Bekijk</x-mortel::link></span></x-slot:trailing>
                                @elseif ($row->note !== null)
                                <x-slot:trailing><x-mortel::text class="text-xs sm:max-w-56">{{ $row->note }}</x-mortel::text></x-slot:trailing>
                                @endif
                            </x-mortel::list.item>
                        @endforeach
                    </x-mortel::list>
                </x-mortel::card>
            </x-mortel::section>
        @endforeach
    @endforeach

    <div class="flex flex-wrap gap-2">
        @if ($this->page->newer !== null)
            <x-mortel::button size="sm" icon="chevron-left" wire:click="show('{{ $this->page->newer }}')">Nieuwere wijzigingen</x-mortel::button>
        @endif
        @if ($this->page->older !== null)
            <x-mortel::button size="sm" icon:trailing="chevron-right" wire:click="show('{{ $this->page->older }}')">Oudere wijzigingen</x-mortel::button>
        @elseif ($this->page->archiveOlder !== null)
            <x-mortel::button size="sm" icon:trailing="chevron-right" wire:click="showArchive('{{ $this->page->archiveOlder }}')">Meer van vóór de overstap</x-mortel::button>
        @endif
    </div>

    {{-- The result of an undo floats at the bottom, so it does not push the list down (layout shift). --}}
    @if ($message !== null)
        <div class="fixed inset-x-4 bottom-4 z-40 sm:left-auto sm:w-[28rem]" role="status" aria-live="polite" data-changelog-message wire:key="message-{{ $messageCount }}" x-data="{ shown: true }" x-show="shown">
            <x-mortel::callout :variant="$failed ? 'danger' : 'success'" :icon="$failed ? 'exclamation-triangle' : 'check-circle'" :heading="$message">
                <x-slot name="actions">
                    @if ($messageLink !== null)
                        <x-mortel::button size="sm" href="{{ route('changelog', ['focus' => $messageLink]) }}" wire:navigate>Bekijk</x-mortel::button>
                    @endif
                    <x-mortel::button size="sm" variant="ghost" x-on:click="shown = false">Sluiten</x-mortel::button>
                </x-slot>
            </x-mortel::callout>
        </div>
    @endif
</div>
