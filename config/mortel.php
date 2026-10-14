<?php

declare(strict_types=1);

use App\Models\User;

/*
| Only the keys the starter sets; mortelos/framework merges in the rest of
| its own config/mortel.php. Single-tenant: no tenant model.
*/

return [

    'models' => [
        'tenant' => null,
        'user' => User::class,
    ],

];
