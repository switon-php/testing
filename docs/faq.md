# Testing FAQ {#testing-faq}

Short answers for common package-test setup questions.

> **Prerequisites:** [Testing Package](README.md)

## Setup {#setup}

### Do I need a package-local `tests/TestCase.php`? {#local-testcase}

Only when the package needs its own helpers or fixtures. Keep it thin and extend `Switon\Testing\TestCase`.

### Where should dependency replacements go? {#replacement-location}

Use `setUpContainer()`. It runs before autowiring and before the subject is created.

### Can I run `vendor/bin/phpunit` directly? {#direct-phpunit}

Use `bash tests/run.sh ...` for normal package work. It keeps runner shortcuts and layout detection consistent.

## Events {#events}

### How do I assert emitted events without running listeners? {#events-without-listeners}

Call `disableEventDispatching()`. `Switon\Testing\EventDispatcher` still records events.

### When should I use `Switon\Testing\MockEventDispatcher`? {#mock-event-dispatcher}

Use it when the dispatcher binding itself should be collector-only.

## Defaults {#defaults}

### What does the testing container preconfigure? {#container-defaults}

It prebinds the common contracts most package tests need: logger, console, random, event dispatcher, context manager, app, filesystem, clock, class scanner, and provider discovery.

### How do I disable provider auto-registration? {#disable-auto-register}

Set `SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=1` before the container is created.
Any set value disables auto-registration.

### Why do coroutine-dependent tests behave differently? {#coroutine-default}

The shared bootstrap disables coroutine runtime by default.

### Why do I see temporary `@view` or `@runtime` paths? {#temp-path-alias}

When no real `PathAlias` is prebound, the container points those aliases at temporary directories.

### Why does `TranslatorInterface` return the key? {#stub-translator}

The default test binding is `Switon\Testing\Mock\StubTranslator`.

### Where does coverage go? {#coverage-report}

`bash tests/run.sh --coverage` writes HTML coverage into `tests/coverage`.
