<?php

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Date\Calendar;
use DateInterval;
use Override;
use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Exceptions\ConversionRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
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
    private const ENDPOINT_CONVERSION = 'https://data.fixer.io/api/convert?%s';

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
    public function send(object $request): ExchangeRateResponse|ConversionResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return self::performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return self::performHistoricalRequest($request);
        }
        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            return self::performConversionRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performCurrentRequest(CurrentExchangeRateRequest $request): ErrorResponse|ExchangeRateResponse
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

        $rateData = $this->retrieveResponse($url);

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new ExchangeRateResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::parse($rateData['date']),
            ) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function performHistoricalRequest(
        HistoricalExchangeRateRequest $request,
    ): ErrorResponse|ExchangeRateResponse {
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
            http_build_query($query, encoding_type: PHP_QUERY_RFC3986),
        );

        $rateData = $this->retrieveResponse($url);
        if ($rateData['success'] === false) {
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
        }

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new ExchangeRateResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::parse($rateData['date']),
            ) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function retrieveResponse(string $url): array|false
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

        /**
         * @var array{success: true, rates: array, date: string}|array{success: false, error: array{code: int}} $data
         */
        $data = json_decode(
            (string)$response->getBody(),
            flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY,
        );

        if ($data['success'] === false) {
            if (
                !\in_array($data['error']['code'], [
                    106, // conversion - future (no rates)
                    201, // invalid base currency
                    302, // no rates (future)
                    402, // invalid conversion currency
                ])
            ) {
                throw HttpFailureException::fromResponse($request, $response);
            }
        }

        $this->cache->set($cacheKey, $data, $this->ttl);

        return $data;
    }

    public function performConversionRequest(
        CurrentConversionRequest|HistoricalConversionRequest $request,
    ): ErrorResponse|ConversionResponse {
        if ($this->accessKeyType !== AccessKeyType::Subscription) {
            return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
        }

        $query = [
            'access_key' => $this->accessKey,
            'from' => $request->baseCurrency,
            'to' => $request->quoteCurrency,
            'amount' => $request->baseAmount,
        ];

        if ($request instanceof HistoricalConversionRequest) {
            $query['date'] = $request->date->toString();
        }

        $url = \sprintf(
            self::ENDPOINT_CONVERSION,
            http_build_query($query, encoding_type: PHP_QUERY_RFC3986),
        );

        $convData = $this->retrieveResponse($url);

        if ($convData['success'] === false) {
            return new ErrorResponse(ConversionNotPerformedException::fromRequest($request));
        }

        return new ConversionResponse(Decimal::init($convData['result']), Calendar::parse($convData['date']));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            // conversion is available only on subscription plans
            return $this->accessKeyType === AccessKeyType::Subscription;
        }

        if (!$request instanceof CurrentExchangeRateRequest && !$request instanceof HistoricalExchangeRateRequest) {
            return false;
        }

        if ($this->accessKeyType === AccessKeyType::Free && $request->baseCurrency !== 'EUR') {
            return false;
        }

        return true;
    }
}
