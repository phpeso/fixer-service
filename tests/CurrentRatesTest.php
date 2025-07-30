<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
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
final class CurrentRatesTest extends TestCase
{
    public function testRateFree(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.151477', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.812829', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('167.944632', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaid(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.151477', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.812829', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('167.944632', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateFreeWithBase(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/USD', $response->exception->getMessage());

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'EUR'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/EUR', $response->exception->getMessage());

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'JPY'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/JPY', $response->exception->getMessage());

        self::assertCount(0, $http->getRequests()); // no requests
    }

    public function testRateFreeWithBaseButMarkedAsSubscription(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage(
            "HTTP error 400. Response is \"{\"success\":false,\"error\":{\"code\":105,\"type\":\"base_currency_access_restricted\"}}\n\"",
        );
        $service->send(new CurrentExchangeRateRequest('CZK', 'USD'));
    }

    public function testRatePaidWithBase(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.046407', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.040302', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'JPY'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('6.76846', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateFreeWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.151477', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.812829', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        // not included
        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/JPY', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaidWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.151477', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.812829', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        // not included
        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/JPY', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRatePaidWithBaseWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, symbols: [
            'GBP', 'CZK', 'RUB', 'EUR', 'ZAR', 'USD',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.046407', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.040302', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        // not included
        $response = $service->send(new CurrentExchangeRateRequest('CZK', 'JPY'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for CZK/JPY', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testExponentRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'BTC'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.000010912818', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('XBT', 'BTC'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for XBT/BTC', $response->exception->getMessage());
    }
}
