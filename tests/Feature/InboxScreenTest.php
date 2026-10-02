<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows an empty inbox instead of an error when no inbox package is installed', function (): void {
    actingAs(User::factory()->create());

    get(route('inbox'))
        ->assertOk()
        ->assertSee('Inbox is nog niet ingericht');
});

it('renders the inbox components when the app registers them', function (): void {
    $stub = new class extends Component
    {
        public string $filterType = '';

        public string $filterRiskLevel = '';

        public string $filterSource = '';

        public bool $filterArchive = false;

        public string $filterStatus = '';

        public string $selectedItemId = '';

        public bool $isReturning = false;

        public string $itemId = '';

        public function render(): string
        {
            return '<div>inbox-stub</div>';
        }
    };

    Livewire::component('inbox.inbox-filter', $stub::class);
    Livewire::component('inbox.inbox-list', $stub::class);
    Livewire::component('inbox.inbox-detail', $stub::class);

    actingAs(User::factory()->create());

    get(route('inbox'))
        ->assertOk()
        ->assertSee('inbox-stub')
        ->assertDontSee('Inbox is nog niet ingericht');
});
