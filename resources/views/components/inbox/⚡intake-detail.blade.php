<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Mortel\Actions\Inbox\RespondToInboxItemMessage;
use Mortel\Models\AiConversationMessage;
use Mortel\Models\InboxItem;
use Mortel\Support\AiConversationRepository;

new class extends Component
{
    #[Reactive]
    public string $itemId = '';

    public string $newMessage = '';

    #[Computed]
    public function item(): ?InboxItem
    {
        if ($this->itemId === '') {
            return null;
        }

        return InboxItem::query()->where('user_id', auth()->id())->find($this->itemId);
    }

    /**
     * @return Collection<int, AiConversationMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        $item = $this->item;
        $userId = auth()->id();
        $conversation = $item === null || $userId === null
            ? null
            : app(AiConversationRepository::class)->findExistingFor('inbox_item', $item->id, $userId);

        if ($conversation === null) {
            return new Collection;
        }

        return AiConversationMessage::query()->where('conversation_id', $conversation->id)->oldest()->oldest('id')->get();
    }

    /** Asks the assistant about this item; the answer comes back in the same request. */
    public function sendMessage(): void
    {
        $this->validate(['newMessage' => ['required', 'string', 'max:2000']], [], ['newMessage' => 'bericht']);

        $item = $this->item;
        $userId = auth()->id();

        if ($item === null || $userId === null) {
            return;
        }

        $message = trim($this->newMessage);
        $repository = app(AiConversationRepository::class);
        $conversation = $repository->findOrStartFor('inbox_item', $item->id, $userId, 'Gesprek over '.$item->title);
        $repository->storePendingUserMessage((string) $conversation->id, $userId, $message);
        app(RespondToInboxItemMessage::class)->respond($item, (string) $conversation->id, $message);

        $this->reset('newMessage');
        unset($this->messages);
    }
}; ?>

<div class="flex h-[calc(100%-4.25rem)] min-h-0 flex-col lg:h-full">
    @if ($this->item === null)
        <div class="p-6">
            <x-mortel::empty icon="inbox" heading="Kies een item" description="Kies links een item om het hier te bekijken." />
        </div>
    @else
        @php $item = $this->item; @endphp

        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <x-mortel::badge size="sm">{{ ucfirst(str_replace('_', ' ', $item->type)) }}</x-mortel::badge>
            <x-mortel::heading size="lg" level="2" class="mt-2">{{ $item->title }}</x-mortel::heading>
            @if ($item->summary)
                <x-mortel::text class="mt-1">{{ $item->summary }}</x-mortel::text>
            @endif
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,1fr)_18rem]">
            <section class="flex min-h-0 flex-col" aria-label="Gesprek">
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-6">
                    @forelse ($this->messages as $message)
                        <div wire:key="intake-message-{{ $message->id }}" @class([
                            'max-w-[85%] rounded-lg px-3 py-2',
                            'ms-auto bg-zinc-100 dark:bg-zinc-800' => $message->role === 'user',
                            'border border-zinc-200 dark:border-zinc-800' => $message->role !== 'user',
                        ])>
                            <x-mortel::text variant="strong" class="whitespace-pre-line">{{ $message->content }}</x-mortel::text>
                        </div>
                    @empty
                        <x-mortel::text>Stel een vraag over dit item. Het antwoord verschijnt hier.</x-mortel::text>
                    @endforelse
                </div>

                <form wire:submit="sendMessage" class="flex items-end gap-2 border-t border-zinc-200 p-4 dark:border-zinc-800">
                    <x-mortel::textarea wire:model="newMessage" label="Je vraag" label:sr-only rows="2" class="flex-1" />
                    <x-mortel::button type="submit" icon="paper-airplane">Stuur</x-mortel::button>
                </form>
            </section>

            <aside class="min-h-0 space-y-4 overflow-y-auto border-t border-zinc-200 p-6 lg:border-s lg:border-t-0 dark:border-zinc-800" aria-label="Over dit item">
                <div>
                    <x-mortel::heading size="sm" level="3">Artefacten</x-mortel::heading>
                    @forelse ($item->metadata['artifacts'] ?? [] as $index => $artifact)
                        <div wire:key="intake-artifact-{{ $item->id }}-{{ $index }}" class="mt-2">
                            <x-mortel::text size="sm" class="font-medium">{{ $artifact['label'] ?? 'Artefact' }}</x-mortel::text>
                            @if (! empty($artifact['summary']))
                                <x-mortel::text size="sm">{{ $artifact['summary'] }}</x-mortel::text>
                            @endif
                        </div>
                    @empty
                        <x-mortel::text size="sm" class="mt-1">Nog geen artefacten. Voorstellen van de assistent komen hier te staan.</x-mortel::text>
                    @endforelse
                </div>

                <dl class="space-y-1">
                    <div class="flex justify-between gap-2">
                        <dt><x-mortel::text size="sm">Status</x-mortel::text></dt>
                        <dd><x-mortel::text size="sm">{{ $item->status }}</x-mortel::text></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt><x-mortel::text size="sm">Urgentie</x-mortel::text></dt>
                        <dd><x-mortel::text size="sm">{{ $item->risk_level }}</x-mortel::text></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt><x-mortel::text size="sm">Aangemaakt</x-mortel::text></dt>
                        <dd><x-mortel::text size="sm">{{ $item->created_at?->diffForHumans() }}</x-mortel::text></dd>
                    </div>
                </dl>
            </aside>
        </div>
    @endif
</div>
