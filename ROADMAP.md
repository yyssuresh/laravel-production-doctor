# Roadmap

This roadmap describes possible work, not committed guarantees.

## v0.2.0 — Safety and testability

- [x] Make database and Redis checks opt-in.
- [x] Prefer process-injected environment values over `.env` values.
- [x] Expand conservative `.env` parsing and add regression tests.
- [x] Run syntax checks and PHPUnit across supported PHP versions in CI.
- [ ] Confirm the complete test suite passes on GitHub Actions after the update is pushed.

## Later

- Add a fixture Laravel application for integration testing.
- Add a testable check registry and clearer output severity policy.
- Consider machine-readable schema/versioning for JSON output.
- Add static analysis after selecting and documenting a supported toolchain.
- Gather real maintainer and user feedback before expanding the diagnostic set.

Do not report adoption, usage numbers, or compatibility claims without evidence.
