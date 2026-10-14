<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

final class OnboardingScreenTestResolver
{
    /**
     * @return array{completed: bool, user_name: string, user_role: string, trust_levels: array<string, string>}
     */
    public function resolve(): array
    {
        return ['completed' => false, 'user_name' => 'Nathan', 'user_role' => 'owner', 'trust_levels' => []];
    }

    public function complete(): void {}
}

it('puts the wizard navigation in the action bar, with back only after step 1', function (): void {
    config(['starter.onboarding.resolver' => OnboardingScreenTestResolver::class]);
    actingAs(User::factory()->create());

    Livewire::test('pages::onboarding.index')
        ->assertSeeHtml('data-mortel-page-actions')
        ->assertSee('Volgende')
        ->assertDontSee('Vorige')
        ->call('next')
        ->assertSeeHtml('data-mortel-page-actions')
        ->assertSee('Vorige');
});

it('shows an empty state naming the missing config key instead of failing without a resolver', function (): void {
    config(['starter.onboarding.resolver' => null]);
    actingAs(User::factory()->create());

    get('/onboarding')
        ->assertOk()
        ->assertSee('Onboarding is nog niet ingericht')
        ->assertSee('starter.onboarding.resolver')
        ->assertDontSee('Volgende');
});
