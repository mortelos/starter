<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('passes a sidebar action to $dispatch as a JS string literal, not as interpolated code', function (): void {
    $action = "x'); window.pwned = 1; ('\"<b>";

    app()->instance('test.sidebar-resolver', new class($action)
    {
        public function __construct(private string $action) {}

        /** @return list<array<string, mixed>> */
        public function sections(mixed $user): array
        {
            return [['label' => 'Werk', 'items' => [
                ['type' => 'action', 'icon' => 'magnifying-glass', 'label' => 'Zoeken', 'action' => $this->action],
            ]]];
        }

        public function inboxCount(mixed $user): int
        {
            return 0;
        }

        /** @return list<array<string, string>> */
        public function overviews(mixed $user): array
        {
            return [];
        }
    });
    config(['starter.navigation.sidebar_resolver' => 'test.sidebar-resolver']);
    actingAs(User::factory()->create());

    $html = Livewire::test('starter::shared.sidebar-nav')->html();

    preg_match('/x-on:click="([^"]*)"/', $html, $match);

    expect(html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5))
        ->toBe('$dispatch('.Js::from($action)->toHtml().')');
});
