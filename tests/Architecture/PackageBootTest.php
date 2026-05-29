<?php

declare(strict_types=1);

use Mortelos\Starter\MortelosStarterServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(MortelosStarterServiceProvider::class);
});

it('merges package config defaults under the starter key', function (): void {
    expect(config('starter.auth'))
        ->toBeArray()
        ->toHaveKeys(['post_login_redirect_resolver', 'controllers']);

    expect(config('starter.auth.controllers'))
        ->toBeArray()
        ->toHaveKeys([
            'accept_invitation',
            'passkey_authenticated',
            'password_login',
            'tenant_select',
        ]);

    expect(config('starter.dashboard.primary_widgets'))
        ->toBeArray()
        ->not->toBeEmpty();
});

it('registers the mortelos-starter view namespace', function (): void {
    $factory = app('view');

    expect($factory->exists('mortelos-starter::layouts.app'))->toBeTrue();
    expect($factory->exists('mortelos-starter::auth.password-form'))->toBeTrue();
});

it('registers the starter livewire namespace', function (): void {
    $factory = app('view');

    expect($factory->exists('starter::pages.dashboard.dashboard'))->toBeTrue();
    expect($factory->exists('starter::pages.auth.login'))->toBeTrue();
    expect($factory->exists('starter::pages.inbox.inbox'))->toBeTrue();
});

it('publishes the config and stubs tags', function (): void {
    $groups = MortelosStarterServiceProvider::$publishGroups
        ?? \Illuminate\Support\ServiceProvider::$publishGroups;

    expect($groups)->toHaveKey('mortelos-starter');
    expect($groups)->toHaveKey('mortelos-starter-views');
    expect($groups)->toHaveKey('mortelos-starter-stubs');
});
