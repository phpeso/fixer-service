<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\Fixer\AccessKeyType;
use Peso\Services\FixerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

// phpcs:disable Generic.Files.LineLength.TooLong
final class HistoricalRatesTest extends TestCase
{
    public function testRateFree(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.15553', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.84493', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('166.518785', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaid(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.15553', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.84493', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('166.518785', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateFreeWithBase(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'USD', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/USD on 2025-06-13', $response->exception->getMessage());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'EUR', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/EUR on 2025-06-13', $response->exception->getMessage());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'JPY', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/JPY on 2025-06-13', $response->exception->getMessage());

        self::assertCount(0, $http->getRequests()); // no requests
    }

    public function testRateFreeWithBaseButMarkedAsSubscription(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage(
            "HTTP error 200. Response is \"{\"success\":false,\"error\":{\"code\":105,\"type\":\"base_currency_access_restricted\"}}\n\"",
        );
        $service->send(new HistoricalExchangeRateRequest('CZK', 'USD', $date));
    }

    public function testRatePaidWithBase(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04651', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'EUR', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04025', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'JPY', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('6.702325', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateFreeWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.15553', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.84493', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // not included
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/JPY on 2025-06-13', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaidWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.15553', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.84493', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // not included
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/JPY on 2025-06-13', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaidWithBaseWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04651', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'EUR', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04025', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // not included
        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'JPY', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/JPY on 2025-06-13', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testExponentRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2025-06-13');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'BTC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.000010952731', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());
    }

    public function testFutureDate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $date = Calendar::parse('2035-01-01');

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/JPY on 2035-01-01', $response->exception->getMessage());
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new HistoricalExchangeRateRequest('XBT', 'BTC', Calendar::parse('2025-06-13')));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for XBT/BTC on 2025-06-13', $response->exception->getMessage());
    }
}
