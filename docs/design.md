# Design Rationale {#design-rationale}

`switon/testing` exists so package tests can stay close to runtime wiring without booting a full application.

## See also {#see-also}

- [Testing Package](README.md) — setup, runner usage, and defaults
- [FAQ](faq.md#testing-faq) — short answers to common setup questions
- [Testing Database Code](../../db/docs/testing.md#testing-database-code) — database-focused test patterns

---

## Why It Exists {#why-it-exists}

Package tests usually need more than plain PHPUnit fixtures, but less than a full app boot.
This package standardizes that middle ground.

It gives tests three things together:

- a container that looks like runtime
- event collection that does not hide what was emitted
- one runner entrypoint across repository layouts

## What It Deliberately Owns {#owns}

`Switon\Testing\Container\Container` owns the default test wiring.

- real framework services stay real when behavior matters
- test doubles stay in place when determinism matters
- provider registration happens, but boot does not

That keeps package tests realistic without pushing runtime side effects into every test.

## What It Leaves Out {#outside}

The package does not try to replace every test style.

- it does not replace package-local helpers when a package needs them
- it does not turn private state assertions into a supported style
- it does not provide browser, end-to-end, or snapshot testing

## Event Boundary {#event-boundary}

Tests often need to know whether something was emitted without always wanting listeners to run.
`Switon\Testing\EventDispatcher` makes collection and listener execution separate on purpose.

`Switon\Testing\MockEventDispatcher` is there when the test wants that boundary even tighter.

## Runner Boundary {#runner-boundary}

The shared `tests/run.sh` entrypoint keeps package docs stable across monorepo and standalone layouts.
That avoids teaching two different command surfaces for the same package.
