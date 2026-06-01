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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'role_id' => Role::factory(),
            'action' => 'governance.manage',
            'effect' => 'deny',
        ];
    }

    public function allow(): static
    {
        return $this->state(fn (array $attributes): array => ['effect' => 'allow']);
    }

    public function deny(): static
    {
        return $this->state(fn (array $attributes): array => ['effect' => 'deny']);
    }

    public function action(string $action): static
    {
        return $this->state(fn (array $attributes): array => ['action' => $action]);
    }
}
