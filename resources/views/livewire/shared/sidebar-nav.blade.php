<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public array $sections = [];

    public int $inboxCount = 0;

    public array $overviews = [];

    public function mount(): void
    {
        $user = auth()->user();
        $resolver = $this->sidebarResolver();

        if ($user === null || $resolver === null) {
            return;
        }

        $this->sections = $resolver->sections($user);
        $this->inboxCount = (int) $resolver->inboxCount($user);
        $this->loadOverviews();
    }

    public function loadOverviews(): void
    {
        $user = auth()->user();
        $resolver = $this->sidebarResolver();

        if ($user === null || $resolver === null) {
            return;
        }

        $this->overviews = $resolver->overviews($user);
    }

    #[On('overzicht-saved')]
    public function onOverzichtSaved(): void
    {
        $this->loadOverviews();
    }

    public function dispatchAction(string $action): void
    {
        $this->js("window.dispatchEvent(new CustomEvent('{$action}'))");
    }

    private function sidebarResolver(): ?object
    {
        $resolver = config('starter.navigation.sidebar_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return null;
        }

        return app($resolver);
    }
}; ?>

<flux:sidebar.nav aria-label="Hoofdnavigatie">
    @foreach ($sections as $section)
        @if ($loop->index > 0)
            <flux:separator variant="subtle" />
        @endif

        <flux:sidebar.group heading="{{ $section['label'] }}">
            @foreach ($section['items'] as $item)
                @if (($item['type'] ?? 'link') === 'action')
                    <flux:sidebar.item
                        icon="{{ $item['icon'] }}"
                        wire:click="dispatchAction('{{ $item['action'] }}')"
                        :current="false"
                    >
                        {{ $item['label'] }}
                    </flux:sidebar.item>
                @else
                    <flux:sidebar.item
                        icon="{{ $item['icon'] }}"
                        href="{{ route($item['route']) }}"
                        wire:navigate
                        :current="request()->routeIs($item['route'])"
                        :badge="$item['permission'] === 'nav.sidebar.inbox' && $inboxCount > 0 ? $inboxCount : null"
                        badge:color="teal"
                    >
                        {{ $item['label'] }}
                    </flux:sidebar.item>
                @endif
            @endforeach
        </flux:sidebar.group>
    @endforeach

    @if (count($overviews) > 0)
        <flux:separator variant="subtle" />
        <flux:sidebar.group heading="Mijn overzichten">
            @foreach ($overviews as $overzicht)
                <flux:sidebar.item
                    icon="table-cells"
                    href="{{ route('overzichten.show', $overzicht['id']) }}"
                    wire:navigate
                    :current="request()->routeIs('overzichten.show')"
                >
                    {{ $overzicht['name'] }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.group>
    @else
        <flux:separator variant="subtle" />
        <flux:sidebar.group heading="Mijn overzichten">
            <p class="px-3 py-2 text-xs text-zinc-400">
                Je hebt nog geen overzichten.<br>Stel een vraag om te beginnen.
            </p>
        </flux:sidebar.group>
    @endif
</flux:sidebar.nav>
