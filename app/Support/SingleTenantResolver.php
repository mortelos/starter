<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Mortel\Contracts\TenantResolver;

final class SingleTenantResolver implements TenantResolver
{
    public function id(): string
    {
        $tenantId = config('starter.tenancy.default_tenant_id', 'default');

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : 'default';
    }

    public function initialized(): bool
    {
        // Single-tenant: a default tenant id always resolves.
        return true;
    }

    public function data(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('tenants')) {
            return $default;
        }

        $tenant = Tenant::query()->find($this->id());
        $data = $tenant?->getAttribute('data');

        if (! is_array($data) || ! array_key_exists($key, $data)) {
            return $default;
        }

        return $data[$key];
    }
}
