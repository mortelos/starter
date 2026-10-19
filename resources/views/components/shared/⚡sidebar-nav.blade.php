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

    #[On('overview-saved')]
    public function onOverviewSaved(): void
    {
        $this->loadOverviews();
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

<x-mortel::sidebar.nav aria-label="Hoofdnavigatie">
    @foreach ($sections as $section)
        @if ($loop->index > 0)
            <x-mortel::separator variant="subtle" wire:key="nav-separator-{{ $section['label'] }}" />
        @endif

        <div class="flex flex-col" data-sidebar-section wire:key="nav-section-{{ $section['label'] }}">
            <div class="px-3 py-2 in-[[data-mortel-sidebar][data-collapsed]]:hidden">
                <div class="text-sm text-zinc-500 dark:text-zinc-400 font-medium leading-none">{{ $section['label'] }}</div>
            </div>

            <div class="flex flex-col">
                @foreach ($section['items'] as $item)
                    @if (($item['type'] ?? 'link') === 'action')
                        {{-- Browser-only action: fire the window event without a server round trip (rule click-server). --}}
                        <x-mortel::sidebar.item
                            icon="{{ $item['icon'] }}"
                            x-on:click="$dispatch({{ \Illuminate\Support\Js::from($item['action']) }})"
                            :current="false"
                            wire:key="nav-action-{{ $item['action'] }}"
                        >
                            {{ $item['label'] }}
                        </x-mortel::sidebar.item>
                    @else
                        <x-mortel::sidebar.item
                            icon="{{ $item['icon'] }}"
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            :current="request()->routeIs($item['route'])"
                            :badge="$item['permission'] === 'nav.sidebar.inbox' && $inboxCount > 0 ? $inboxCount : null"
                            badge:color="teal"
                            wire:key="nav-link-{{ $item['route'] }}"
                        >
                            {{ $item['label'] }}
                        </x-mortel::sidebar.item>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    @if (count($overviews) > 0)
        <x-mortel::separator variant="subtle" />
        <div class="flex flex-col" data-sidebar-section>
            <div class="px-3 py-2 in-[[data-mortel-sidebar][data-collapsed]]:hidden">
                <div class="text-sm text-zinc-500 dark:text-zinc-400 font-medium leading-none">Mijn overzichten</div>
            </div>

            <div class="flex flex-col">
                @foreach ($overviews as $overview)
                    <x-mortel::sidebar.item
                        wire:key="nav-overview-{{ $overview['id'] }}"
                        icon="table-cells"
                        href="{{ route('overviews.show', $overview['id']) }}"
                        wire:navigate
                        :current="request()->routeIs('overviews.show')"
                    >
                        {{ $overview['name'] }}
                    </x-mortel::sidebar.item>
                @endforeach
            </div>
        </div>
    @else
        <x-mortel::separator variant="subtle" />
        <div class="flex flex-col" data-sidebar-section>
            <div class="px-3 py-2 in-[[data-mortel-sidebar][data-collapsed]]:hidden">
                <div class="text-sm text-zinc-500 dark:text-zinc-400 font-medium leading-none">Mijn overzichten</div>
            </div>

            <p class="px-3 py-2 text-xs text-zinc-500 dark:text-zinc-400 in-[[data-mortel-sidebar][data-collapsed]]:hidden">
                Je hebt nog geen overzichten.<br>Stel een vraag om te beginnen.
            </p>
        </div>
    @endif
</x-mortel::sidebar.nav>
