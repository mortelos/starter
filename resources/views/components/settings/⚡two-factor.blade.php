<?php

declare(strict_types=1);

use App\Actions\Account\ConfirmTwoFactor;
use App\Actions\Account\DisableTwoFactor;
use App\Actions\Account\EnableTwoFactor;
use App\Actions\Account\RegenerateRecoveryCodes;
use App\Models\User;
use App\Support\PasswordConfirmation;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $confirm_password = '';

    public string $code = '';

    public bool $showRecoveryCodes = false;

    public ?string $message = null;

    public function enable(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        app(EnableTwoFactor::class)->handle($user, $this->confirm_password, $user);

        $this->confirm_password = '';
        $this->message = null;
    }

    public function confirm(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        app(ConfirmTwoFactor::class)->handle($user, $this->code, $user);

        $this->code = '';
        $this->showRecoveryCodes = true;
        $this->message = 'Tweestaps staat aan. Bewaar je herstelcodes op een veilige plek.';
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        app(DisableTwoFactor::class)->handle($user, '', $user);

        $this->code = '';
        $this->message = null;
    }

    public function disable(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        app(DisableTwoFactor::class)->handle($user, $this->confirm_password, $user);

        $this->confirm_password = '';
        $this->showRecoveryCodes = false;
        $this->message = 'Tweestaps staat uit.';
    }

    public function regenerate(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        app(RegenerateRecoveryCodes::class)->handle($user, $this->confirm_password, $user);

        $this->confirm_password = '';
        $this->showRecoveryCodes = true;
        $this->message = 'Je hebt nieuwe herstelcodes. De oude werken niet meer.';
    }

    public function reveal(): void
    {
        $this->resetErrorBag();
        $user = $this->user();

        // Same proof as the other changes: the codes are as good as a code from the app.
        PasswordConfirmation::ensure($user, $this->confirm_password);

        $this->confirm_password = '';
        $this->showRecoveryCodes = true;
        $this->message = null;
    }

    #[Computed]
    public function enabled(): bool
    {
        return $this->user()->hasEnabledTwoFactorAuthentication();
    }

    #[Computed]
    public function settingUp(): bool
    {
        return ! $this->enabled && $this->user()->two_factor_secret !== null;
    }

    #[Computed]
    public function qrCode(): string
    {
        return $this->user()->twoFactorQrCodeSvg();
    }

    #[Computed]
    public function secretKey(): string
    {
        return Fortify::currentEncrypter()->decrypt((string) $this->user()->two_factor_secret);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function recoveryCodes(): array
    {
        return $this->enabled && $this->showRecoveryCodes ? array_values($this->user()->recoveryCodes()) : [];
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user->fresh() ?? $user;
    }
}; ?>

<x-mortel::card>
    <x-mortel::heading size="lg" level="2" class="mb-4">Tweestaps</x-mortel::heading>

    @if ($message)
        <x-mortel::callout variant="success" icon="check-circle" :heading="$message" class="mb-4" />
    @endif

    @if ($this->settingUp)
        <x-mortel::text class="mb-4">
            Scan de QR-code met een authenticator-app (zoals Google Authenticator, Microsoft Authenticator of 1Password).
            Kun je niet scannen? Voer dan deze sleutel in: <code data-test="two-factor-secret">{{ $this->secretKey }}</code>
        </x-mortel::text>

        <div class="mb-4 inline-block rounded-lg bg-white p-2" data-test="two-factor-qr">{!! $this->qrCode !!}</div>

        <form wire:submit="confirm" class="space-y-4">
            <x-mortel::input label="Code uit de app" wire:model="code" inputmode="numeric" autocomplete="one-time-code" required />

            <div class="flex gap-2">
                <x-mortel::button type="submit" variant="primary">Bevestigen</x-mortel::button>
                <x-mortel::button type="button" wire:click="cancel">Annuleren</x-mortel::button>
            </div>
        </form>
    @elseif ($this->enabled)
        <x-mortel::text class="mb-4">
            Tweestaps staat aan. Na je wachtwoord, of na inloggen met Google of Microsoft, vragen we een code uit je app.
        </x-mortel::text>

        @if ($this->recoveryCodes !== [])
            <x-mortel::callout variant="warning" icon="key" heading="Herstelcodes" class="mb-4">
                <x-mortel::callout.text>
                    Elke code werkt één keer, als je je app niet bij de hand hebt. Bewaar ze op een veilige plek.
                </x-mortel::callout.text>
                <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm" data-test="recovery-codes">
                    @foreach ($this->recoveryCodes as $recoveryCode)
                        <li>{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            </x-mortel::callout>
        @endif

        <div class="space-y-4">
            <x-mortel::input label="Wachtwoord ter bevestiging" type="password" wire:model="confirm_password" autocomplete="current-password" />

            <div class="flex flex-wrap gap-2">
                <x-mortel::button type="button" wire:click="reveal">Toon herstelcodes</x-mortel::button>
                <x-mortel::button type="button" wire:click="regenerate">Nieuwe herstelcodes</x-mortel::button>
                <x-mortel::button type="button" variant="danger" wire:click="disable">Tweestaps uitzetten</x-mortel::button>
            </div>
        </div>
    @else
        <x-mortel::text class="mb-4">
            Vraag bij het inloggen ook een code uit een authenticator-app. Zo komt niemand binnen met alleen je wachtwoord.
        </x-mortel::text>

        <form wire:submit="enable" class="space-y-4">
            <x-mortel::input label="Wachtwoord ter bevestiging" type="password" wire:model="confirm_password" autocomplete="current-password" required />

            <x-mortel::button type="submit" variant="primary">Tweestaps instellen</x-mortel::button>
        </form>
    @endif
</x-mortel::card>
