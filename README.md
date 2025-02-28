# A laravel Livewire block based pagebuilder, using Tailwind 4 and FluxUI.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vfineide/livewire-pagebuilder.svg?style=flat-square)](https://packagist.org/packages/vfineide/livewire-pagebuilder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/vfineide/livewire-pagebuilder/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/vfineide/livewire-pagebuilder/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/vfineide/livewire-pagebuilder/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/vfineide/livewire-pagebuilder/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/vfineide/livewire-pagebuilder.svg?style=flat-square)](https://packagist.org/packages/vfineide/livewire-pagebuilder)

This package gives you a pagebuilder that you can use in your Laravel application. It is built with Livewire and FluxUI, although FluxUI easily can be replaced with regular HTML fields.
## Installation

You can install the package via composer:

```bash
composer require vfineide/livewire-pagebuilder
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="livewire-pagebuilder-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="livewire-pagebuilder-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="livewire-pagebuilder-views"
```

## Usage

```php
$livewirePagebuilder = new Fineide\LivewirePagebuilder();
echo $livewirePagebuilder->echoPhrase('Hello, Fineide!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Vidar Fineide](https://github.com/vfineide)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
