<?php

use Livewire\Component;

new class extends Component {
    public string $userName = '';

    public string $userEmail = '';

    public string $position = 'bottom';

    public string $align = 'end';

    public bool $sidebar = false;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $this->userName = $user->name;
        $this->userEmail = $user->email;
    }
}; ?>

<x-mortel::dropdown :position="$position" :align="$align">
    @if ($sidebar)
        <x-mortel::sidebar.profile
            :name="$userName"
            avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0d9488&color=fff&size=32' }}"
        />
    @else
        <x-mortel::profile
            :name="$userName"
            avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0d9488&color=fff&size=32' }}"
        />
    @endif

    <x-mortel::menu>
        <x-mortel::menu.heading>
            <div class="font-medium">{{ $userName }}</div>
            <div class="text-xs text-zinc-500">{{ $userEmail }}</div>
        </x-mortel::menu.heading>

        <x-mortel::menu.separator />

        <x-mortel::menu.item icon="cog-6-tooth" href="{{ route('settings') }}" wire:navigate>
            Instellingen
        </x-mortel::menu.item>

        <x-mortel::menu.separator />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-mortel::menu.item icon="arrow-right-start-on-rectangle" type="submit">
                Uitloggen
            </x-mortel::menu.item>
        </form>
    </x-mortel::menu>
</x-mortel::dropdown>
