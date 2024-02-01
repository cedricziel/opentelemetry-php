<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Unit\SDK;

use AssertWell\PHPUnitGlobalState\EnvironmentVariables;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\LoggerHolder;
use OpenTelemetry\API\Logs\NoopLoggerProvider;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\SdkAutoloader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \OpenTelemetry\SDK\SdkAutoloader
 */
class SdkAutoloaderTest extends TestCase
{
    use EnvironmentVariables;

    public function setUp(): void
    {
        LoggerHolder::set(new NullLogger());
        Globals::reset();
        SdkAutoloader::reset();
    }

    public function tearDown(): void
    {
        $this->restoreEnvironmentVariables();
    }

    public function test_disabled_by_default(): void
    {
        $this->assertFalse(SdkAutoloader::autoload());
        $this->assertInstanceOf(NoopMeterProvider::class, Globals::meterProvider());
        $this->assertInstanceOf(NoopTracerProvider::class, Globals::tracerProvider());
        $this->assertInstanceOf(NoopLoggerProvider::class, Globals::loggerProvider());
        $this->assertInstanceOf(NoopTextMapPropagator::class, Globals::propagator(), 'propagator not initialized by disabled autoloader');
    }

    public function test_disabled_with_invalid_flag(): void
    {
        $this->setEnvironmentVariable(Variables::OTEL_PHP_AUTOLOAD_ENABLED, 'invalid-value');
        $this->assertFalse(SdkAutoloader::autoload());
    }

    public function test_sdk_disabled_does_not_disable_propagator(): void
    {
        $this->setEnvironmentVariable(Variables::OTEL_PHP_AUTOLOAD_ENABLED, 'true');
        $this->setEnvironmentVariable(Variables::OTEL_SDK_DISABLED, 'true');
        SdkAutoloader::autoload();
        $this->assertNotInstanceOf(NoopTextMapPropagator::class, Globals::propagator());
        $this->assertInstanceOf(NoopMeterProvider::class, Globals::meterProvider());
        $this->assertInstanceOf(NoopTracerProvider::class, Globals::tracerProvider());
    }

    public function test_enabled_by_configuration(): void
    {
        $this->setEnvironmentVariable(Variables::OTEL_PHP_AUTOLOAD_ENABLED, 'true');
        SdkAutoloader::autoload();
        $this->assertNotInstanceOf(NoopTextMapPropagator::class, Globals::propagator());
        $this->assertNotInstanceOf(NoopMeterProvider::class, Globals::meterProvider());
        $this->assertNotInstanceOf(NoopTracerProvider::class, Globals::tracerProvider());
        $this->assertNotInstanceOf(NoopLoggerProvider::class, Globals::loggerProvider());
    }

    public function test_ignore_urls_without_request_uri(): void
    {
        $this->setEnvironmentVariable(Variables::OTEL_PHP_AUTOLOAD_ENABLED, 'true');
        $this->setEnvironmentVariable(Variables::OTEL_PHP_EXCLUDED_URLS, '*');
        unset($_SERVER['REQUEST_URI']);
        $this->assertFalse(SdkAutoloader::isIgnoredUrl());
    }

    /**
     * @dataProvider ignoreUrlsProvider
     */
    public function test_ignore_urls(string $ignore, string $uri, bool $expected): void
    {
        $this->setEnvironmentVariable(Variables::OTEL_PHP_AUTOLOAD_ENABLED, 'true');
        $this->setEnvironmentVariable(Variables::OTEL_PHP_EXCLUDED_URLS, $ignore);
        $_SERVER['REQUEST_URI'] = $uri;
        $this->assertSame($expected, SdkAutoloader::isIgnoredUrl());
    }

    public static function ignoreUrlsProvider(): array
    {
        return [
            [
                'foo',
                '/foo?bar=baz',
                true,
            ],
            [
                'foo',
                '/bar',
                false,
            ],
            [
                'foo,bar',
                'https://example.com/bar?p1=2',
                true,
            ],
            [
                'foo,bar',
                'https://example.com/baz?p1=2',
                false,
            ],
            [
                'client/.*/info,healthcheck',
                'https://site/client/123/info',
                true,
            ],
            [
                'client/.*/info,healthcheck',
                'https://site/xyz/healthcheck',
                true,
            ],
        ];
    }
}
