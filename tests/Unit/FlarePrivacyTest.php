<?php

namespace Tests\Unit;

use Spatie\FlareClient\Enums\CollectType;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Tests\TestCase;

class FlarePrivacyTest extends TestCase
{
    public function test_error_reports_exclude_customer_context(): void
    {
        $this->assertSame([
            CollectType::ErrorsWithTraces->value,
            CollectType::GitInfo->value,
            LaravelCollectType::HandledExceptions->value,
        ], array_keys(config('flare.collects')));

        $this->assertTrue(config('flare.censor.client_ips'));
        $this->assertTrue(config('flare.censor.cookies'));
        $this->assertTrue(config('flare.censor.session'));
    }
}
