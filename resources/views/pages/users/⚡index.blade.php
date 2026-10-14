<?php

use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Undo;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantResolver;
use Mortel\Models\Role;
use Mortel\Support\Actor;

new
#[Layout('layouts::app')]
#[Title('Gebruikersbeheer')]
class extends Component {
    public array $members = [];

    public array $pendingInvites = [];

    /** @var array<string, string> role id => name, the roles of this tenant */
    public array $roles = [];

    public string $inviteEmail = '';

    public string $inviteRole = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    /** The accept link holds the plain token, which exists only in the ActionResult of InviteUser. */
    #[Locked]
    public ?string $acceptUrl = null;

    /** @var array{action: string, params: array<string, mixed>, valid_until: string|null}|null */
    #[Locked]
    public ?array $pendingUndo = null;

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

        $this->roles = Role::query()
            ->where('org_id', app(TenantResolver::class)->id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $this->inviteRole = $this->defaultRole();

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

    private function defaultRole(): string
    {
        return (string) (array_search('member', $this->roles, true) ?: array_key_first($this->roles) ?? '');
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
            'inviteEmail' => ['required', 'email'],
            'inviteRole' => ['required', Rule::in(array_keys($this->roles))],
        ]);

        $result = app(InviteUser::class)->handle($this->inviteEmail, $this->inviteRole, Actor::current());

        $this->showResult($result);

        if ($result->isDone()) {
            $this->acceptUrl = $result->data['accept_url'];
            $this->pendingUndo = $result->undo?->toArray();
            $this->inviteEmail = '';
            $this->inviteRole = $this->defaultRole();
            $this->loadData();
        }
    }

    public function undoInvite(): void
    {
        if ($this->pendingUndo === null) {
            return;
        }

        $this->showResult(app(UndoRegistry::class)->undo(Undo::fromArray($this->pendingUndo), Actor::current()));
        $this->pendingUndo = null;
        $this->acceptUrl = null;
        $this->loadData();
    }

    public function canUndo(): bool
    {
        return $this->pendingUndo !== null && ! Undo::fromArray($this->pendingUndo)->isExpired();
    }

    public function revokeInvite(string $invitationId): void
    {
        $result = app(RevokeInvitation::class)->handle($invitationId, Actor::current());

        $this->showResult($result);

        // The link and the undo of a just-revoked invitation are dead; stop showing them.
        if ($result->isDone() && ($this->pendingUndo['params']['invitation_id'] ?? null) === $invitationId) {
            $this->pendingUndo = null;
            $this->acceptUrl = null;
        }

        $this->loadData();
    }

    private function showResult(ActionResult $result): void
    {
        $this->resultMessage = $result->isFailed() ? null : $result->message;
        $this->errorMessage = $result->isFailed() ? $result->message : null;
    }
}; ?>

