<?php

declare(strict_types=1);

use Mortel\Models\UteqStoredEvent;
use Mortel\Repositories\UteqStoredEventRepository;

return [
    'stored_event_model' => UteqStoredEvent::class,
    'stored_event_repository' => UteqStoredEventRepository::class,
    'queue' => env('EVENT_PROJECTOR_QUEUE_NAME', null),
    'catch_exceptions' => env('EVENT_PROJECTOR_CATCH_EXCEPTIONS', false),
    'aggregate_event_order_column' => 'aggregate_version',

    /*
    | Host-projectors en -reactors worden gevonden in app/ (app/Projectors, app/Reactors).
    | Een host-event hoort hier onder zijn storedEventType(), zodat een hernoemde klasse
    | een replay niet breekt: 'notes.note.created' => App\Events\Notes\NoteCreated::class.
    */
    'auto_discover_projectors_and_reactors' => [app_path()],
    'event_class_map' => [],

    /*
    | De projectors en reactors van mortelos/framework. Het framework meldt ze niet
    | zelf aan; zonder deze lijst worden RoleCreated, PolicyCreated en de andere
    | framework-events wel opgeslagen maar nooit in hun tabel geprojecteerd.
    */
    'projectors' => [
        Mortel\Projectors\AiActionStatisticsProjector::class,
        Mortel\Projectors\ChannelProjector::class,
        Mortel\Projectors\DocumentProjector::class,
        Mortel\Projectors\EntityLinkProjector::class,
        Mortel\Projectors\EntityProjector::class,
        Mortel\Projectors\ExperimentProjector::class,
        Mortel\Projectors\InboxAttentionProjector::class,
        Mortel\Projectors\InboxItemReclassifiedProjector::class,
        Mortel\Projectors\MetricProjector::class,
        Mortel\Projectors\PolicyProjector::class,
        Mortel\Projectors\RoleProjector::class,
        Mortel\Projectors\TranscriptProjector::class,
        Mortel\Projectors\WorkflowProjector::class,
        Mortel\Projectors\WorkflowRunProjector::class,
        Mortel\Projectors\WorkflowScheduleProjector::class,
        Mortel\Projectors\WorkflowStepWaitProjector::class,
    ],
    'reactors' => [
        Mortel\Reactors\WorkflowReactor::class,
    ],
];
