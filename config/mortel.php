<?php

declare(strict_types=1);

use App\Models\User;

/*
| Only the keys the starter sets; mortelos/framework merges in the rest of
| its own config/mortel.php. That merge is shallow: a top-level key set here
| replaces the package's whole key, so copy the package's sub-keys along
| when you add one. Single-tenant: no tenant model.
*/

return [

    'models' => [
        'tenant' => null,
        'user' => User::class,
    ],

    'workflows' => [
        // Workflow schedules use this timezone, or the application timezone when empty.
        'timezone' => env('MORTEL_WORKFLOWS_TIMEZONE'),
        // A step that still fails after its retries fails the run (framework 0.7.107+).
        'after_retries' => env('MORTEL_WORKFLOWS_AFTER_RETRIES', 'abort'),
        // The run-due tick restarts a lost next-step job after 15 minutes, at most
        // three times, then only logs it (framework 0.7.109+).
        'recover_stalled_runs' => env('MORTEL_WORKFLOWS_RECOVER_STALLED_RUNS', true),
    ],

    'events' => [
        // Host stored events validate against the schema file next to their class,
        // for example app/Events/Invitations/invitation.created.v1.schema.json.
        'schema_namespaces' => [
            'App\\Events\\' => app_path('Events'),
        ],
    ],

];
