<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ChangeLog\ChangeLogAccessCheck;
use App\Support\ChangeLog\ChangeLogDescriptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The registry of this test, shared for the rest of the test so sentences registered here reach the page and the
 * actions. The app builds a fresh one per resolve from the tagged DescribesChanges classes.
 */
function changeLogDescriptions(): ChangeLogDescriptions
{
    $descriptions = app(ChangeLogDescriptions::class);
    app()->instance(ChangeLogDescriptions::class, $descriptions);

    return $descriptions;
}

/**
 * @param  array<string, string>  $query
 * @return list<string> the lines of the CSV, the header first
 */
function changeLogCsv(User $user, array $query = []): array
{
    $response = Pest\Laravel\actingAs($user)->get(route('changelog.export', $query));
    $response->assertOk();
    $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));

    return $lines;
}

/** A member whose role is allowed `changelog.view` and the given other abilities. */
function changeLogViewer(string ...$abilities): User
{
    $user = User::factory()->create();
    $role = createRole('changelog-'.Str::lower(Str::random(8)));

    foreach ([ChangeLogAccessCheck::ABILITY, ...$abilities] as $ability) {
        grantPolicy($role, $ability);
    }

    attachGovernanceMembership($user, $role);

    return $user;
}

/**
 * Put one event in `events` the way the event store leaves it, for event types a test makes up. Real starter events
 * go through their Actions.
 *
 * @param  array<string, mixed>  $payload
 */
function storeChangeEvent(string $type, array $payload = [], ?string $actor = null, ?CarbonImmutable $at = null, ?string $reason = null): string
{
    $id = strtolower((string) Str::ulid());
    $at ??= CarbonImmutable::now();

    DB::table('events')->insert(changeEventRow($id, $type, $payload, $actor, $at, $reason));

    return $id;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function changeEventRow(string $id, string $type, array $payload, ?string $actor, CarbonImmutable $at, ?string $reason = null): array
{
    return [
        'id' => $id,
        'aggregate_uuid' => strtolower((string) Str::ulid()),
        'event_class' => 'Tests\\Events\\'.Str::studly(str_replace('.', '_', $type)),
        'aggregate_version' => 1,
        'event_version' => 1,
        'event_properties' => json_encode($payload, JSON_THROW_ON_ERROR),
        'meta_data' => '{}',
        'aggregate_type' => 'test',
        'event_type' => $type,
        'actor' => $actor,
        'reason' => $reason,
        'org_id' => governanceTenantId(),
        'branch_id' => governanceBranchId(),
        'timestamp' => $at->toIso8601String(),
        'created_at' => $at->format('Y-m-d H:i:s'),
    ];
}
