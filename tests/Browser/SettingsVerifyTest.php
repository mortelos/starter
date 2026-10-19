<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

const SETTINGS_PROFILE_SUBMIT = 'form[wire\:submit="updateProfile"] button[type="submit"]';
const SETTINGS_PASSWORD_SUBMIT = 'form[wire\:submit="updatePassword"] button[type="submit"]';

it('saves the profile with an island-only round trip and leaves the password card alone', function (): void {
    $page = verifyAsAdmin('/settings');

    expectNoPostsSinceLoad($page);

    $page->script("() => { document.querySelector('h1').__verifyProbe = 1; return true; }");
    $page->fill('current_password', 'half-typed');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('name', 'Admin Verify')
        ->click(SETTINGS_PROFILE_SUBMIT), 1, 'profile');

    $page->assertSee('Profiel bijgewerkt.')
        ->assertScript("document.querySelector('h1').__verifyProbe === 1")
        ->assertValue('current_password', 'half-typed');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});

it('shows a wrong current password inside the password island', function (): void {
    $page = verifyAsAdmin('/settings');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('current_password', 'not-the-password')
        ->fill('password', 'a-new-password')
        ->fill('password_confirmation', 'a-new-password')
        ->click(SETTINGS_PASSWORD_SUBMIT), 1, 'password');

    $page->assertSee('Huidige wachtwoord klopt niet.');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});

it('shows a server validation error inside the password island', function (): void {
    $page = verifyAsAdmin('/settings');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('current_password', 'password')
        ->fill('password', 'short')
        ->fill('password_confirmation', 'short')
        ->click(SETTINGS_PASSWORD_SUBMIT), 1, 'password');

    $page->assertSee('at least 12 characters');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});
