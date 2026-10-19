<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;

it('renders pagination, loading and closing controls in Dutch', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-mortel::pagination :paginator="$paginator" />
        <x-mortel::placeholder height="100px" />
        <x-mortel::modal name="locale-check" aria-label="Voorbeeld" />
        BLADE, ['paginator' => new LengthAwarePaginator([1, 2], 4, 2)]);

    expect(app()->getLocale())->toBe('nl')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and($html)->toContain('Paginering', 'Vorige', 'Volgende', 'Laden…', 'aria-label="Sluiten"');
});
