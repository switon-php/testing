# Testing Package {#testing-package}

`switon/testing` gives Switon package tests one realistic container, a shared `TestCase`, and a package-local runner.

**Key features:**

- **Shared `TestCase`** — `Switon\Testing\TestCase` builds a test container, runs `setUpContainer()`, and autowires the test instance
- **Realistic defaults** — the container keeps framework services where behavior matters and test doubles where determinism matters
- **Event collection** — `Switon\Testing\EventDispatcher` records dispatched events and can still forward them to listeners
- **Package-level runner** — `bash tests/run.sh ...` keeps unit, integration, and coverage runs consistent across layouts
- **Provider discovery** — package service providers are registered from composer metadata without a full app boot

---

## Installation {#installation}

**Requirements:** PHP 8.3+, PHPUnit 12+, plus the framework testing stack (`switon/core`, `switon/di`, `switon/event`, `switon/composer-extra`, and the PSR container/log/event packages).

```bash
composer require --dev switon/testing
```

---

## Quick Start {#quick-start}

Extend `Switon\Testing\TestCase`, create the subject with `$this->make()`, and assert behavior.

```php
namespace App\Tests;

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

Override `setUpContainer()` when one dependency must change before the subject is created.

Run one suite through the shared runner:

```bash
bash tests/run.sh unit
```

---

## Core Concepts {#core-concepts}

### Test Case {#test-case}

`Switon\Testing\TestCase` creates `Switon\Testing\Container\Container`, calls `setUpContainer()`, and then autowires `#[Autowired]` properties.
The shared bootstrap disables coroutine runtime by default; enable it explicitly in suites that need coroutine behavior.

Use `$this->make()` for subjects that should come from the test container.

### Container Defaults {#container-defaults}

The test container preconfigures the common contracts most packages need:

- `Psr\Log\LoggerInterface` → `Switon\Testing\MockLogger`
- `Switon\Core\ConsoleInterface` → `Switon\Testing\Mock\MockConsole`
- `Switon\Core\RandomInterface` → `Switon\Testing\MockRandom`
- `Switon\Eventing\EventDispatcherInterface` → `Switon\Testing\EventDispatcher`
- `Psr\EventDispatcher\EventDispatcherInterface` → the same testing dispatcher binding
- `Switon\Core\ContextManagerInterface` → `Switon\Testing\InMemoryContextManager`
- `Switon\Core\App` → `id=test-app`, `env=test`, `debug=true`
- `Switon\Core\TranslatorInterface` → `Switon\Testing\Mock\StubTranslator`

It also keeps real filesystem, clock, class-scanning, and app bindings available, and creates temporary `@view`, `@public`, and `@runtime` aliases when the container does not already have a `PathAlias`.

### Event Dispatching {#event-dispatching}

`Switon\Testing\EventDispatcher` always collects events. By default it still forwards them to the real dispatcher.

Use `disableEventDispatching()` when the test should verify emission without listener side effects. `getContext()` still comes from the real dispatcher.
Use `Switon\Testing\MockEventDispatcher` when the dispatcher itself should be collector-only.

### Provider Registration {#provider-registration}

The container discovers Switon service providers from composer extra metadata and calls `register()` only.
That keeps service wiring close to runtime without full boot-time side effects. It also registers resource aliases, but it does not call provider `boot()`.

### Test Doubles {#test-doubles}

The package also ships `Switon\Testing\MockClock`, `Switon\Testing\MockListenerProvider`, `Switon\Testing\Mock\MockCache`, `Switon\Testing\MockLogger`, `Switon\Testing\MockRandom`, and `Switon\Testing\Mock\StubTranslator`.

Bind them explicitly when a package test needs them.

---

## Configuration {#configuration}

Test behavior is driven mostly by container wiring and runner arguments.

### Runner Entry Point {#runner-entrypoint}

Use the package-local runner from the package root:

```bash
bash tests/run.sh
bash tests/run.sh unit
bash tests/run.sh integration
bash tests/run.sh all
bash tests/run.sh --fast
bash tests/run.sh --coverage
```

- `unit` and `integration` select the matching PHPUnit suite
- `all` runs the full suite explicitly
- `--fast` is a shortcut for the unit suite
- `--coverage` writes HTML coverage into `tests/coverage`
- shortcut parsing only reads the first positional argument

### Provider Auto-Registration {#provider-auto-registration}

Set `SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=1` before the test container is created when you want to disable provider discovery.
Any set value disables auto-registration.

---

## Keep in Mind {#keep-in-mind}

- Prefer `setUpContainer()` for dependency changes.
- Prefer behavior assertions over private state.
- Use `disableEventDispatching()` when listeners should not run.
- Prefer `bash tests/run.sh ...` over direct `vendor/bin/phpunit`.

---

## Next {#next}

- **Need setup questions answered quickly:** [FAQ](faq.md#testing-faq)
- **Need design trade-offs:** [Design Rationale](design.md#design-rationale)
- **Need database test patterns:** [Testing Database Code](https://github.com/switon-php/db/blob/main/docs/testing.md#testing-database-code)
