<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Contracts\ChangeLog\DescribesChanges;
use Closure;
use Throwable;

/**
 * Registry outlet of the change log: per stored event type a part ("onderdeel") and a describer that turns the event
 * into a readable sentence with its Undo. An app registers its own types in a class that implements DescribesChanges,
 * tagged with ChangeLogDescriptions::TAG in a service provider:
 *
 *     public function register(ChangeLogDescriptions $descriptions): void
 *     {
 *         $descriptions->part('planning', 'Planning');
 *         $descriptions->register('planning.phase.shifted', 'planning', fn (ChangeLogEntry $e) => new ChangeDescription(…));
 *     }
 *
 * A type without a registration reads "Wijziging vastgelegd" under the part "Overig". The part of a type is fixed at
 * registration, so the log filters and hides parts in SQL instead of after the query.
 */
final class ChangeLogDescriptions
{
    public const string TAG = 'changelog.descriptions';

    public const string OTHER = 'other';

    public const string FALLBACK_SENTENCE = 'Wijziging vastgelegd';

    /** @var array<string, string> part key => label */
    private array $parts = [];

    /** @var array<string, array{part: string, describe: Closure(ChangeLogEntry): (ChangeDescription|string)}> */
    private array $types = [];

    /** @var array<string, array{label: string, fields: array<string, string>}> */
    private array $tables = [];

    /** @param  iterable<mixed>  $describers  the classes tagged with TAG */
    public function __construct(iterable $describers = [])
    {
        foreach ($describers as $describer) {
            if ($describer instanceof DescribesChanges) {
                $describer->register($this);
            }
        }
    }

    public function part(string $key, string $label): void
    {
        $this->parts[$key] = $label;
    }

    /** @param  Closure(ChangeLogEntry): (ChangeDescription|string)  $describe */
    public function register(string $eventType, string $part, Closure $describe): void
    {
        $this->types[$eventType] = ['part' => $part, 'describe' => $describe];
    }

    /**
     * Words for an own table and its columns, used when an undo is refused because rows changed in the meantime
     * (the `conflicts` of RevertOwnTableChanges or an OwnTableConflict).
     *
     * @param  array<string, string>  $fields  column => label
     */
    public function table(string $table, string $label, array $fields = []): void
    {
        $this->tables[$table] = ['label' => $label, 'fields' => $fields];
    }

    public function describe(ChangeLogEntry $entry): ChangeDescription
    {
        $registration = $this->types[$entry->type] ?? null;

        if ($registration === null) {
            return new ChangeDescription(self::FALLBACK_SENTENCE, $entry->reason);
        }

        try {
            $description = ($registration['describe'])($entry);
        } catch (Throwable $exception) {
            // An old payload shape must not take the whole log down; the row falls back and the error is reported.
            report($exception);

            return new ChangeDescription(self::FALLBACK_SENTENCE, $entry->reason);
        }

        return is_string($description) ? new ChangeDescription($description) : $description;
    }

    public function partOf(string $eventType): ChangePart
    {
        $key = $this->types[$eventType]['part'] ?? self::OTHER;

        return new ChangePart($key, $this->parts()[$key] ?? $key);
    }

    /** @return array<string, string> part key => label, "Overig" last */
    public function parts(): array
    {
        $parts = $this->parts;

        foreach ($this->types as $registration) {
            $parts[$registration['part']] ??= $registration['part'];
        }

        unset($parts[self::OTHER]);
        asort($parts);

        return [...$parts, self::OTHER => 'Overig'];
    }

    /** @return list<string> the event types registered under this part */
    public function typesOf(string $part): array
    {
        return array_keys(array_filter($this->types, fn (array $registration): bool => $registration['part'] === $part));
    }

    /** @return list<string> */
    public function registeredTypes(): array
    {
        return array_keys($this->types);
    }

    public function tableLabel(string $table): string
    {
        return $this->tables[$table]['label'] ?? $table;
    }

    public function fieldLabel(string $table, string $field): string
    {
        return $this->tables[$table]['fields'][$field] ?? $field;
    }
}
