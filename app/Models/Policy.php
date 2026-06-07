<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single owner-editable policy row: "role R may/!may do action A" (D11).
 *
 * Deny-by-default lives in the GATE, not here: the absence of an `allow` row is
 * a denial, so a `deny` row is only needed to override an inherited allow.
 * `effect` is 'allow' | 'deny'. Lives alongside Role in the one default database.
 *
 * This is the cheap host store. When the framework is installed its richer
 * policy engine (CheckPolicy + the `policies` table it owns) takes over via the
 * gate's delegation, and this model is no longer consulted.
 */
final class Policy extends Model
{
    /** @use HasFactory<PolicyFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'policies';

    protected $fillable = [
        'id',
        'name',
        'description',
        'scope',
        'resource_type',
        'resource_id',
        'role_id',
        'action',
        'actions',
        'effect',
        'priority',
        'conditions',
        'org_id',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'actions' => 'array',
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
