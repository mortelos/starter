<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Policy;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Policy>
 */
final class PolicyFactory extends Factory
{
    protected $model = Policy::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'role_id' => Role::factory(),
            'name' => 'Policy '.Str::random(8),
            'scope' => 'host',
            'action' => 'governance.manage',
            'actions' => ['governance.manage' => 'deny'],
            'effect' => 'deny',
            'priority' => 0,
            'conditions' => [],
            'org_id' => config('starter.tenancy.default_tenant_id', 'default'),
            'branch_id' => config('starter.tenancy.default_branch_id', 'main'),
        ];
    }

    public function allow(): static
    {
        return $this->state(function (array $attributes): array {
            $action = is_string($attributes['action'] ?? null) ? $attributes['action'] : 'governance.manage';

            return [
                'actions' => [$action => 'allow'],
                'effect' => 'allow',
            ];
        });
    }

    public function deny(): static
    {
        return $this->state(function (array $attributes): array {
            $action = is_string($attributes['action'] ?? null) ? $attributes['action'] : 'governance.manage';

            return [
                'actions' => [$action => 'deny'],
                'effect' => 'deny',
            ];
        });
    }

    public function action(string $action): static
    {
        return $this->state(function (array $attributes) use ($action): array {
            $effect = ($attributes['effect'] ?? 'deny') === 'allow' ? 'allow' : 'deny';

            return [
                'action' => $action,
                'actions' => [$action => $effect],
            ];
        });
    }
}
