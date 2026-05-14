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
    <h1 class="mb-6 text-2xl font-semibold text-zinc-900">Instellingen</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Profiel --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900">Profiel</h2>

            @if ($profileMessage)
                <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-800">
                    {{ $profileMessage }}
                </div>
            @endif

            <form wire:submit="updateProfile" class="space-y-4">
                <flux:input label="Naam" wire:model="name" required />
                <flux:input label="E-mail" type="email" wire:model="email" required />

                <flux:button type="submit" variant="primary">Opslaan</flux:button>
            </form>
        </div>

        {{-- Wachtwoord --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900">Wachtwoord wijzigen</h2>

            @if ($passwordMessage)
                <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-800">
                    {{ $passwordMessage }}
                </div>
            @endif

            <form wire:submit="updatePassword" class="space-y-4">
                <flux:input label="Huidige wachtwoord" type="password" wire:model="current_password" required />
                <flux:input label="Nieuw wachtwoord" type="password" wire:model="password" required />
                <flux:input label="Bevestig nieuw wachtwoord" type="password" wire:model="password_confirmation" required />

                <flux:button type="submit" variant="primary">Wachtwoord wijzigen</flux:button>
            </form>
        </div>
    </div>
</div>
