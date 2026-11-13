<div>
    <x-social-login::buttons />

    @if (app(\Mortel\SocialLogin\SocialLogin::class)->available() !== [])
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200 dark:border-zinc-800"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="bg-surface px-3 text-gray-400">of</span>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('auth.password-login') }}" class="space-y-4">
        @csrf
        <x-mortel::input type="email" name="email" label="E-mailadres" :value="old('email')" required autofocus />
        <x-mortel::input type="password" name="password" label="Wachtwoord" required />
        <x-mortel::checkbox name="remember" label="Onthoud mij" />
        <x-mortel::button type="submit" variant="primary" class="w-full">Inloggen</x-mortel::button>
    </form>
</div>
