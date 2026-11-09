# Implementation Plan

## Product lines

- `PHP_7.4_Support` is frozen for security maintenance. Its final feature release is `v1.0.0`.
- `main` is the modern line after the PHP 8.3 work is reviewed and merged. Its minimum PHP version is 8.3, with a planned first stable release of `v2.0.0`.
- Keep the library MySQL/PDO focused. SQLite is a test backend for portable behavior, not a promise of a second production dialect.
- Keep `Hand-Written-Legacy` as the preserved snapshot of the old main line.

## PHP 7.4 support line: frozen

The planned PHP 7.4 work is complete and released as `v1.0.0`. The branch is feature-frozen and receives security fixes only. Historical release workflow and tests remain on that branch.

The published version uses a Git tag and GitHub Release. Git tags are what Composer and Packagist use to identify installable versions; GitHub provides source archives for releases. We do not need to build a separate custom package archive.

## Modern line: `PHP_8.3_Modern`

1. Carry forward the safe PDO/API design and tests.
2. Require PHP 8.3 or newer; use Monolog 3 and current development tooling.
3. Use enums for closed input sets (for example sort direction), readonly value objects for immutable query options, and `#[\\Override]` where it catches real mistakes.
4. Keep PHP 8.4/8.5 syntax out unless it materially improves this library. Test PHP 8.3, 8.4, and 8.5; keep PHP 8.6 prerelease CI non-blocking until stable.
5. Use prepared raw SQL for advanced joins, nested queries, and unions rather than growing a large SQL builder. Keep chainable methods deferred unless they prove meaningfully simpler than array/value-object arguments.
6. The PHP floor increase and public API changes require a SemVer major release. The first modern stable version is planned as `v2.0.0`.

### Automated checks and publishing

- SQLite-backed unit tests and isolated MySQL integration tests cover portable and MySQL behavior.
- CI validates Composer metadata, installs dependencies, runs tests, checks formatting, and runs PHPStan across PHP 8.3, 8.4, and 8.5. PHP 8.6 prerelease CI is non-blocking.
- Version tags matching `v2.*` create a GitHub Release with generated notes. No custom package archive is built; users can install the Git tag through Composer/Packagist.
- Do not publish or merge the modern line until the full CI matrix is green and its API, examples, migration guidance, and support policy are review-ready.

## Completed release and branch sequence

1. Check existing tags/releases before choosing release numbers.
2. Finish and test the PHP 7.4 API, tests, and examples.
3. Publish the `v1.0.0` release from `PHP_7.4_Support`; freeze that line for security maintenance.
4. Merge the completed PHP 7.4 work to `main`, preserve the old main snapshot, and clean up obsolete branches.
5. Update `PHP_8.3_Modern` from the merged `main` and finish modern implementation there.
6. Open a review PR to `main` only after modern work and CI are ready. Stop before merging so the owner can review the release boundary.
