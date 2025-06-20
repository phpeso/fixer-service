<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Services\Fixer\AccessKeyType;
use Peso\Services\FixerService;
use PHPUnit\Framework\TestCase;
use stdClass;

class SupportTest extends TestCase
{
    public function testRequestsFree(): void
    {
        $service = new FixerService('xxxfreexxx', AccessKeyType::Free);

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'USD')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'USD', Date::today())));
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));
        self::assertFalse($service->supports(new stdClass()));
    }

    public function testRequests(): void
    {
        $service = new FixerService('xxxpaidxxx', AccessKeyType::Subscription);

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'USD')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'USD', Date::today())));
        self::assertTrue($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));
        self::assertFalse($service->supports(new stdClass()));
    }
}
