<?php

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

final readonly class MockClient
{
    public static function get(): Client
    {
        $client = new Client();

        $client->on(
            new RequestMatcher('/api/latest', 'data.fixer.io', ['GET'], ['https']),
            function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($request->getUri()->getQuery()) {
                    case 'access_key=xxxfreexxx&base=EUR':
                    case 'access_key=xxxpaidxxx&base=EUR':
                        return new Response(200, body: fopen(__DIR__ . '/../data/latest.json', 'r'));

                    case 'access_key=xxxfreexxx&base=CZK':
                    case 'access_key=xxxfreexxx&base=CZK&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/latest-czk-free.json', 'r'));

                    case 'access_key=xxxpaidxxx&base=CZK':
                        return new Response(200, body: fopen(__DIR__ . '/../data/latest-czk.json', 'r'));

                    case 'access_key=xxxfreexxx&base=EUR&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                    case 'access_key=xxxpaidxxx&base=EUR&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/latest-symbols.json', 'r'));

                    case 'access_key=xxxpaidxxx&base=CZK&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/latest-czk-symbols.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            }
        );
        $client->on(
            new RequestMatcher('/api/2025-06-13', 'data.fixer.io', ['GET'], ['https']),
            function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($request->getUri()->getQuery()) {
                    case 'access_key=xxxfreexxx&base=EUR':
                    case 'access_key=xxxpaidxxx&base=EUR':
                        return new Response(200, body: fopen(__DIR__ . '/../data/2025-06-13.json', 'r'));

                    case 'access_key=xxxfreexxx&base=CZK':
                    case 'access_key=xxxfreexxx&base=CZK&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/2025-06-13-czk-free.json', 'r'));

                    case 'access_key=xxxpaidxxx&base=CZK':
                        return new Response(200, body: fopen(__DIR__ . '/../data/2025-06-13-czk.json', 'r'));

                    case 'access_key=xxxfreexxx&base=EUR&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                    case 'access_key=xxxpaidxxx&base=EUR&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/2025-06-13-symbols.json', 'r'));

                    case 'access_key=xxxpaidxxx&base=CZK&symbols=GBP%2CCZK%2CRUB%2CEUR%2CZAR%2CUSD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/2025-06-13-czk-symbols.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            }
        );
        $client->on(
            new RequestMatcher('/api/2035-01-01', 'data.fixer.io', ['GET'], ['https']),
            function () {
                return new Response(200, body: fopen(__DIR__ . '/../data/2035-01-01.json', 'r'));
            }
        );

        return $client;
    }
}
