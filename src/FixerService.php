<?php

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Date\Calendar;
use DateInterval;
use Override;
use Peso\Core\Exceptions\ConversionRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Core\Services\ExchangeRateServiceInterface;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Peso\Services\Fixer\AccessKeyType;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class FixerService implements ExchangeRateServiceInterface
{
    private const ENDPOINT_LATEST = 'https://data.fixer.io/api/latest?%s';
    private const ENDPOINT_HISTORICAL = 'https://data.fixer.io/api/%s?%s';

    public function __construct(
        private string $accessKey,
        private AccessKeyType $accessKeyType,
        private array|null $symbols = null,
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(object $request): SuccessResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return self::performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return self::performHistoricalRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performCurrentRequest(CurrentExchangeRateRequest $request): ErrorResponse|SuccessResponse
    {
        if ($this->accessKeyType === AccessKeyType::Free && $request->baseCurrency !== 'EUR') {
            return new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
        }

        $query = [
            'access_key' => $this->accessKey,
            'base' => $request->baseCurrency,
            'symbols' => $this->symbols === null ? null : implode(',', $this->symbols),
        ];

        $url = \sprintf(self::ENDPOINT_LATEST, http_build_query($query, encoding_type: PHP_QUERY_RFC3986));

        $rateData = $this->retrieveRates($url);

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new SuccessResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::parse($rateData['date'])
            ) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function performHistoricalRequest(HistoricalExchangeRateRequest $request): ErrorResponse|SuccessResponse
    {
        if ($this->accessKeyType === AccessKeyType::Free && $request->baseCurrency !== 'EUR') {
            return new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
        }

        $query = [
            'access_key' => $this->accessKey,
            'base' => $request->baseCurrency,
            'symbols' => $this->symbols === null ? null : implode(',', $this->symbols),
        ];

        $url = \sprintf(
            self::ENDPOINT_HISTORICAL,
            $request->date->toString(),
            http_build_query($query, encoding_type: PHP_QUERY_RFC3986)
        );

        $rateData = $this->retrieveRates($url);

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new SuccessResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::parse($rateData['date'])
            ) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function retrieveRates(string $url): array|false
    {
        $cacheKey = hash('sha1', $url);

        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
            'FixerClient',
            'peso/fixer-service',
            $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
        ));
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw HttpFailureException::fromResponse($request, $response);
        }

        $data = json_decode(
            (string)$response->getBody(),
            flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY
        );

        if ($data['success'] === false) {
            if ($data['error']['code'] === 302) {
                return ['date' => '0000-00-00', 'rates' => []]; // invalid date range
            }
            throw HttpFailureException::fromResponse($request, $response);
        }

        $this->cache->set($cacheKey, $data, $this->ttl);

        return $data;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        if (!$request instanceof CurrentExchangeRateRequest && !$request instanceof HistoricalExchangeRateRequest) {
            return false;
        }

        if ($this->accessKeyType === AccessKeyType::Free && $request->baseCurrency !== 'EUR') {
            return false;
        }

        return true;
    }
}
