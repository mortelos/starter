<?php

declare(strict_types=1);

use App\Models\User;
use Mortelos\Ui\Updates\BuildId;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Browser\Support\FlippableBuild;

/*
 * A deploy while a user is typing (mortelos/ui updates): the page reloads on the new build, the text is back in the
 * field and a save that certainly did not run happens exactly once. The settings profile form is the real starter
 * form; FlippableBuild stands in for the build id a release directory, MORTEL_BUILD, git or the Vite manifest gives.
 */

const SAFE_DEPLOY_NAME = 'input[name="name"]';
const SAFE_DEPLOY_SAVE = 'form[wire\:submit="updateProfile"] button[type="submit"]';

beforeEach(function (): void {
    // Not 'testing': Laravel skips the CSRF check there, and the page must behave as it does in production.
    app()['env'] = 'local';
    config(['mortel-ui.updates.resolver' => FlippableBuild::class]);
    // The app booted with the git id already resolved; resolve it again through the configured resolver.
    app()->forgetInstance(BuildId::class);
    FlippableBuild::deploy('1.0.0');
});

/** Run $script in the page, retrying while a reload replaces it. */
function safeDeployScript(PendingAwaitablePage|AwaitableWebpage $page, string $script): mixed
{
    $last = null;

    for ($i = 0; $i < 50; $i++) {
        try {
            return $page->script($script);
        } catch (Throwable $e) {
            $last = $e;
            $page->wait(0.1);
        }
    }

    throw $last;
}

/** Wait until the page function returns something truthy. */
function safeDeployUntil(PendingAwaitablePage|AwaitableWebpage $page, string $script, float $seconds = 10.0): void
{
    $deadline = microtime(true) + $seconds;

    do {
        try {
            if ($page->script('async () => !!(await ('.$script.')())')) {
                return;
            }
        } catch (Throwable) {
        }

        $page->wait(0.1);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Timed out waiting for: '.$script);
}

it('puts the typed text back after a deploy and saves exactly once', function (): void {
    $saves = 0;
    User::updated(function () use (&$saves): void {
        $saves++;
    });

    $page = verifyAsAdmin('/settings');
    safeDeployUntil($page, "() => !!window.Livewire && !!window.__mortelResilience && document.readyState === 'complete'");

    // Typing, then a pause longer than the journal's write delay.
    $page->fill(SAFE_DEPLOY_NAME, 'Admin Na Deploy');
    $page->wait(0.8);

    // The release goes live while the form is open; the click is refused before any component code runs.
    FlippableBuild::deploy('1.0.1');
    $page->script("() => { window.__loadMarker = 'old page'; }");
    $page->click(SAFE_DEPLOY_SAVE);

    // The old page is gone (a fresh document has no marker) and the new one shows the text again.
    safeDeployUntil($page, '() => window.__loadMarker === undefined && !!window.Livewire && !!window.__mortelResilience && document.readyState === "complete"', 15.0);
    safeDeployUntil($page, '() => document.body.textContent.includes("Profiel bijgewerkt.")', 15.0);

    expect($saves)->toBe(1)
        ->and(safeDeployScript($page, '() => document.querySelector('.json_encode(SAFE_DEPLOY_NAME).').value'))->toBe('Admin Na Deploy')
        ->and(User::query()->where('email', 'admin@example.test')->value('name'))->toBe('Admin Na Deploy');
});

it('shows the update notice in the sidebar after a deploy', function (): void {
    $page = verifyAsAdmin('/dashboard');
    safeDeployUntil($page, "() => !!window.Livewire && !!window.__mortelResilience && document.readyState === 'complete'");

    $page->assertScript("getComputedStyle(document.querySelector('[data-mortel-update-notice]')).display === 'none'");

    FlippableBuild::deploy('1.0.1');
    // The page asks the version endpoint when it comes back online or a poll fires.
    $page->script("() => window.dispatchEvent(new Event('online'))");
    safeDeployUntil($page, "() => document.documentElement.hasAttribute('data-mortel-update')");

    $page->assertScript("document.querySelector('[data-mortel-update-notice]').closest('[data-mortel-sidebar]') !== null")
        ->assertScript("getComputedStyle(document.querySelector('[data-mortel-update-notice-caption]')).display !== 'none'")
        ->assertSeeIn('[data-mortel-update-notice-caption]', 'Update beschikbaar')
        ->assertSeeIn('[data-mortel-update-notice-caption]', 'v1.0.1');
});
