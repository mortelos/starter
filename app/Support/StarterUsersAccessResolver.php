<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final readonly class StarterUsersAccessResolver
{
    public function __construct(
        private StarterUsersResolver $usersResolver,
    ) {}

    public function canInspect(string $userId): bool
    {
        $userId = trim($userId);

        return $userId !== ''
            && $this->usersResolver->canManage()
            && User::query()->whereKey($userId)->exists();
    }
}
