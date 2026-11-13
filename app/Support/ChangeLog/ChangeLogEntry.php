<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use Carbon\CarbonImmutable;
use Mortel\Support\Scalar;

/**
 * One stored event as the change log reads it: the columns of `events` it needs and the decoded payload. This is
 * what a describer registered in ChangeLogDescriptions receives.
 */
final readonly class ChangeLogEntry
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $aggregateUuid,
        public int $aggregateVersion,
        public ?string $actor,
        public ?string $reason,
        public CarbonImmutable $at,
        public array $payload,
    ) {}

    /** @param  mixed  $row  a row of `events` from the query builder */
    public static function fromRow(mixed $row): self
    {
        $data = is_object($row) ? get_object_vars($row) : (is_array($row) ? $row : []);
        $payload = $data['event_properties'] ?? null;
        $payload = is_string($payload) ? json_decode($payload, true) : $payload;
        $at = $data['created_at'] ?? null;

        return new self(
            id: Scalar::string($data['id'] ?? ''),
            type: Scalar::string($data['event_type'] ?? ''),
            aggregateUuid: is_string($data['aggregate_uuid'] ?? null) ? $data['aggregate_uuid'] : null,
            aggregateVersion: is_numeric($data['aggregate_version'] ?? null) ? (int) $data['aggregate_version'] : 0,
            actor: is_string($data['actor'] ?? null) && $data['actor'] !== '' ? $data['actor'] : null,
            reason: is_string($data['reason'] ?? null) && $data['reason'] !== '' ? $data['reason'] : null,
            at: is_string($at) ? CarbonImmutable::parse($at) : CarbonImmutable::now(),
            payload: is_array($payload) ? array_filter($payload, is_string(...), ARRAY_FILTER_USE_KEY) : [],
        );
    }

    /** A payload field as text, or null when it is missing or not a scalar. */
    public function text(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
