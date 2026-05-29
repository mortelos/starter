<?php

declare(strict_types=1);

it('ships publishable controller stubs', function (string $stub): void {
    expect(file_exists(__DIR__.'/../../stubs/'.$stub))->toBeTrue();
})->with([
    'Auth/Controllers/PasswordLoginController.php',
    'Auth/Controllers/PasskeyAuthenticatedController.php',
    'Auth/Controllers/AcceptInvitationController.php',
    'Auth/Controllers/TenantSelectController.php',
    'Auth/Actions/ResolvePostLoginRedirect.php',
    'config/starter.php',
]);

it('uses host App namespace in published stubs', function (string $stub, string $expectedNamespace): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/'.$stub);

    expect($contents)->toContain("namespace {$expectedNamespace};");
})->with([
    ['Auth/Controllers/PasswordLoginController.php', 'App\\Http\\Controllers\\Auth'],
    ['Auth/Controllers/PasskeyAuthenticatedController.php', 'App\\Http\\Controllers\\Auth'],
    ['Auth/Controllers/AcceptInvitationController.php', 'App\\Http\\Controllers\\Auth'],
    ['Auth/Controllers/TenantSelectController.php', 'App\\Http\\Controllers\\Auth'],
    ['Auth/Actions/ResolvePostLoginRedirect.php', 'App\\Actions\\Auth'],
]);

it('stub config uses array_replace_recursive to merge package defaults', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/config/starter.php');

    expect($contents)->toContain('array_replace_recursive');
    expect($contents)->toContain('vendor/mortelos/starter/config/starter.php');
});
