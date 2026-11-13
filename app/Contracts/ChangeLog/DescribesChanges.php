<?php

declare(strict_types=1);

namespace App\Contracts\ChangeLog;

use App\Support\ChangeLog\ChangeLogDescriptions;

/**
 * A class that adds parts and sentences to the change log. Tag it in a service provider:
 *
 *     $this->app->tag([AppChangeDescriptions::class], ChangeLogDescriptions::TAG);
 *
 * Every ChangeLogDescriptions is built from the tagged classes, so the registry holds no state between requests.
 */
interface DescribesChanges
{
    public function register(ChangeLogDescriptions $descriptions): void;
}
