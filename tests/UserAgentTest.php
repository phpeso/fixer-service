<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Composer\InstalledVersions;
use Http\Discovery\Psr17Factory;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Services\Fixer\AccessKeyType;
use Peso\Services\FixerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class UserAgentTest extends TestCase
{
    public function testUserAgent(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FixerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $pesoVersion = InstalledVersions::getPrettyVersion('peso/core');
        $clientVersion = InstalledVersions::getPrettyVersion('peso/fixer-service');

        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));

        $request = $http->getLastRequest();

        self::assertEquals("Peso/$pesoVersion FixerClient/$clientVersion", $request->getHeaderLine('User-Agent'));
    }

    public function testCustomUserAgent(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $requestFactory = new class implements RequestFactoryInterface
        {
            private RequestFactoryInterface $factory;

            public function __construct()
            {
                $this->factory = new Psr17Factory();
            }

            public function createRequest(string $method, mixed $uri): RequestInterface
            {
                return $this->factory->createRequest($method, $uri)->withHeader(
                    'user-agent',
                    'CustomSuffix/1.0'
                );
            }
        };

        $service = new FixerService(
            accessKey: 'xxxfreexxx',
            accessKeyType: AccessKeyType::Free,
            cache: $cache,
            httpClient: $http,
            requestFactory: $requestFactory
        );

        $pesoVersion = InstalledVersions::getPrettyVersion('peso/core');
        $clientVersion = InstalledVersions::getPrettyVersion('peso/fixer-service');

        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));

        $request = $http->getLastRequest();

        self::assertEquals(
            "Peso/$pesoVersion FixerClient/$clientVersion CustomSuffix/1.0",
            $request->getHeaderLine('User-Agent')
        );
    }
}
