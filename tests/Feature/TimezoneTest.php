<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_time_is_server_controlled_as_asia_tehran(): void
    {
        $this->assertSame('Asia/Tehran', config('app.timezone'));
        $this->assertSame('Asia/Tehran', date_default_timezone_get());
        $this->assertSame('+03:30', CarbonImmutable::now()->format('P'));
    }
}
