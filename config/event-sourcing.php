<?php

declare(strict_types=1);

use App\Events\Invitations\InvitationAccepted;
use App\Events\Invitations\InvitationCreated;
use App\Events\Invitations\InvitationRevoked;
use Mortel\Models\UteqStoredEvent;
use Mortel\Projectors\AiActionStatisticsProjector;
use Mortel\Projectors\ChannelProjector;
use Mortel\Projectors\DocumentProjector;
use Mortel\Projectors\EntityLinkProjector;
use Mortel\Projectors\EntityProjector;
use Mortel\Projectors\ExperimentProjector;
use Mortel\Projectors\InboxAttentionProjector;
use Mortel\Projectors\InboxItemReclassifiedProjector;
use Mortel\Projectors\MetricProjector;
use Mortel\Projectors\PolicyProjector;
use Mortel\Projectors\RoleProjector;
use Mortel\Projectors\TranscriptProjector;
use Mortel\Projectors\WorkflowProjector;
use Mortel\Projectors\WorkflowRunProjector;
use Mortel\Projectors\WorkflowScheduleProjector;
use Mortel\Projectors\WorkflowStepWaitProjector;
use Mortel\Repositories\UteqStoredEventRepository;

return [
    'stored_event_model' => UteqStoredEvent::class,
    'stored_event_repository' => UteqStoredEventRepository::class,
    'queue' => env('EVENT_PROJECTOR_QUEUE_NAME', null),
    'catch_exceptions' => env('EVENT_PROJECTOR_CATCH_EXCEPTIONS', false),
    'aggregate_event_order_column' => 'aggregate_version',

    /*
    | Host projectors and reactors are discovered in app/ (app/Projectors, app/Reactors).
    | A host event belongs here under its storedEventType(), so a renamed class
    | does not break a replay: 'notes.note.created' => App\Events\Notes\NoteCreated::class.
    */
    'auto_discover_projectors_and_reactors' => [app_path()],
    'event_class_map' => [
        'invitations.invitation.created' => InvitationCreated::class,
        'invitations.invitation.revoked' => InvitationRevoked::class,
        'invitations.invitation.accepted' => InvitationAccepted::class,
    ],

    /*
    | The projectors and reactors of mortelos/framework. The framework does not
    | register them itself; without this list RoleCreated, PolicyCreated and the other
    | framework events are stored but never projected into their table.
    */
    'projectors' => [
        AiActionStatisticsProjector::class,
        ChannelProjector::class,
        DocumentProjector::class,
        EntityLinkProjector::class,
        EntityProjector::class,
        ExperimentProjector::class,
        InboxAttentionProjector::class,
        InboxItemReclassifiedProjector::class,
        MetricProjector::class,
        PolicyProjector::class,
        RoleProjector::class,
        TranscriptProjector::class,
        WorkflowProjector::class,
        WorkflowRunProjector::class,
        WorkflowScheduleProjector::class,
        WorkflowStepWaitProjector::class,
    ],
    /*
    | Mortel\Reactors\WorkflowReactor only belongs here once the host has the
    | workflows table: the reactor queries that table on every stored event.
    */
    'reactors' => [],
];
