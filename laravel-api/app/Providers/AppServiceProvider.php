<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ApStatusService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\DeviceActionService;
use App\Services\DeviceService;
use App\Services\FactoryLayoutService;
use App\Services\IamClient;
use App\Services\JwtService;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider
 *
 * Registers all application-level service bindings into the Laravel IoC container.
 * Each service is bound as a singleton so the same instance is reused per request.
 */
class AppServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    public function register(): void
    {
        // JwtService — depends only on the shared secret from config
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            secret: (string) config('ems.jwt_secret'),
        ));

        // IamClient — HTTP transport for the external IAM service
        $this->app->singleton(IamClient::class, fn () => new IamClient());

        // AuthService — depends on IamClient + JwtService (auto-resolved)
        $this->app->singleton(AuthService::class, fn ($app) => new AuthService(
            iam: $app->make(IamClient::class),
            jwt: $app->make(JwtService::class),
        ));

        // ApStatusService — no dependencies; uses ApLog model internally
        $this->app->singleton(ApStatusService::class, fn () => new ApStatusService());

        // FactoryLayoutService — no dependencies; uses FactoryLayout model internally
        $this->app->singleton(FactoryLayoutService::class, fn () => new FactoryLayoutService());

        // AuditService — cross-cutting concern; injected into any mutating service
        $this->app->singleton(AuditService::class, fn () => new AuditService());

        // DeviceService — now requires AuditService for CRUD mutations
        $this->app->singleton(DeviceService::class, fn (\Illuminate\Contracts\Foundation\Application $app)
            => new DeviceService($app->make(AuditService::class)));

        // DeviceActionService — requires AuditService for workflow mutations
        $this->app->singleton(DeviceActionService::class, fn (\Illuminate\Contracts\Foundation\Application $app)
            => new DeviceActionService($app->make(AuditService::class)));
    }

    /*
    |--------------------------------------------------------------------------
    | BOOT
    |--------------------------------------------------------------------------
    */

    public function boot(): void
    {
        //
    }
}
