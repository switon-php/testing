# Switon Testing Package

Shared test bootstrap and utilities for Switon Framework packages.

## Installation

```bash
composer require --dev switon/testing
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use Switon\Testing\TestCase;

class UserServiceTest extends TestCase
{
    public function testRegisterCreatesUser(): void
    {
        // Arrange
        $service = $this->make(UserService::class);

        // Act
        $user = $service->register('test@example.com');

        // Assert
        $this->assertSame('test@example.com', $user->email);
    }
}
```

Docs: https://docs.switon.dev/latest/testing

## License

MIT.
