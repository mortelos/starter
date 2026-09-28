<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

const SETTINGS_PROFILE_SUBMIT = 'form[wire\:submit="updateProfile"] button[type="submit"]';
const SETTINGS_PASSWORD_SUBMIT = 'form[wire\:submit="updatePassword"] button[type="submit"]';

it('saves the profile with an island-only round trip and leaves the password card alone', function (): void {
    $page = verifyAsAdmin('/settings');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);

    $page->script("() => { document.querySelector('h1').__verifyProbe = 1; return true; }");
    $page->fill('current_password', 'half-getypt');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('name', 'Admin Verify')
        ->click(SETTINGS_PROFILE_SUBMIT), 1, 'profile');

    $page->assertSee('Profiel bijgewerkt.')
        ->assertScript("document.querySelector('h1').__verifyProbe === 1")
        ->assertValue('current_password', 'half-getypt');
});

it('shows a wrong current password inside the password island', function (): void {
    $page = verifyAsAdmin('/settings');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('current_password', 'niet-het-wachtwoord')
        ->fill('password', 'een-nieuw-wachtwoord')
        ->fill('password_confirmation', 'een-nieuw-wachtwoord')
        ->click(SETTINGS_PASSWORD_SUBMIT), 1, 'password');

    $page->assertSee('Huidige wachtwoord klopt niet.');
});

it('shows a server validation error inside the password island', function (): void {
    $page = verifyAsAdmin('/settings');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('current_password', 'password')
        ->fill('password', 'kort')
        ->fill('password_confirmation', 'kort')
        ->click(SETTINGS_PASSWORD_SUBMIT), 1, 'password');

    $page->assertSee('at least 12 characters');
});
