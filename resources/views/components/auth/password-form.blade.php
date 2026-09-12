<div>
    <form method="POST" action="{{ route('auth.password-login') }}" class="space-y-4">
        @csrf
        <x-mortel::input type="email" name="email" label="E-mailadres" :value="old('email')" required autofocus />
        <x-mortel::input type="password" name="password" label="Wachtwoord" required />
        <x-mortel::checkbox name="remember" label="Onthoud mij" />
        <x-mortel::button type="submit" variant="primary" class="w-full">Inloggen</x-mortel::button>
    </form>
</div>
