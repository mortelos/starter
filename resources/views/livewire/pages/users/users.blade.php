<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Gebruikersbeheer')]
class extends Component {
    public array $members = [];

    public array $pendingInvites = [];

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public ?string $errorMessage = null;

    public bool $showUserAccessSlide = false;

    public string $selectedUserAccessId = '';

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (! $this->usersResolver()->canManage()) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->loadData();
    }

    public function loadData(): void
    {
        $resolver = $this->usersResolver();

        $this->members = $resolver->members();
        $this->pendingInvites = $resolver->pendingInvites();
    }


    private function usersResolver(): object
    {
        $resolver = config('starter.users.resolver');

        if (! is_string($resolver) || $resolver === '') {
            throw new LogicException('Missing starter users resolver config [starter.users.resolver].');
        }

        return app($resolver);
    }

    private function accessResolver(): object
    {
        $resolver = config('starter.users.access_resolver');

        if (! is_string($resolver) || $resolver === '') {
            throw new LogicException('Missing starter users access resolver config [starter.users.access_resolver].');
        }

        return app($resolver);
    }

    public function openUserAccessSlide(string $userId): void
    {
        $userId = trim($userId);

        if ($userId === '' || ! $this->accessResolver()->canInspect($userId)) {
            return;
        }

        $this->selectedUserAccessId = $userId;
        $this->showUserAccessSlide = true;
    }

    public function closeUserAccessSlide(): void
    {
        $this->showUserAccessSlide = false;
        $this->selectedUserAccessId = '';
    }

    public function invite(): void
    {
        $this->validate([
            'inviteEmail' => 'required|email',
            'inviteRole' => 'required|in:owner,member,observer',
        ]);

        $this->errorMessage = null;

        try {
            $this->usersResolver()->invite($this->inviteEmail, $this->inviteRole);

            $this->inviteEmail = '';
            $this->inviteRole = 'member';
            $this->loadData();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = $e->errors()['email'][0] ?? 'Er is een fout opgetreden.';
        }
    }

    public function revokeInvite(string $inviteId): void
    {
        $this->usersResolver()->revokeInvite($inviteId);

        $this->loadData();
    }
}; ?>

<div class="p-6">
    <h1 class="mb-6 text-2xl font-semibold text-gray-900">Gebruikersbeheer</h1>

    {{-- Uitnodigingsformulier --}}
    <div class="mb-8 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Medewerker uitnodigen</h2>

        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errorMessage }}</div>
        @endif

        <form wire:submit="invite" class="flex items-end gap-4">
            <x-mortel::input type="email" wire:model="inviteEmail" label="E-mailadres" placeholder="naam@voorbeeld.nl" required class="flex-1" />
            <x-mortel::select wire:model="inviteRole" label="Rol">
                <x-mortel::select.option value="member">Member</x-mortel::select.option>
                <x-mortel::select.option value="observer">Observer</x-mortel::select.option>
                <x-mortel::select.option value="owner">Owner</x-mortel::select.option>
            </x-mortel::select>
            <x-mortel::button type="submit" variant="primary">Uitnodiging versturen</x-mortel::button>
        </form>
    </div>

    {{-- Teamleden --}}
    <div class="mb-8 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Teamleden</h2>
        </div>
        <x-mortel::table class="px-6">
            <x-mortel::table.columns>
                <x-mortel::table.column>Naam</x-mortel::table.column>
                <x-mortel::table.column>E-mail</x-mortel::table.column>
                <x-mortel::table.column>Rol</x-mortel::table.column>
                <x-mortel::table.column>Lid sinds</x-mortel::table.column>
                <x-mortel::table.column align="end"></x-mortel::table.column>
            </x-mortel::table.columns>
            <x-mortel::table.rows>
                @forelse($members as $member)
                    <x-mortel::table.row>
                        <x-mortel::table.cell>{{ $member['name'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell>{{ $member['email'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell><x-mortel::badge size="sm">{{ $member['role'] }}</x-mortel::badge></x-mortel::table.cell>
                        <x-mortel::table.cell>{{ $member['joined_at'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell align="end">
                            <x-mortel::button size="xs" icon:trailing="arrow-up-right" wire:click="openUserAccessSlide('{{ $member['id'] }}')">Bekijk toegang</x-mortel::button>
                        </x-mortel::table.cell>
                    </x-mortel::table.row>
                @empty
                    <x-mortel::table.row>
                        <x-mortel::table.cell colspan="5" class="text-center text-zinc-500">Nog geen teamleden.</x-mortel::table.cell>
                    </x-mortel::table.row>
                @endforelse
            </x-mortel::table.rows>
        </x-mortel::table>
    </div>

    {{-- Openstaande uitnodigingen --}}
    @if($pendingInvites !== [])
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Openstaande uitnodigingen</h2>
            </div>
            <x-mortel::table class="px-6">
                <x-mortel::table.columns>
                    <x-mortel::table.column>E-mail</x-mortel::table.column>
                    <x-mortel::table.column>Rol</x-mortel::table.column>
                    <x-mortel::table.column>Verloopt</x-mortel::table.column>
                    <x-mortel::table.column align="end"></x-mortel::table.column>
                </x-mortel::table.columns>
                <x-mortel::table.rows>
                    @foreach($pendingInvites as $invite)
                        <x-mortel::table.row>
                            <x-mortel::table.cell>{{ $invite['email'] }}</x-mortel::table.cell>
                            <x-mortel::table.cell><x-mortel::badge color="amber" size="sm">{{ $invite['role'] }}</x-mortel::badge></x-mortel::table.cell>
                            <x-mortel::table.cell>{{ $invite['expires_at'] }}</x-mortel::table.cell>
                            <x-mortel::table.cell align="end">
                                <x-mortel::button variant="ghost" size="xs" class="text-red-600" wire:click="revokeInvite('{{ $invite['id'] }}')">Intrekken</x-mortel::button>
                            </x-mortel::table.cell>
                        </x-mortel::table.row>
                    @endforeach
                </x-mortel::table.rows>
            </x-mortel::table>
        </div>
    @endif

    @if($showUserAccessSlide)
        <div
            class="fixed inset-0 z-50 flex justify-end bg-gray-950/20"
            x-data
            x-on:keydown.escape.window="$wire.closeUserAccessSlide()"
        >
            <div class="absolute inset-0" wire:click="closeUserAccessSlide" aria-hidden="true"></div>
            <div class="relative h-full w-full max-w-3xl bg-white shadow-2xl">
                <livewire:users.user-access-slide-over
                    :user-id="$selectedUserAccessId"
                    wire:key="users-access-slide-{{ $selectedUserAccessId }}"
                />
            </div>
        </div>
    @endif
</div>
