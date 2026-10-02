<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

/**
 * Sidebar resolver for the verify tests: one action item that opens universal search
 * and one plain link, the two item kinds sidebar-nav renders.
 */
final class NavigationFixture
{
    /**
     * @return list<array{label: string, items: list<array<string, string>>}>
     */
    public function sections(mixed $user): array
    {
        return [[
            'label' => 'Werk',
            'items' => [
                ['type' => 'action', 'icon' => 'magnifying-glass', 'label' => 'Zoeken', 'action' => 'open-universal-search'],
                ['icon' => 'cog-6-tooth', 'label' => 'Instellingen', 'route' => 'settings', 'permission' => 'nav.sidebar.settings'],
            ],
        ]];
    }

    public function inboxCount(mixed $user): int
    {
        return 0;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function overviews(mixed $user): array
    {
        return [];
    }
}
