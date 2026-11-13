<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use Illuminate\Database\UniqueConstraintViolationException;
use Mortel\Actions\ActionResult;
use Mortel\Support\Scalar;
use Spatie\EventSourcing\AggregateRoots\Exceptions\CouldNotPersistAggregate;
use Throwable;

/**
 * The Dutch message for an undo that did not go through. It says what changed in the meantime when the counter-Action
 * names it: the `conflicts` of RevertOwnTableChanges, or an OwnTableConflict thrown by an own-table write. Both come
 * from mortelos/framework 0.7.68 and later; they are recognised by shape and class name, so this also runs on an
 * older framework.
 */
final readonly class UndoFailureText
{
    public const string NOTHING_CHANGED = 'Er is niets teruggedraaid.';

    private const string OWN_TABLE_CONFLICT = 'Mortel\\OwnTables\\OwnTableConflict';

    public function __construct(private ChangeLogDescriptions $descriptions) {}

    public function forResult(ActionResult $result): string
    {
        $lines = [];

        foreach (Scalar::listOfArrays($result->data['conflicts'] ?? null) as $conflict) {
            $lines[] = $this->conflictLine(
                Scalar::string($conflict['table'] ?? ''),
                $this->keyText($conflict['key'] ?? null),
                Scalar::string($conflict['reason'] ?? ''),
                array_values(array_filter(is_array($conflict['fields'] ?? null) ? $conflict['fields'] : [], is_string(...))),
            );
        }

        if ($lines !== []) {
            return 'Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd. '.implode(' ', $lines).' '.self::NOTHING_CHANGED;
        }

        return 'Ongedaan maken lukte niet: '.rtrim($result->message, '.').'. '.self::NOTHING_CHANGED;
    }

    public function forException(Throwable $exception): ?string
    {
        if (is_a($exception, self::OWN_TABLE_CONFLICT)
            && preg_match('/^(\S+) (\S+) (changed in the meantime|does not exist|already exists)/', $exception->getMessage(), $match) === 1) {
            $reason = match ($match[3]) {
                'does not exist' => 'missing',
                'already exists' => 'exists',
                default => 'changed',
            };

            return 'Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd. '
                .$this->conflictLine($match[1], $this->keyText($match[2]), $reason, []).' '.self::NOTHING_CHANGED;
        }

        if ($exception instanceof CouldNotPersistAggregate || $exception instanceof UniqueConstraintViolationException) {
            return 'Iemand anders heeft deze wijziging net ongedaan gemaakt of aangepast. '.self::NOTHING_CHANGED;
        }

        return null;
    }

    /** @param  list<string>  $fields */
    private function conflictLine(string $table, string $key, string $reason, array $fields): string
    {
        $subject = trim($this->descriptions->tableLabel($table).' '.$key);
        $labels = array_map(fn (string $field): string => $this->descriptions->fieldLabel($table, $field), $fields);

        return match ($reason) {
            'missing' => "{$subject} bestaat niet meer.",
            'exists' => "{$subject} bestaat intussen weer.",
            default => $labels === []
                ? "{$subject} is intussen gewijzigd."
                : "Bij {$subject} ".(count($labels) === 1 ? 'is ' : 'zijn ').$this->list($labels).' intussen gewijzigd.',
        };
    }

    private function keyText(mixed $key): string
    {
        if (is_string($key)) {
            // OwnTableConflict::key(): "id=12" or "a=1,b=2"
            $key = array_column(array_map(fn (string $pair): array => explode('=', $pair, 2) + [1 => ''], explode(',', $key)), 1, 0);
        }

        return is_array($key) ? implode(', ', array_map(fn (mixed $value): string => Scalar::scalarString($value), $key)) : '';
    }

    /** @param  non-empty-list<string>  $items */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? $last : implode(', ', $items).' en '.$last;
    }
}
