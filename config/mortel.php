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

    'events' => [
        // Host stored events validate against the schema file next to their class,
        // for example app/Events/Invitations/invitation.created.v1.schema.json.
        'schema_namespaces' => [
            'App\\Events\\' => app_path('Events'),
        ],
    ],

];
