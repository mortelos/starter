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
            <div class="flex-1">
                <label for="inviteEmail" class="block text-sm font-medium text-gray-700">E-mailadres</label>
                <input type="email" wire:model="inviteEmail" id="inviteEmail" required
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                    placeholder="naam@voorbeeld.nl">
            </div>
            <div>
                <label for="inviteRole" class="block text-sm font-medium text-gray-700">Rol</label>
                <select wire:model="inviteRole" id="inviteRole"
                    class="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
                    <option value="member">Member</option>
                    <option value="observer">Observer</option>
                    <option value="owner">Owner</option>
                </select>
            </div>
            <button type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800">
                Uitnodiging versturen
            </button>
        </form>
    </div>

    {{-- Teamleden --}}
    <div class="mb-8 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Teamleden</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Naam</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">E-mail</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Rol</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Lid sinds</th>
                    <th class="px-6 py-3 text-right font-medium text-gray-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($members as $member)
                    <tr class="transition hover:bg-gray-50/70">
                        <td class="px-6 py-3 text-gray-900">{{ $member['name'] }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $member['email'] }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $member['role'] }}</span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ $member['joined_at'] }}</td>
                        <td class="px-6 py-3 text-right">
                            <button
                                type="button"
                                wire:click="openUserAccessSlide('{{ $member['id'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50 hover:text-gray-950"
                            >
                                Bekijk toegang
                                <flux:icon.arrow-up-right class="size-3.5 text-gray-400" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nog geen teamleden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Openstaande uitnodigingen --}}
    @if($pendingInvites !== [])
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Openstaande uitnodigingen</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">E-mail</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Rol</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Verloopt</th>
                        <th class="px-6 py-3 text-right font-medium text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($pendingInvites as $invite)
                        <tr>
                            <td class="px-6 py-3 text-gray-900">{{ $invite['email'] }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">{{ $invite['role'] }}</span>
                            </td>
                            <td class="px-6 py-3 text-gray-500">{{ $invite['expires_at'] }}</td>
                            <td class="px-6 py-3 text-right">
                                <button wire:click="revokeInvite('{{ $invite['id'] }}')"
                                    class="text-sm font-medium text-red-600 transition hover:text-red-800">
                                    Intrekken
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($showUserAccessSlide)
        <div
            class="fixed inset-0 z-50 flex justify-end bg-gray-950/20"
            x-data
            x-on:keydown.escape.window="$wire.closeUserAccessSlide()"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-default"
                wire:click="closeUserAccessSlide"
                aria-label="Sluiten"
            ></button>
            <div class="relative h-full w-full max-w-3xl bg-white shadow-2xl">
                <livewire:users.user-access-slide-over
                    :user-id="$selectedUserAccessId"
                    wire:key="users-access-slide-{{ $selectedUserAccessId }}"
                />
            </div>
        </div>
    @endif
</div>
