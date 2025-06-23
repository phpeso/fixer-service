# Fixer Client for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]

[Packagist]: https://img.shields.io/packagist/v/peso/fixer-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/fixer-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/fixer-service.svg?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/fixer-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[Fixer](https://fixer.io/).

## Installation

```bash
composer require peso/fixer-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/fixer-service php-http/discovery guzzlehttp/guzzle symfony/cache
```

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Peso\Services\Fixer\AccessKeyType;
use Peso\Services\FixerService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new FixerService('...', AccessKeyType::Free, cache: $cache);
$converter = new CurrencyConverter($service);

// 14419.61 as of 2025-06-20
echo $converter->convert('12500', 'EUR', 'USD', 2), PHP_EOL;

```

## Documentation

Read the full documentation here: <https://phpeso.org/v0.x/services/fixer.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/ecb-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
