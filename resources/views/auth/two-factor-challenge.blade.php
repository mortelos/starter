<x-layouts::guest>
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-zinc-100">{{ config('app.name') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Tweestaps</p>
            </div>

            <div class="rounded-xl bg-surface p-8 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10" x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
                <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4" x-show="! recovery">
                    @csrf
                    <x-mortel::text>Vul de code uit je authenticator-app in.</x-mortel::text>
                    <x-mortel::input name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus />
                    <x-mortel::button type="submit" variant="primary" class="w-full">Doorgaan</x-mortel::button>
                    <x-mortel::button type="button" variant="ghost" class="w-full" x-on:click="recovery = true">Gebruik een herstelcode</x-mortel::button>
                </form>

                <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4" x-show="recovery" x-cloak>
                    @csrf
                    <x-mortel::text>Vul een van je herstelcodes in. Elke code werkt één keer.</x-mortel::text>
                    <x-mortel::input name="recovery_code" label="Herstelcode" autocomplete="off" />
                    <x-mortel::button type="submit" variant="primary" class="w-full">Doorgaan</x-mortel::button>
                    <x-mortel::button type="button" variant="ghost" class="w-full" x-on:click="recovery = false">Gebruik de code uit de app</x-mortel::button>
                </form>
            </div>
        </div>
    </div>
</x-layouts::guest>
