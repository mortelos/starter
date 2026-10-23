<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Mortel\Actions\ApproveInboxItem;
use Mortel\Actions\RejectInboxItem;
use Mortel\Models\InboxItem;

new class extends Component
{
    #[Reactive]
    public string $itemId = '';

    public bool $showRejectForm = false;

    public string $rejectReason = '';

    public string $errorMessage = '';

    public function updatedItemId(): void
    {
        $this->reset('showRejectForm', 'rejectReason', 'errorMessage');
        $this->resetValidation();
    }

    #[Computed]
    public function item(): ?InboxItem
    {
        if ($this->itemId === '') {
            return null;
        }

        return InboxItem::query()->where('user_id', auth()->id())->find($this->itemId);
    }

    public function approve(): void
    {
        $item = $this->item;
        $user = auth()->user();

        if ($item === null || ! $user instanceof Model) {
            return;
        }

        try {
            app(ApproveInboxItem::class)->execute($item, $user);
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Goedkeuren is niet gelukt. Probeer het opnieuw.';

            return;
        }

        $this->dispatch('item-approved', id: $item->id);
    }

    public function reject(): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:1000']], [], ['rejectReason' => __('validation.attributes.rejectReason', locale: 'nl')]);

        $item = $this->item;
        $user = auth()->user();

        if ($item === null || ! $user instanceof Model) {
            return;
        }

        try {
            app(RejectInboxItem::class)->execute($item, $user, trim($this->rejectReason));
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Afwijzen is niet gelukt. Probeer het opnieuw.';

            return;
        }

        $this->reset('showRejectForm', 'rejectReason');
        $this->dispatch('item-rejected', id: $item->id);
    }
}; ?>

<div class="flex h-[calc(100%-4.25rem)] min-h-0 flex-col lg:h-full">
    @if ($this->item === null)
        <div class="p-6">
            <x-mortel::empty icon="inbox" heading="Kies een item" description="Kies links een item om het hier te bekijken." />
        </div>
    @else
        @php
            $item = $this->item;
            $riskLabels = ['low' => 'Laag', 'medium' => 'Gemiddeld', 'high' => 'Hoog'];
            $riskColors = ['low' => 'zinc', 'medium' => 'amber', 'high' => 'red'];
        @endphp

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
            <div class="flex flex-wrap items-center gap-2">
                <x-mortel::badge size="sm">{{ ucfirst(str_replace('_', ' ', $item->type)) }}</x-mortel::badge>
                <x-mortel::badge size="sm" :color="$riskColors[$item->risk_level] ?? 'zinc'">Urgentie: {{ $riskLabels[$item->risk_level] ?? $item->risk_level }}</x-mortel::badge>
                <x-mortel::text size="sm">{{ $item->created_at?->diffForHumans() }}</x-mortel::text>
            </div>

            <x-mortel::heading size="lg" level="2">{{ $item->title }}</x-mortel::heading>

            @if ($item->summary)
                <x-mortel::text class="whitespace-pre-line">{{ $item->summary }}</x-mortel::text>
            @endif

            @if ($errorMessage !== '')
                <x-mortel::callout variant="danger" icon="exclamation-triangle" :heading="$errorMessage" />
            @endif

            @if ($item->status === 'approved')
                <x-mortel::callout variant="success" icon="check-circle" heading="Goedgekeurd" :text="$item->approved_at?->diffForHumans()" />
            @elseif ($item->status === 'rejected')
                <x-mortel::callout variant="danger" icon="x-circle" heading="Afgewezen" :text="isset($item->metadata['rejection_reason']) ? 'Reden: '.$item->metadata['rejection_reason'] : null" />
            @elseif ($item->status === 'executing')
                <x-mortel::callout icon="arrow-path" heading="Wordt uitgevoerd" />
            @endif

            @if ($showRejectForm && $item->status === 'pending')
                <form wire:submit="reject" class="space-y-3">
                    <x-mortel::textarea wire:model="rejectReason" label="Waarom wijs je dit af?" rows="3" />
                    <div class="flex gap-2">
                        <x-mortel::button type="submit" variant="danger">Afwijzen</x-mortel::button>
                        <x-mortel::button variant="ghost" wire:click="$set('showRejectForm', false)">Annuleren</x-mortel::button>
                    </div>
                </form>
            @endif
        </div>

        @if ($item->status === 'pending' && ! $showRejectForm)
            <x-mortel::page-actions class="px-6 pb-4">
                <x-mortel::button wire:click="$set('showRejectForm', true)">Afwijzen</x-mortel::button>
                <x-mortel::button variant="primary" wire:click="approve">Goedkeuren</x-mortel::button>
            </x-mortel::page-actions>
        @endif
    @endif
</div>
