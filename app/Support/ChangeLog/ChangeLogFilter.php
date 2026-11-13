<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The filter of the change log, shared by the page, the CSV export and the archive outlet.
 *
 * `who` is '' (everyone), 'people', 'user:<id>', 'agents', 'agent:<key>' or 'system'. `part` is a part key or ''.
 * `from` and `to` are whole days in the app's time zone; a day that does not parse is ignored.
 */
final readonly class ChangeLogFilter
{
    public function __construct(
        public string $who = '',
        public string $part = '',
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    public static function fromInput(mixed $who, mixed $part, mixed $from, mixed $to): self
    {
        return new self(
            who: self::who(is_string($who) ? trim($who) : ''),
            part: is_string($part) && preg_match('/^[a-z0-9._-]{1,64}$/', $part) === 1 ? $part : '',
            from: self::day($from)?->startOfDay(),
            to: self::day($to)?->endOfDay(),
        );
    }

    /** @return array{who?: string, part?: string, from?: string, to?: string} */
    public function query(): array
    {
        return array_filter([
            'who' => $this->who,
            'part' => $this->part,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function isEmpty(): bool
    {
        return $this->query() === [];
    }

    private static function who(string $who): string
    {
        return in_array($who, ['', 'people', 'agents', 'system'], true) || preg_match('/^(user|agent):[^\s]{1,200}$/', $who) === 1 ? $who : '';
    }

    private static function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, config()->string('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $value ? $day : null;
    }
}