<div class="p-6">
    <x-mortel::heading size="xl" level="1" class="mb-6">Gebruikersbeheer</x-mortel::heading>

    {{-- Teamleden --}}
    <x-mortel::card class="mb-8 p-0">
        <div class="border-b border-zinc-100 px-6 py-4">
            <x-mortel::heading size="lg" level="2">Teamleden</x-mortel::heading>
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
                    <x-mortel::table.row wire:key="member-{{ $member['id'] }}">
                        <x-mortel::table.cell>{{ $member['name'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell>{{ $member['email'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell><x-mortel::badge size="sm">{{ $member['role'] }}</x-mortel::badge></x-mortel::table.cell>
                        <x-mortel::table.cell>{{ $member['joined_at'] }}</x-mortel::table.cell>
                        <x-mortel::table.cell align="end">
                            <x-mortel::button size="xs" icon:trailing="arrow-up-right" wire:click="openUserAccessSlide('{{ $member['id'] }}')" wire:island="access">Bekijk toegang</x-mortel::button>
                        </x-mortel::table.cell>
                    </x-mortel::table.row>
                @empty
                    <x-mortel::table.row>
                        <x-mortel::table.cell colspan="5">
                            <x-mortel::empty icon="users" heading="Nog geen teamleden" description="Nodig hieronder een medewerker uit." />
                        </x-mortel::table.cell>
                    </x-mortel::table.row>
                @endforelse
            </x-mortel::table.rows>
        </x-mortel::table>
    </x-mortel::card>

    {{-- Uitnodigingsformulier. It sits below the member list and its messages below the form, so a
         message that appears after an action does not push existing markup down (layout shift). --}}
    <x-mortel::card class="mb-8">
        <x-mortel::heading size="lg" level="2" class="mb-4">Medewerker uitnodigen</x-mortel::heading>

        <form wire:submit="invite" class="flex items-end gap-4">
            <x-mortel::input type="email" wire:model="inviteEmail" label="E-mailadres" placeholder="naam@voorbeeld.nl" required class="flex-1" />
            <x-mortel::select wire:model="inviteRole" label="Rol">
                @foreach($roles as $roleId => $roleName)
                    <x-mortel::select.option value="{{ $roleId }}" wire:key="role-{{ $roleId }}">{{ $roleName }}</x-mortel::select.option>
                @endforeach
            </x-mortel::select>
            <x-mortel::button type="submit" variant="primary">Uitnodigen</x-mortel::button>
        </form>

        @if($errorMessage)
            <x-mortel::callout variant="danger" icon="exclamation-triangle" :heading="$errorMessage" class="mt-4" />
        @endif

        @if($resultMessage)
            <x-mortel::callout variant="success" icon="check-circle" :heading="$resultMessage" class="mt-4">
                @if($this->canUndo())
                    <x-slot name="actions">
                        <x-mortel::button size="sm" wire:click="undoInvite">Ongedaan maken</x-mortel::button>
                    </x-slot>
                @endif
            </x-mortel::callout>
        @endif

        @if($acceptUrl)
            <x-mortel::callout icon="link" heading="Uitnodigingslink" class="mt-4">
                <x-mortel::callout.text>
                    De starter stuurt geen mail. Kopieer de link en stuur hem zelf naar je nieuwe collega.
                    Je ziet de link maar één keer. Wil je dat de app zelf mailt? Voeg dat dan toe in InviteUser.
                </x-mortel::callout.text>
                <x-mortel::input :value="$acceptUrl" readonly copyable aria-label="Uitnodigingslink" class="mt-3" />
            </x-mortel::callout>
        @endif
    </x-mortel::card>

    {{-- Openstaande uitnodigingen --}}
    @if($pendingInvites !== [])
        <x-mortel::card class="p-0">
            <div class="border-b border-zinc-100 px-6 py-4">
                <x-mortel::heading size="lg" level="2">Openstaande uitnodigingen</x-mortel::heading>
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
                        <x-mortel::table.row wire:key="invite-{{ $invite['id'] }}">
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
        </x-mortel::card>
    @endif

    {{-- Opened from a table row with wire:island (rule island-trigger). An island action returns only
         this island's HTML; markup outside it is not re-rendered and would go stale if it showed what
         these actions write (rule island-stale). They only write $showUserAccessSlide and
         $selectedUserAccessId, which only this island shows. Island actions run in their own queue,
         next to invite and revokeInvite; for the stored data that is safe, because the island actions
         write none (the last response's snapshot wins for component state). The overlay keeps 3rem
         next to the panel on narrow screens, so a tap can always close it. --}}
    @island(name: 'access')
        @if($showUserAccessSlide)
            <div
                class="fixed inset-0 z-50 flex bg-gray-950/20"
                data-access-panel
                x-data
                x-on:keydown.escape.window="$wire.$island('access').closeUserAccessSlide()"
            >
                <x-mortel::button variant="ghost" class="h-full! min-w-12 flex-1 rounded-none!" wire:click="closeUserAccessSlide" aria-label="Sluiten" data-access-close />
                <div class="h-full w-full max-w-3xl bg-white shadow-2xl">
                    <livewire:users.user-access-slide-over
                        :user-id="$selectedUserAccessId"
                        wire:key="users-access-slide-{{ $selectedUserAccessId }}"
                    />
                </div>
            </div>
        @endif
    @endisland
</div>
