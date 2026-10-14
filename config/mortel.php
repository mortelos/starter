<?php

declare(strict_types=1);

/*
| Host overrides for mortelos/framework. Every other key comes from the
| package's own config/mortel.php through mergeConfigFrom. That merge is
| shallow: a top-level key set here replaces the package's whole key, so
| copy the package's sub-keys along when you add one.
*/

return [

    'events' => [
        // Host stored events validate against the schema file next to their class,
        // for example app/Events/Invitations/invitation.created.v1.schema.json.
        'schema_namespaces' => [
            'App\\Events\\' => app_path('Events'),
        ],
    ],

];
