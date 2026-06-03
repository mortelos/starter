<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Owner-editable role (DB data, D11).
 *
 * Mirrors the subset of Mortel\Models\Role the gate needs (name + trust_config),
 * with a string primary key so role ids are portable to the framework. In the
 * single-tenant baseline this lives in the one default database; the add-tenancy
 * skill detects it and makes it tenant-scoped when multi-tenancy is added.
 */
final class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'roles';

    protected $fillable = [
        'id',
        'name',
        'description',
        'trust_config',
    ];

    protected $casts = [
        'trust_config' => 'array',
    ];

    /**
     * @return HasMany<Policy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
