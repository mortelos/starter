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

<flux:dropdown :position="$position" :align="$align">
    @if ($sidebar)
        <flux:sidebar.profile
            :name="$userName"
            avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0d9488&color=fff&size=32' }}"
        />
    @else
        <flux:profile
            :name="$userName"
            avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0d9488&color=fff&size=32' }}"
        />
    @endif

    <flux:menu>
        <flux:menu.heading>
            <div class="font-medium">{{ $userName }}</div>
            <div class="text-xs text-zinc-500">{{ $userEmail }}</div>
        </flux:menu.heading>

        <flux:menu.separator />

        <flux:menu.item icon="cog-6-tooth" href="{{ route('settings') }}" wire:navigate>
            Instellingen
        </flux:menu.item>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:menu.item icon="arrow-right-start-on-rectangle" type="submit">
                Uitloggen
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
