<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use Illuminate\Database\UniqueConstraintViolationException;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Undo;
use Mortel\OwnTables\OwnTableChanges;
use Mortel\OwnTables\OwnTableConflict;
use Mortel\OwnTables\RevertOwnTableChanges;
use Mortel\Support\Scalar;
use Spatie\EventSourcing\AggregateRoots\Exceptions\CouldNotPersistAggregate;
use Throwable;

/**
 * The Dutch message for an undo that did not go through. For the framework's own-table undo (RevertOwnTableChanges)
 * it says which rows changed in the meantime: that action names them in `data.conflicts` (table, key, reason
 * `changed`, `missing` or `exists`, and the changed fields). Table and column words come from
 * ChangeLogDescriptions::table().
 */
final readonly class UndoFailureText
{
    public const string NOTHING_CHANGED = 'Er is niets teruggedraaid.';

    private const string CHANGED_IN_THE_MEANTIME = 'Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd.';

    /** Columns that change on every own-table write: naming them tells a user nothing. */
    private const array BOOKKEEPING = ['created_at', 'updated_at'];

    public function __construct(private ChangeLogDescriptions $descriptions) {}

    public function forResult(Undo $undo, ActionResult $result): string
    {
        $lines = [];

        if ($undo->action === RevertOwnTableChanges::class) {
            foreach (Scalar::listOfArrays($result->data['conflicts'] ?? null) as $conflict) {
                $lines[] = $this->conflictLine(
                    Scalar::string($conflict['table'] ?? ''),
                    is_array($conflict['key'] ?? null) ? $conflict['key'] : [],
                    Scalar::string($conflict['reason'] ?? ''),
                    array_values(array_filter(is_array($conflict['fields'] ?? null) ? $conflict['fields'] : [], is_string(...))),
                );
            }
        }

        if ($lines !== []) {
            return self::CHANGED_IN_THE_MEANTIME.' '.implode(' ', $lines).' '.self::NOTHING_CHANGED;
        }

        return 'Ongedaan maken lukte niet: '.rtrim($result->message, '.').'. '.self::NOTHING_CHANGED;
    }

    public function forException(Throwable $exception): ?string
    {
        return match (true) {
            // Thrown when a row is not in the state an own-table write expects; nothing is written.
            $exception instanceof OwnTableConflict => self::CHANGED_IN_THE_MEANTIME.' Iemand heeft het intussen aangepast. '.self::NOTHING_CHANGED,
            $exception instanceof CouldNotPersistAggregate, $exception instanceof UniqueConstraintViolationException => 'Iemand anders heeft deze wijziging net ongedaan gemaakt of aangepast. '.self::NOTHING_CHANGED,
            default => null,
        };
    }

    /**
     * @param  array<mixed>  $key
     * @param  list<string>  $fields
     */
    private function conflictLine(string $table, array $key, string $reason, array $fields): string
    {
        $subject = trim($this->descriptions->tableLabel($table).' '.implode(', ', array_map(fn (mixed $value): string => Scalar::scalarString($value), $key)));
        $hidden = [...self::BOOKKEEPING, OwnTableChanges::revisionColumn()];
        $labels = array_values(array_map(
            fn (string $field): string => $this->descriptions->fieldLabel($table, $field),
            array_filter($fields, fn (string $field): bool => ! in_array($field, $hidden, true)),
        ));

        return match ($reason) {
            'missing' => "{$subject} bestaat niet meer.",
            'exists' => "{$subject} bestaat intussen weer.",
            default => $labels === []
                ? "{$subject} is intussen gewijzigd."
                : "Bij {$subject} ".(count($labels) === 1 ? 'is ' : 'zijn ').$this->list($labels).' intussen gewijzigd.',
        };
    }

    /** @param  non-empty-list<string>  $items */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? $last : implode(', ', $items).' en '.$last;
    }
}
