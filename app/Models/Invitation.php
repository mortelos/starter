<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read model of an invitation, written only by App\Projectors\InvitationProjector.
 * The key is the aggregate uuid (a ulid). Change an invitation through
 * App\Actions\Invitations, never on this model.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property string|null $role_id
 * @property string $invited_by
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $accepted_at
 * @property string|null $accepted_user_id
 */
#[Fillable(['id', 'tenant_id', 'email', 'role_id', 'invited_by', 'token_hash', 'expires_at', 'revoked_at', 'accepted_at', 'accepted_user_id', 'created_at', 'updated_at'])]
final class Invitation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }
}
