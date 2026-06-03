<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'trust_config' => null,
        ];
    }
}
