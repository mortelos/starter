<?php

use Livewire\Component;

new class extends Component {
    public string $userName = '';

    public string $userEmail = '';

    public string $position = 'bottom';

    public string $align = 'end';

    public bool $sidebar = false;

    /** @var list<array{id: string, name: string, is_current: bool}> */
    public array $tenants = [];

    public string $currentTenantId = '';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->currentTenantId = (string) session('tenant_id', '');

        if (method_exists($user, 'tenants')) {
            $this->tenants = $user->tenants()->get()->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name ?? $tenant->id,
                'is_current' => $tenant->id === $this->currentTenantId,
            ])->all();
        }
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

        @if (count($tenants) > 1)
            <flux:menu.heading>Organisaties</flux:menu.heading>

            @foreach ($tenants as $tenant)
                <form method="POST" action="{{ route('auth.tenant-store') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant['id'] }}">
                    <flux:menu.item type="submit">
                        <div class="flex items-center gap-2">
                            @if ($tenant['is_current'])
                                <flux:icon.check variant="mini" class="size-4 text-accent" />
                            @else
                                <span class="size-4"></span>
                            @endif
                            {{ $tenant['name'] }}
                        </div>
                    </flux:menu.item>
                </form>
            @endforeach

            <flux:menu.separator />
        @endif

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
