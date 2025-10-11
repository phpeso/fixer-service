<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Types\Decimal;
use Peso\Services\Fixer\AccessKeyType;
use Peso\Services\FixerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class ConversionTest extends TestCase
{
    public function testSubscriptionOnly(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(new Decimal('1000'), 'PHP', 'EUR'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals(
            'Unsupported request type: "Peso\Core\Requests\CurrentConversionRequest"',
            $response->exception->getMessage(),
        );
    }

    public function testSubscriptionOnlyFreeKey(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        // wrong key - expect exception
        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage(
            '"Access Restricted - Your current Subscription Plan does not support this API Function."',
        );

        $service->send(new CurrentConversionRequest(new Decimal('1000'), 'PHP', 'EUR'));
    }

    public function testCurrentConversion(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(new Decimal('1000'), 'PHP', 'EUR'));

        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('15.093', $response->amount->value);
        self::assertEquals('2025-06-30', $response->date->toString());
    }

    public function testHistoricalConversion(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalConversionRequest(
            new Decimal('1000'),
            'PHP',
            'EUR',
            Calendar::parse('2025-06-13'),
        ));

        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('15.429', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());
    }

    public function testFuture(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalConversionRequest(
            new Decimal('1000'),
            'PHP',
            'EUR',
            Calendar::parse('2035-01-01'),
        ));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1000 PHP to EUR on 2035-01-01', $response->exception->getMessage());
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        // invalid from

        $response = $service->send(new CurrentConversionRequest(new Decimal('1000'), 'XBT', 'EUR'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1000 XBT to EUR', $response->exception->getMessage());

        // invalid to

        $response = $service->send(new CurrentConversionRequest(new Decimal('1000'), 'PHP', 'XBT'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1000 PHP to XBT', $response->exception->getMessage());
    }
}
