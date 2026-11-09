# Implementation Plan

## Product lines

- `Simple-PHP-DB-Wrapper_7.4_Support` is the final PHP 7.4-compatible line. Finish the listed work, publish a normal SemVer release after checking existing published tags, then freeze features. After freeze, accept security fixes only.
- `main` becomes the modern line with a declared PHP 8.3+ floor. Support and test current stable PHP releases; run PHP 8.6 as a non-blocking prerelease job until GA.
- Keep the library MySQL/PDO focused. SQLite is a test backend for portable behavior, not a promise of a second production dialect.

## PHP 7.4 support line: final feature release

### Core/API

1. Replace hidden `.env` bootstrapping with PDO/configuration dependency injection. Remove Symfony Dotenv from runtime requirements.
2. Keep Monolog. Support a PHP 7.4-compatible Monolog 2 and Monolog 3 on newer PHP; allow logger injection and avoid creating log directories/files as an implicit constructor side effect.
3. Add parameterized operations. Bind values; validate and quote identifiers separately. Never treat `prepare()` without bound values as protection for interpolated SQL.
4. Replace SQL-fragment strings with arrays for columns, data, conditions, ordering, and integer pagination. Support equality, NULL, IN, and a deliberately small set of comparison/LIKE operators with nested AND/OR groups.
5. Provide the small, common operation set: select many, select one, select one column, insert, update, delete, count, exists, prepared raw execute/fetch, and a transaction callback. Keep complex joins/vendor-specific SQL available through prepared raw SQL rather than growing a large builder.
6. Make whole-table mutations explicit. An empty condition does not update/delete every row; provide explicit all-row operations if needed.
7. Standardize results to associative rows and make database failures visible as exceptions. Insert returns the generated ID where supported; update/delete return affected-row counts.
8. Do not add a chainable API in this release. The README item is considered and deferred: array arguments provide clear composition without maintaining a second API.

### Tests, CI, and package delivery

- Portable unit tests use an in-memory SQLite PDO connection where SQL behavior is portable.
- A MySQL service integration job verifies MySQL-specific behavior. Tests use an isolated schema and never drop a developer database.
- CI runs Composer validation/install, style check, static analysis, unit/integration tests, and package archive/install smoke checks on PHP 7.4. Add current PHP jobs that can execute the same PHP 7.4-compatible code.
- On a version tag, build a Composer archive and GitHub Release artifact. Configure Packagist auto-update/webhook or token publishing separately; never publish from pull-request jobs.
- Declare PHP and required PDO extension constraints accurately. Use a test-runner version compatible with PHP 7.4 in the legacy matrix.

## Modern line: `main`

1. Carry forward the safe PDO/API design and tests.
2. Require PHP 8.3 or newer; use Monolog 3 and current development tooling.
3. Use enums for closed input sets (for example sort direction), readonly value objects for immutable configuration/query options, and `#[\\Override]` where it catches real mistakes.
4. Keep PHP 8.4/8.5 syntax out unless it materially improves this library. Test PHP 8.3, 8.4, and 8.5; keep PHP 8.6 prerelease CI non-blocking until stable.
5. The PHP floor increase and any public API break require an appropriate SemVer major release.

## Execution sequence

1. Confirm existing tags/releases before assigning release numbers.
2. Finish the legacy branch API and tests; add its CI/package workflows.
3. Freeze the PHP 7.4 branch after its final release, with security-only maintenance.
4. Port the completed API to `main`, add modern PHP types/features, CI matrix, and release packaging.
5. Document install requirements, examples, migration path, support policy, and package publishing steps.
