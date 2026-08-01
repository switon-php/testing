# Switon Testing Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/testing/ci.yml?branch=main&label=CI)](https://github.com/switon-php/testing/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's test bootstrap and fixtures for package-level tests.

## Highlights

- **Ready test base:** package tests start with a Switon container already wired.
- **Common defaults:** core services and test doubles are prebound.
- **Reusable test doubles:** logger, events, console, clock, cache, random, and translator are prebound.
- **Listener support:** `MockListenerProvider` keeps event wiring lightweight in tests.
- **Package-aware checks:** `ComposerExtra` and `PackagePathAssert` discover package layout (workspace, vendor, or split root).

## Installation

```bash
composer require --dev switon/testing
```

## Quick Start

```php
use Switon\Testing\TestCase;

final class UserServiceTest extends TestCase
{
    public function testRegisterCreatesUser(): void
    {
        $service = $this->make(UserService::class);

        $user = $service->register('test@example.com');

        self::assertSame('test@example.com', $user->email);
    }
}
```

Docs: https://github.com/switon-php/testing/blob/main/docs/README.md

## License

MIT.
