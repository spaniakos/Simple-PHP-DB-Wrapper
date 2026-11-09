# Implementation Plan

## Product lines

- `PHP_7.4_Support` is frozen for security maintenance. Its final feature release is `v1.0.0`.
- `main` is the modern line, with PHP 8.3 as its minimum version. Its first stable modern release is `v2.0.0`.
- Keep the library MySQL/PDO focused. SQLite is a test backend for portable behavior, not a promise of a second production dialect.
- `Hand-Written-Legacy` preserves the original main-line snapshot.

## PHP 7.4 support line: frozen

The planned PHP 7.4 work is complete and released as `v1.0.0`. The branch is feature-frozen and receives security fixes only. Its CI and release workflows stay on that branch.

The published version uses a Git tag and GitHub Release. Git tags are what Composer and Packagist use to identify installable versions; GitHub provides source archives for releases. We do not build a separate custom package archive.

## Modern line: `main`

1. Require PHP 8.3 or newer; use Monolog 3 and current development tooling.
2. Use enums for closed input sets (for example sort direction), readonly value objects for immutable query options, and `#[\\Override]` where it catches real mistakes.
3. Keep PHP 8.4/8.5 syntax out unless it materially improves this library. Test PHP 8.3, 8.4, and 8.5; keep PHP 8.6 prerelease CI non-blocking.
4. Use prepared raw SQL for advanced joins, nested queries, and unions rather than growing a large SQL builder. Keep chainable methods deferred unless they prove meaningfully simpler than array/value-object arguments.
5. Public API and PHP-floor changes are released under the SemVer major version `2.x`.

### Automated checks and publishing

- SQLite-backed unit tests and isolated MySQL integration tests cover portable and MySQL behavior.
- Modern CI runs on pushes to `main` and `PHP_8.3_Modern`, and on pull requests targeting either branch. It validates Composer metadata, installs dependencies, runs tests, checks formatting, and runs PHPStan across PHP 8.3, 8.4, and 8.5. PHP 8.6 prerelease CI is non-blocking.
- Version tags matching `v2.*` create a GitHub Release with generated notes. No custom package archive is built; Composer/Packagist installs the tagged source.

## Completed release and branch sequence

1. Completed the PHP 7.4 API, examples, and tests.
2. Published `v1.0.0` from `PHP_7.4_Support` and froze that line for security maintenance.
3. Preserved the old main snapshot as `Hand-Written-Legacy`, cleaned up obsolete branches, and merged the 7.4 base to `main`.
4. Completed and merged the PHP 8.3 modern implementation, including typed API options, tests, documentation, and CI.
5. Published the modern `v2.0.0` GitHub Release from the tested `main` commit. The 2.x line is the current modern release line.
