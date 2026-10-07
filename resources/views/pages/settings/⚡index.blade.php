<?php

declare(strict_types=1);

use App\Actions\Account\ChangePassword;
use App\Actions\Account\UpdateProfile;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Instellingen')]
class extends Component {
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $profileMessage = null;

    public ?string $passwordMessage = null;

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        app(UpdateProfile::class)->handle($user, $validated['name'], $validated['email'], $user);

        $this->profileMessage = 'Profiel bijgewerkt.';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        app(ChangePassword::class)->handle($user, $this->current_password, $this->password, $user);

        $this->current_password = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->passwordMessage = 'Wachtwoord gewijzigd.';
    }
}; ?>

<div class="p-6">
    <x-mortel::heading size="xl" level="1" class="mb-6">Instellingen</x-mortel::heading>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Each card is its own island (rules island-when, island-always). A save returns only that card's
             HTML; markup outside it is not re-rendered, so what a save writes ($name, $email, the messages,
             the cleared password fields) is shown only inside its own card (rule island-stale).
             Island actions run in their own queue, so both saves can run at once. For the stored data that
             is safe: each writes only its own columns (Eloquent updates dirty attributes) and neither reads
             a column the other writes; for component state the last response's snapshot wins. Two islands that read-modify-write the same row need a transaction with
             lockForUpdate() instead. --}}
        @island(name: 'profile', always: true)
            <x-mortel::card>
                <x-mortel::heading size="lg" level="2" class="mb-4">Profiel</x-mortel::heading>

                @if ($profileMessage)
                    <x-mortel::callout variant="success" icon="check-circle" :heading="$profileMessage" class="mb-4" />
                @endif

                <form wire:submit="updateProfile" class="space-y-4">
                    <x-mortel::input label="Naam" wire:model="name" required />
                    <x-mortel::input label="E-mail" type="email" wire:model="email" required />

                    <x-mortel::button type="submit" variant="primary">Opslaan</x-mortel::button>
                </form>
            </x-mortel::card>
        @endisland

        @island(name: 'password', always: true)
            <x-mortel::card>
                <x-mortel::heading size="lg" level="2" class="mb-4">Wachtwoord wijzigen</x-mortel::heading>

                @if ($passwordMessage)
                    <x-mortel::callout variant="success" icon="check-circle" :heading="$passwordMessage" class="mb-4" />
                @endif

                <form wire:submit="updatePassword" class="space-y-4">
                    <x-mortel::input label="Huidige wachtwoord" type="password" wire:model="current_password" required />
                    <x-mortel::input label="Nieuw wachtwoord" type="password" wire:model="password" required />
                    <x-mortel::input label="Bevestig nieuw wachtwoord" type="password" wire:model="password_confirmation" required />

                    <x-mortel::button type="submit" variant="primary">Wachtwoord wijzigen</x-mortel::button>
                </form>
            </x-mortel::card>
        @endisland
    </div>
</div>
