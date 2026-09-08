<?php

namespace Tests\Unit;

use Tests\TestCase;

class HttpRateLimitsConfigTest extends TestCase
{
    public function test_rate_limits_are_read_from_config_not_raw_env_in_provider(): void
    {
        $this->assertSame(120, config('http-rate-limits.guest_per_minute'));
        $this->assertSame(300, config('http-rate-limits.authenticated_per_minute'));
        $this->assertSame(30, config('http-rate-limits.upload_per_minute'));
        $this->assertSame(10, config('http-rate-limits.bulk_per_minute'));
        $this->assertSame(200, config('http-rate-limits.health_per_minute'));
        $this->assertSame(8, config('http-rate-limits.form_submit_per_minute'));

        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString("env('API_RATE_LIMIT_", $source);
        $this->assertStringContainsString("config('http-rate-limits.", $source);
    }
}
