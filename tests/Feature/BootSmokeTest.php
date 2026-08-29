<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\SingleTenantResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\PendingCommand;
use Mortel\Access\ActorContextResolver;
use Mortel\Contracts\TenantResolver;
use Mortel\Models\UteqStoredEvent;
use Mortel\Repositories\UteqStoredEventRepository;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;
use Tests\TestCase;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('redirects the root to the login page for guests', function (): void {
    get('/')->assertRedirect('/login');
});

it('serves the login page', function (): void {
    get('/login')->assertOk();
});

it('completes the default password login to dashboard flow', function (): void {
    $user = User::factory()->create([
        'email' => 'admin@example.test',
    ]);

    post('/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home'));

    get('/')->assertRedirect('/dashboard');

    get('/dashboard')->assertOk();
});

it('throws LogicException when an auth controller is missing', function (): void {
    config()->set('starter.auth.controllers.password_login', null);

    expect(fn () => require base_path('routes/starter.php'))
        ->toThrow(LogicException::class, 'Missing starter route class config');
});

it('exposes the starter view namespaces and shell pages', function (): void {
    expect(View::exists('mortelos-starter::layouts.app'))->toBeTrue();
    expect(View::exists('layouts.guest'))->toBeTrue();

    expect(is_file(resource_path('views/components/auth/password-form.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/dashboard/dashboard.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/inbox/inbox.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/auth/login.blade.php')))->toBeTrue();
});

it('reports the doctor command as green for the default config', function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $command = artisan('starter:doctor');

    expect($command)->toBeInstanceOf(PendingCommand::class);
    assert($command instanceof PendingCommand);

    $command->assertSuccessful();
});

it('ships the MortelOS event store baseline', function (): void {
    expect(Schema::hasTable('events'))->toBeTrue();
    expect(config('event-sourcing.stored_event_model'))->toBe(UteqStoredEvent::class);
    expect(config('event-sourcing.stored_event_repository'))->toBe(UteqStoredEventRepository::class);
});

it('ships a single-tenant framework baseline', function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $resolver = app(TenantResolver::class);

    expect($resolver)->toBeInstanceOf(SingleTenantResolver::class)
        ->and($resolver->id())->toBe('default')
        ->and($resolver->initialized())->toBeTrue()
        ->and(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasTable('tenant_user'))->toBeTrue()
        ->and(Schema::hasTable('entities'))->toBeTrue()
        ->and(Schema::hasTable('entity_links'))->toBeTrue()
        ->and(DB::table('tenants')->where('id', 'default')->exists())->toBeTrue();
});

it('resolves a framework actor for the seeded admin', function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $actor = app(ActorContextResolver::class)->resolve($admin);

    expect($actor)->not->toBeNull()
        ->and($actor?->tenantId)->toBe('default')
        ->and($actor?->role->name)->toBe('owner');
});

it('fails the doctor command when the event store table is missing', function (): void {
    Schema::dropIfExists('events');

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->assertFailed();
});

it('fails the doctor command when event sourcing falls back to Spatie defaults', function (): void {
    config()->set('event-sourcing.stored_event_model', EloquentStoredEvent::class);
    config()->set('event-sourcing.stored_event_repository', EloquentStoredEventRepository::class);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->assertFailed();
});
