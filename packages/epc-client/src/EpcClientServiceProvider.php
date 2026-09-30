<?php

declare(strict_types=1);

namespace InstallHub\EpcClient;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use InstallHub\EpcClient\Contracts\EpcRegister;
use InstallHub\EpcClient\Contracts\PostcodeLookup;
use InstallHub\EpcClient\Http\EpcConfig;
use InstallHub\EpcClient\Http\HttpEpcRegister;
use InstallHub\EpcClient\Http\HttpPostcodeLookup;
use InstallHub\EpcClient\Http\PostcodesConfig;
use InstallHub\EpcClient\Http\ResilientTransport;
use InstallHub\EpcClient\Http\RetryPolicy;

final class EpcClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/epc-client.php', 'epc-client');

        $this->app->singleton(RetryPolicy::class, fn (Application $app): RetryPolicy => new RetryPolicy(
            maxAttempts: $this->int($app, 'retry.max_attempts', 3),
            baseDelayMs: $this->int($app, 'retry.base_delay_ms', 200),
            maxDelayMs: $this->int($app, 'retry.max_delay_ms', 5000),
            maxRetryAfterSeconds: $this->int($app, 'retry.max_retry_after_seconds', 10),
        ));

        $this->app->singleton(EpcConfig::class, fn (Application $app): EpcConfig => new EpcConfig(
            baseUrl: $this->string($app, 'epc.base_url'),
            email: $this->nullableString($app, 'epc.email'),
            apiKey: $this->nullableString($app, 'epc.key'),
            timeout: $this->float($app, 'epc.timeout', 10.0),
            connectTimeout: $this->float($app, 'connect_timeout', 3.0),
        ));

        $this->app->singleton(PostcodesConfig::class, fn (Application $app): PostcodesConfig => new PostcodesConfig(
            baseUrl: $this->string($app, 'postcodes.base_url'),
            timeout: $this->float($app, 'postcodes.timeout', 5.0),
            connectTimeout: $this->float($app, 'connect_timeout', 3.0),
        ));

        // Built from the container's HTTP factory, so Http::fake() in the host
        // application's tests intercepts these clients too.
        $this->app->bind(EpcRegister::class, fn (Application $app): EpcRegister => new HttpEpcRegister(
            $app->make(Factory::class),
            $app->make(EpcConfig::class),
            new ResilientTransport($app->make(RetryPolicy::class)),
        ));

        $this->app->bind(PostcodeLookup::class, fn (Application $app): PostcodeLookup => new HttpPostcodeLookup(
            $app->make(Factory::class),
            $app->make(PostcodesConfig::class),
            new ResilientTransport($app->make(RetryPolicy::class)),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/epc-client.php' => $this->app->configPath('epc-client.php'),
            ], 'epc-client-config');
        }
    }

    private function config(Application $app): Repository
    {
        return $app->make(Repository::class);
    }

    private function string(Application $app, string $key): string
    {
        $value = $this->config($app)->get("epc-client.{$key}");

        return is_string($value) ? $value : '';
    }

    private function nullableString(Application $app, string $key): ?string
    {
        $value = $this->config($app)->get("epc-client.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function int(Application $app, string $key, int $default): int
    {
        $value = $this->config($app)->get("epc-client.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }

    private function float(Application $app, string $key, float $default): float
    {
        $value = $this->config($app)->get("epc-client.{$key}");

        return is_numeric($value) ? (float) $value : $default;
    }
}
