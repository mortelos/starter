<?php

declare(strict_types=1);

namespace Tests\Support\ChangeLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mortel\Contracts\ProvidesStoredEventMetadata;
use Mortel\OwnTables\AggregateUuid;
use Mortel\OwnTables\ChangeSet;
use Mortel\OwnTables\OwnTableChanges;
use Mortel\OwnTables\OwnTableProjector;
use Mortel\OwnTables\RecordedChanges;
use Mortel\OwnTables\RowChangesEvent;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Projectionist;

/*
 * A real own table for the change log tests (docs/own-tables.md of mortelos/framework): a model with a revision column,
 * an aggregate per row, one event that carries the row before and after, and the framework's OwnTableProjector that
 * writes it. Undo goes through the framework's RevertOwnTableChanges.
 */

/**
 * @property int $id
 * @property string $name
 * @property string $colour
 * @property int $revision
 */
final class Thing extends Model
{
    public const string TABLE = 'changelog_things';

    protected $table = self::TABLE;

    protected $fillable = ['name', 'colour'];

    public static function createTable(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('colour');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });

        app(Projectionist::class)->addProjector(OwnTableProjector::class);
    }

    /** The action of the tests: write the row (create or update) as one ThingWritten event on its own aggregate. */
    public static function write(self $thing, string $summary, Actor $actor): RecordedChanges
    {
        return app(EventContext::class)->within($actor, $summary, function () use ($thing, $summary): RecordedChanges {
            $recorder = app(OwnTableChanges::class);

            if (! $thing->exists) {
                $recorder->reserveId($thing);
            }

            return $recorder->record(
                ThingAggregate::retrieve(AggregateUuid::for('changelog_thing', $thing->id)),
                new ThingWritten($summary),
                ChangeSet::make()->save($thing),
            );
        });
    }
}

final class ThingAggregate extends AggregateRoot {}

final class ThingWritten extends RowChangesEvent implements ProvidesStoredEventMetadata
{
    public function __construct(public readonly string $summary) {}

    public function storedEventType(): string
    {
        return 'things.thing.written';
    }

    public function storedAggregateType(): string
    {
        return 'changelog_thing';
    }
}
