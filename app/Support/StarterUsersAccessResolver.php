<?php

declare(strict_types=1);

namespace App\Support;

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
            && $this->usersResolver->hasMember($userId);
    }
}
