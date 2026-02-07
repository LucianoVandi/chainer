# Changelog

All notable changes to `Chainer` will be documented in this file.

## v0.2.0 - 2026-02-07

New middleware orchestration features.

Highlights:

- Conditional middleware: when/unless helpers
- Named middleware with add/remove/replace/has
- Introspection via toArray()/debug()
- Test suite reorganized with support helpers
- 100% coverage and PHPStan clean

Maintenance:

- Style cleanup in Chainer

**Full Changelog**: https://github.com/LucianoVandi/chainer/compare/v0.1.1...v0.2.0

## v0.1.1 - 2026-02-05

### What's Changed

* Fix PHPStan 2.x false-positive in CallableMiddleware by @LucianoVandi in https://github.com/LucianoVandi/chainer/pull/6
* chore(deps): bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/LucianoVandi/chainer/pull/1
* chore(deps): bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/LucianoVandi/chainer/pull/2
* chore(deps): bump dependabot/fetch-metadata from 1.6.0 to 2.5.0 by @dependabot[bot] in https://github.com/LucianoVandi/chainer/pull/3
* Fix PHPStan ignore in CallableMiddleware by @LucianoVandi in https://github.com/LucianoVandi/chainer/pull/7

**Full Changelog**: https://github.com/LucianoVandi/chainer/compare/v0.1.0...v0.1.1

## v0.1.0 - 2026-02-05

Highlights:

- PSR-15 middleware chain with strict error handling
- Default resolver supports instances, callables, and class-strings
- Optional PSR-11 container resolution via ContainerResolver
- ResolverChain for composable resolution strategies
- 100% test coverage + PHPStan (strict)
- Docker-based dev setup + Makefile shortcuts
- CI updated with quality gates and style checks

**Full Changelog**: https://github.com/LucianoVandi/chainer/commits/v0.1.0
