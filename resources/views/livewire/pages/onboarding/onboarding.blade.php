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

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $state = $this->onboardingResolver()->resolve();

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
        $this->onboardingResolver()->complete();

        $this->redirect(route('dashboard'), navigate: true);
    }


    private function onboardingResolver(): object
    {
        $resolver = config('starter.onboarding.resolver');

        if (! is_string($resolver) || $resolver === '') {
            throw new LogicException('Missing starter onboarding resolver config [starter.onboarding.resolver].');
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
        {{-- Progress indicator --}}
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @foreach ([1 => 'Welcome', 2 => 'AI-intro', 3 => 'Inbox'] as $num => $label)
                    <div class="flex items-center gap-2">
                        <div @class([
                            'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                            'bg-zinc-900 text-white' => $step === $num,
                            'bg-emerald-500 text-white' => $step > $num,
                            'bg-zinc-200 text-zinc-500' => $step < $num,
                        ])>
                            @if ($step > $num)
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span @class([
                            'text-sm hidden sm:inline',
                            'font-medium text-zinc-900' => $step === $num,
                            'text-zinc-500' => $step !== $num,
                        ])>{{ $label }}</span>
                    </div>
                    @if ($num < 3)
                        <div @class([
                            'h-px w-8',
                            'bg-emerald-500' => $step > $num,
                            'bg-zinc-200' => $step <= $num,
                        ])></div>
                    @endif
                @endforeach
            </div>
            <span class="text-sm text-zinc-400">Stap {{ $step }} van 3</span>
        </div>

        {{-- Card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
            {{-- Step 1: Welcome --}}
            @if ($step === 1)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900">Welkom, {{ $userName }}!</h2>
                <p class="mb-3 text-zinc-600">
                    Dit is UteqOS, je persoonlijke werkplek voor alle AI-voorstellen, acties en inzichten.
                </p>
                <p class="text-zinc-600">
                    De AI werkt voor jou: hij luistert, stelt voor en handelt op jouw akkoord.
                </p>
            @endif

            {{-- Step 2: AI Introduction --}}
            @if ($step === 2)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900">Jouw AI-assistent</h2>
                <p class="mb-6 text-zinc-600">De AI in UteqOS werkt met drie niveaus:</p>

                <div class="mb-6 space-y-3">
                    <div class="flex items-start gap-3 rounded-lg bg-zinc-50 p-4">
                        <span class="text-lg">👁</span>
                        <div>
                            <span class="font-medium text-zinc-900">Observe</span>
                            <p class="text-sm text-zinc-500">Adviseert, jij beslist altijd</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-zinc-50 p-4">
                        <span class="text-lg">📋</span>
                        <div>
                            <span class="font-medium text-zinc-900">Propose</span>
                            <p class="text-sm text-zinc-500">Doet voorstellen, jij keurt goed</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-zinc-50 p-4">
                        <span class="text-lg">⚡</span>
                        <div>
                            <span class="font-medium text-zinc-900">Act</span>
                            <p class="text-sm text-zinc-500">Voert autonoom uit (met jouw permissie)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-sm text-emerald-800">
                        Jouw huidig niveau: <span class="font-semibold uppercase">{{ $this->dominantTrustLevel() }}</span> voor jouw rol
                    </p>
                </div>
            @endif

            {{-- Step 3: Inbox tutorial --}}
            @if ($step === 3)
                <h2 class="mb-4 text-2xl font-semibold text-zinc-900">Je werk-inbox</h2>
                <p class="mb-6 text-zinc-600">
                    In de inbox vind je alle AI-voorstellen en acties. Akkoord? Dan wordt het uitgevoerd. Afwijzen? De AI leert ervan.
                </p>

                <div class="mb-2 space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">⚡</span>
                            <span class="text-sm text-zinc-700">Entiteit bijgewerkt</span>
                        </div>
                        <div class="flex gap-1">
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Akkoord</span>
                            <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Afwijzen</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">📋</span>
                            <span class="text-sm text-zinc-700">Voorstel: mail versturen</span>
                        </div>
                        <div class="flex gap-1">
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Akkoord</span>
                            <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Afwijzen</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Navigation --}}
        <div class="mt-6 flex items-center justify-between">
            <div>
                @if ($step > 1)
                    <button wire:click="previous" class="rounded-lg border border-zinc-200 bg-white px-5 py-2.5 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50">
                        ← Vorige
                    </button>
                @endif
            </div>
            <button wire:click="next" class="rounded-lg bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-800">
                @if ($step === 3)
                    Aan de slag →
                @else
                    Volgende →
                @endif
            </button>
        </div>
    </div>
</div>
