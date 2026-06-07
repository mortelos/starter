<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;

final class Tenant extends StanclTenant
{
    protected $table = 'tenants';
}
