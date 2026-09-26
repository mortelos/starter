<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

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

    Livewire::test('starter::pages.onboarding.onboarding')
        ->assertSeeHtml('data-mortel-page-actions')
        ->assertSee('Volgende')
        ->assertDontSee('Vorige')
        ->call('next')
        ->assertSeeHtml('data-mortel-page-actions')
        ->assertSee('Vorige');
});
