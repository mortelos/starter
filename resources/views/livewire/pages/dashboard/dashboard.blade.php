<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Dashboard')]
class extends Component {
    public string $proudMessage = '';

    public function mount(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $this->loadProudMessage();
    }

    private function loadProudMessage(): void
    {
        $resolver = config('starter.dashboard.proud_message_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return;
        }

        $message = app($resolver)->resolve();

        if (is_string($message)) {
            $this->proudMessage = $message;
        }
    }
}; ?>

<div class="p-6">
    <h1 class="mb-6 text-2xl font-semibold text-zinc-900">Management Dashboard</h1>

    @if ($proudMessage)
        <div class="mb-4 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">
            {{ $proudMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach (config('starter.dashboard.primary_widgets', []) as $widget)
            <livewire:dynamic-component :is="$widget" :wire:key="'dashboard-primary-'.$widget" />
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach (config('starter.dashboard.secondary_widgets', []) as $widget)
            <livewire:dynamic-component :is="$widget" :wire:key="'dashboard-secondary-'.$widget" />
        @endforeach
    </div>
</div>
