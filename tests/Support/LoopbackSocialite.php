<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Socialite\Testing\FakeProvider;
use Laravel\Socialite\Testing\SocialiteFake;

/**
 * A pretend Google or Microsoft for browser tests: the redirect to the provider comes straight back to the
 * callback of mortelos/social-login, and the provider "returns" the profile that was set up with fake(). Socialite's
 * own fake would send the browser to a host that does not exist.
 */
final class LoopbackSocialite extends SocialiteFake
{
    public function fake($driver, $user = null): static
    {
        $this->providers[$driver] = new class($driver, fn () => $this->factory->driver($driver), $user) extends FakeProvider
        {
            public function redirect(): RedirectResponse
            {
                return new RedirectResponse(route('social-login.callback', ['provider' => Str::after($this->driver, 'social-login-')]).'?code=pretend&state=pretend');
            }
        };

        return $this;
    }
}
