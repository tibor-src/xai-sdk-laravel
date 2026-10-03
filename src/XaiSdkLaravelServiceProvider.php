<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TiborSrc\XaiSdkPhp\SpaceXAI;

class XaiSdkLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/xai.php', 'xai');

        $this->app->singleton(SpaceXAI::class, function (Application $app): SpaceXAI {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('xai', []);

            return new SpaceXAI(self::clientOptions(is_array($config) ? $config : []));
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

        $integers = [
            'timeout' => 'timeout',
            'idle_timeout' => 'idleTimeout',
            'max_response_body_bytes' => 'maxResponseBodyBytes',
            'max_retries' => 'maxRetries',
        ];
        foreach ($integers as $configKey => $clientKey) {
            $value = self::integerOption($config[$configKey] ?? null);
            if ($value !== null) {
                $options[$clientKey] = $value;
            }
        }

        if (array_key_exists('retry_before_output', $config)) {
            $retry = self::booleanOption($config['retry_before_output']);
            if ($retry !== null) {
                $options['retryBeforeOutput'] = $retry;
            }
        }

        $headers = $config['default_headers'] ?? null;
        if (is_array($headers) && $headers !== []) {
            $options['defaultHeaders'] = $headers;
        }

        foreach (['fetch', 'onRequest', 'onResponse'] as $callback) {
            if (($config[$callback] ?? null) instanceof \Closure) {
                $options[$callback] = $config[$callback];
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
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
