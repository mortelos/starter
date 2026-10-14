<x-layouts::guest>
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ config('app.name') }}</h1>
                <p class="mt-1 text-sm text-gray-500">Je bent uitgenodigd</p>
            </div>

            <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5">
                @if ($isOpen)
                    <form method="POST" action="{{ route('invite.store', ['token' => $token]) }}" class="space-y-4">
                        @csrf

                        @error('email')
                            <x-mortel::callout variant="danger" icon="exclamation-triangle" :heading="$message" />
                        @enderror

                        <x-mortel::input type="email" label="E-mailadres" :value="$email" readonly />
                        <x-mortel::input name="name" label="Naam" :value="old('name')" required autofocus autocomplete="name" />
                        <x-mortel::input type="password" name="password" label="Wachtwoord" required autocomplete="new-password" />
                        <x-mortel::input type="password" name="password_confirmation" label="Herhaal wachtwoord" required autocomplete="new-password" />
                        <x-mortel::button type="submit" variant="primary" class="w-full">Account aanmaken</x-mortel::button>
                    </form>
                @else
                    <x-mortel::callout variant="warning" icon="exclamation-triangle" heading="Deze uitnodiging werkt niet meer">
                        <x-mortel::callout.text>
                            De link is verlopen, ingetrokken of al gebruikt. Vraag de beheerder om een nieuwe uitnodiging.
                            Heb je al een account? Dan kun je gewoon <x-mortel::link :href="route('login')">inloggen</x-mortel::link>.
                        </x-mortel::callout.text>
                    </x-mortel::callout>
                @endif
            </div>
        </div>
    </div>
</x-layouts::guest>
