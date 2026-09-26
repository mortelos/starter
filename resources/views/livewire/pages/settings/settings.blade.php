<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
        $user->update($validated);

        $this->profileMessage = 'Profiel bijgewerkt.';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Huidige wachtwoord klopt niet.',
            ]);
        }

        $user->update(['password' => Hash::make($this->password)]);

        $this->current_password = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->passwordMessage = 'Wachtwoord gewijzigd.';
    }
}; ?>

<div class="p-6">
    <x-mortel::heading size="xl" level="1" class="mb-6">Instellingen</x-mortel::heading>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Profiel --}}
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

        {{-- Wachtwoord --}}
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
    </div>
</div>
