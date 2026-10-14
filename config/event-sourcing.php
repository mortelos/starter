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
    | Host-projectors en -reactors worden gevonden in app/ (app/Projectors, app/Reactors).
    | Een host-event hoort hier onder zijn storedEventType(), zodat een hernoemde klasse
    | een replay niet breekt: 'notes.note.created' => App\Events\Notes\NoteCreated::class.
    */
    'auto_discover_projectors_and_reactors' => [app_path()],
    'event_class_map' => [
        'invitations.invitation.created' => InvitationCreated::class,
        'invitations.invitation.revoked' => InvitationRevoked::class,
        'invitations.invitation.accepted' => InvitationAccepted::class,
    ],

    /*
    | De projectors en reactors van mortelos/framework. Het framework meldt ze niet
    | zelf aan; zonder deze lijst worden RoleCreated, PolicyCreated en de andere
    | framework-events wel opgeslagen maar nooit in hun tabel geprojecteerd.
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
    | Mortel\Reactors\WorkflowReactor hoort hier pas bij als de host de tabel
    | workflows heeft: de reactor bevraagt die tabel bij elk opgeslagen event.
    */
    'reactors' => [],
];
