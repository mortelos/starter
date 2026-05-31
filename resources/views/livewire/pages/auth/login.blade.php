<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::guest')]
#[Title('Inloggen')]
class extends Component {
    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(route('home'), navigate: true);
        }
    }
}; ?>

<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-gray-500">Log in op je account</p>
        </div>

        <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5">
            @php
                $passkeyFormComponent = config('starter.auth.passkey_form_component');
                $passwordFormComponent = config('starter.auth.password_form_component');
            @endphp

            @if (is_string($passkeyFormComponent) && $passkeyFormComponent !== '')
                <x-dynamic-component :component="$passkeyFormComponent" />
            @endif

            @if (is_string($passkeyFormComponent) && $passkeyFormComponent !== '' && is_string($passwordFormComponent) && $passwordFormComponent !== '')
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-white px-3 text-gray-400">of</span>
                    </div>
                </div>
            @endif

            @if (is_string($passwordFormComponent) && $passwordFormComponent !== '')
                <x-dynamic-component :component="$passwordFormComponent" />
            @endif
        </div>
    </div>
</div>
