<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GlobalMiddlewareStackTest extends TestCase
{
    public function test_framework_defaults_stay_in_the_global_stack(): void
    {
        $global = $this->app->make(Kernel::class)->getGlobalMiddleware();

        foreach ([TrustProxies::class, InvokeDeferredCallbacks::class, PreventRequestsDuringMaintenance::class, ValidatePostSize::class] as $class) {
            $this->assertContains($class, $global);
        }
        $this->assertNotContains(TrimStrings::class, $global);
        $this->assertNotContains(ConvertEmptyStringsToNull::class, $global);
    }

    public function test_client_ip_comes_from_forwarded_header_only_for_trusted_proxies(): void
    {
        Route::get('/_test/client-ip', fn (Request $request) => ['ip' => $request->ip()]);
        $headers = ['X-Forwarded-For' => '203.0.113.5'];

        config(['trustedproxy.proxies' => null]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson('/_test/client-ip', $headers)
            ->assertJsonPath('ip', '10.0.0.1');

        config(['trustedproxy.proxies' => '10.0.0.1']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson('/_test/client-ip', $headers)
            ->assertJsonPath('ip', '203.0.113.5');
    }
}
