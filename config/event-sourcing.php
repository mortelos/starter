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
];
