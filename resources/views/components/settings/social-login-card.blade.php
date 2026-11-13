@props(['providers', 'error' => ''])

<x-mortel::card>
    <x-mortel::heading size="lg" level="2" class="mb-4">Inloggen met Google of Microsoft</x-mortel::heading>

    @if ($error !== '')
        <x-mortel::callout variant="danger" icon="exclamation-triangle" :heading="$error" class="mb-4" role="alert" />
    @endif

    <x-mortel::text class="mb-4">
        Koppel je account, dan kun je op de loginpagina met één klik inloggen. Het e-mailadres van het Google- of
        Microsoft-account moet hetzelfde zijn als dat van dit account.
    </x-mortel::text>

    <ul class="space-y-3">
        @foreach ($providers as $provider)
            <li class="flex items-center justify-between gap-3" data-social-link="{{ $provider['name'] }}">
                <span>{{ $provider['label'] }}</span>

                @if ($provider['linked'])
                    <x-mortel::badge color="green">Gekoppeld</x-mortel::badge>
                @else
                    <x-mortel::button :href="$provider['url']">Koppel {{ $provider['label'] }}</x-mortel::button>
                @endif
            </li>
        @endforeach
    </ul>
</x-mortel::card>
