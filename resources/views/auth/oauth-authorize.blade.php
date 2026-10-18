{{-- Passport's consent screen for /oauth/authorize (Passport::authorizationView in StarterServiceProvider). --}}
<x-layouts::guest>
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ config('app.name') }}</h1>
                <p class="mt-1 text-sm text-gray-500">Je bent ingelogd als {{ $user->email }}</p>
            </div>

            <div class="space-y-6 rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5">
                <x-mortel::heading size="lg">{{ $client->name }} wil toegang tot je werkomgeving</x-mortel::heading>

                <div class="space-y-2">
                    <x-mortel::text>Als je toestaat, mag {{ $client->name }}:</x-mortel::text>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                        @forelse ($scopes as $scope)
                            <li>{{ $scope->description }}</li>
                        @empty
                            <li>je werkomgeving bekijken;</li>
                            <li>dingen voor je doen die jij zelf ook mag doen.</li>
                        @endforelse
                    </ul>
                </div>

                @if ($redirectHost || $selfRegistered)
                    <div class="space-y-2">
                        @if ($redirectHost)
                            <x-mortel::text>Na toestemming gaat je browser naar <strong>{{ $redirectHost }}</strong>.</x-mortel::text>
                        @endif
                        @if ($selfRegistered)
                            <x-mortel::text>Deze app heeft zichzelf aangemeld. Sta alleen toe als je deze app zelf net hebt gekoppeld.</x-mortel::text>
                        @endif
                    </div>
                @endif

                <div class="flex gap-3">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-mortel::button type="submit" class="w-full">Weigeren</x-mortel::button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-mortel::button type="submit" variant="primary" class="w-full">Toestaan</x-mortel::button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::guest>
