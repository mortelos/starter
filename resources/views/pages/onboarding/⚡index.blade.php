<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Welkom')]
class extends Component {
    public int $step = 1;
    public string $userName = '';
    public string $userRole = '';
    public array $trustLevels = [];
    public bool $configured = true;

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $resolver = $this->onboardingResolver();

        if ($resolver === null) {
            $this->configured = false;

            return;
        }

        $state = $resolver->resolve();

        if ($state['completed']) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->userName = $state['user_name'];
        $this->userRole = $state['user_role'];
        $this->trustLevels = $state['trust_levels'];
    }

    public function next(): void
    {
        if ($this->step < 3) {
            $this->step++;
        } else {
            $this->complete();
        }
    }

    public function previous(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function complete(): void
    {
        $this->onboardingResolver()?->complete();

        $this->redirect(route('dashboard'), navigate: true);
    }


    private function onboardingResolver(): ?object
    {
        $resolver = config('starter.onboarding.resolver');

        if (! is_string($resolver) || $resolver === '') {
            return null;
        }

        return app($resolver);
    }

    private function dominantTrustLevel(): string
    {
        if (empty($this->trustLevels)) {
            return match ($this->userRole) {
                'owner' => 'propose',
                default => 'observe',
            };
        }

        $counts = array_count_values($this->trustLevels);
        arsort($counts);

        return array_key_first($counts);
    }
}; ?>

<div class="flex min-h-[80vh] items-center justify-center p-6">
    <div class="w-full max-w-xl">
        @if (! $configured)
            <x-mortel::empty icon="cog-6-tooth" heading="Onboarding is nog niet ingericht" description="Vul starter.onboarding.resolver in config/starter.php in om de welkomststappen te tonen." />
        @else
        {{-- Progress indicator --}}
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @foreach ([1 => 'Welcome', 2 => 'AI-intro', 3 => 'Inbox'] as $num => $label)
                    <div class="flex items-center gap-2" wire:key="onboarding-step-{{ $num }}">
                        <div @class([
                            'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                            'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $step === $num,
                            'bg-emerald-500 text-white' => $step > $num,
                            'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $step < $num,
                        ])>
                            @if ($step > $num)
                                <x-mortel::icon name="check" class="size-4" />
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span @class([
                            'text-sm hidden sm:inline',
                            'font-medium text-zinc-900 dark:text-zinc-100' => $step === $num,
                            'text-zinc-500 dark:text-zinc-400' => $step !== $num,
                        ])>{{ $label }}</span>
                    </div>
                    @if ($num < 3)
                        <div wire:key="onboarding-step-line-{{ $num }}" @class([
                            'h-px w-8',
                            'bg-emerald-500' => $step > $num,
                            'bg-zinc-200 dark:bg-zinc-800' => $step <= $num,
                        ])></div>
                    @endif
                @endforeach
            </div>
            <span class="text-sm text-zinc-500 dark:text-zinc-400">Stap {{ $step }} van 3</span>
        </div>

        {{-- Card --}}
        <div class="rounded-xl border border-zinc-200 bg-surface p-8 shadow-sm dark:border-zinc-800">
            {{-- Step 1: Welcome --}}
            @if ($step === 1)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Welkom, {{ $userName }}!</h2>
                <p class="mb-3 text-zinc-600 dark:text-zinc-400">
                    Dit is MortelOS, je persoonlijke werkplek voor alle AI-voorstellen, acties en inzichten.
                </p>
                <p class="text-zinc-600 dark:text-zinc-400">
                    De AI werkt voor jou: hij luistert, stelt voor en handelt op jouw akkoord.
                </p>
            @endif

            {{-- Step 2: AI Introduction --}}
            @if ($step === 2)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Jouw AI-assistent</h2>
                <p class="mb-6 text-zinc-600 dark:text-zinc-400">De AI in MortelOS werkt met drie niveaus:</p>

                <div class="mb-6 space-y-3">
                    <div class="flex items-start gap-3 rounded-lg bg-surface-alt p-4">
                        <span class="text-lg">👁</span>
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">Observe</span>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Adviseert, jij beslist altijd</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-surface-alt p-4">
                        <span class="text-lg">📋</span>
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">Propose</span>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Doet voorstellen, jij keurt goed</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-surface-alt p-4">
                        <span class="text-lg">⚡</span>
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">Act</span>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Voert autonoom uit (met jouw permissie)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950">
                    <p class="text-sm text-emerald-800 dark:text-emerald-200">
                        Jouw huidig niveau: <span class="font-semibold uppercase">{{ $this->dominantTrustLevel() }}</span> voor jouw rol
                    </p>
                </div>
            @endif

            {{-- Step 3: Inbox tutorial --}}
            @if ($step === 3)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Je werk-inbox</h2>
                <p class="mb-6 text-zinc-600 dark:text-zinc-400">
                    In de inbox vind je alle AI-voorstellen en acties. Akkoord? Dan wordt het uitgevoerd. Afwijzen? De AI leert ervan.
                </p>

                <div class="mb-2 space-y-2 rounded-lg border border-zinc-200 bg-surface-alt p-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between rounded-md bg-surface px-3 py-2 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">⚡</span>
                            <span class="text-sm text-zinc-700 dark:text-zinc-200">Entiteit bijgewerkt</span>
                        </div>
                        <div class="flex gap-1">
                            <x-mortel::badge color="emerald" size="sm">Akkoord</x-mortel::badge>
                            <x-mortel::badge color="red" size="sm">Afwijzen</x-mortel::badge>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-surface px-3 py-2 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">📋</span>
                            <span class="text-sm text-zinc-700 dark:text-zinc-200">Voorstel: mail versturen</span>
                        </div>
                        <div class="flex gap-1">
                            <x-mortel::badge color="emerald" size="sm">Akkoord</x-mortel::badge>
                            <x-mortel::badge color="red" size="sm">Afwijzen</x-mortel::badge>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Navigation --}}
        <x-mortel::page-actions class="mt-6">
            @if ($step > 1)
                <x-slot:back>
                    <x-mortel::button wire:click="previous">← Vorige</x-mortel::button>
                </x-slot:back>
            @endif
            <x-mortel::button wire:click="next" variant="primary">
                @if ($step === 3)
                    Aan de slag →
                @else
                    Volgende →
                @endif
            </x-mortel::button>
        </x-mortel::page-actions>
        @endif
    </div>
</div>
