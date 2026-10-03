<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TiborSrc\XaiSdkPhp\SpaceXAI;
use UnexpectedValueException;

final class XaiSdkLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/xai.php', 'xai');

        $this->app->singleton(SpaceXAI::class, static function (Application $app): SpaceXAI {
            return new SpaceXAI(self::clientOptions(self::config($app)));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__).'/config/xai.php' => config_path('xai.php'),
            ], 'xai-config');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(Application $app): array
    {
        $config = $app->make(Repository::class)->get('xai', []);

        if (! is_array($config)) {
            throw new UnexpectedValueException('The [xai] config must be an array.');
        }

        $options = [];

        foreach ($config as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('The [xai] config keys must be strings.');
            }

            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function clientOptions(array $config): array
    {
        $options = [];

        $apiKey = $config['api_key'] ?? null;

        if (is_string($apiKey) && $apiKey !== '') {
            $options['apiKey'] = $apiKey;
        }

        $baseUrl = $config['base_url'] ?? null;

        if (is_string($baseUrl) && $baseUrl !== '') {
            $options['baseURL'] = $baseUrl;
        }

        foreach ([
            'timeout' => 'timeout',
            'idle_timeout' => 'idleTimeout',
            'max_response_body_bytes' => 'maxResponseBodyBytes',
            'max_retries' => 'maxRetries',
        ] as $configKey => $clientKey) {
            $value = self::integerOption($config[$configKey] ?? null);

            if ($value !== null) {
                $options[$clientKey] = $value;
            }
        }

        $retry = self::booleanOption($config['retry_before_output'] ?? null);

        if ($retry !== null) {
            $options['retryBeforeOutput'] = $retry;
        }

        $headers = $config['default_headers'] ?? null;

        if (is_array($headers) && $headers !== []) {
            $options['defaultHeaders'] = $headers;
        }

        foreach (['fetch', 'onRequest', 'onResponse'] as $callback) {
            $value = $config[$callback] ?? null;

            if ($value instanceof Closure) {
                $options[$callback] = $value;
            }
        }

        return $options;
    }

    private static function integerOption(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function booleanOption(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ((! is_string($value) && ! is_int($value)) || $value === '') {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return is_bool($parsed) ? $parsed : null;
    }
}
