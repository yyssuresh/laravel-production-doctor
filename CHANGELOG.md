# Changelog

## Unreleased — v0.2.1-rc.1 release candidate

- Reject unknown and duplicate CLI options, missing `--path` values, and empty path values with exit code 2.
- Support both `--path=/directory` and `--path /directory`.
- Add CLI subprocess integration tests for help, JSON, network opt-in, invalid paths, unknown arguments, and exit codes.
- Document strict argument validation and exit-code behavior.


## Unreleased (v0.2.1 development)

- Reject malformed quoted dotenv values that have unexpected suffixes or would consume later assignments.
- Make injected environment lookups deterministic and support explicit process-environment snapshots in tests.
- Validate Redis hosts before opening a socket, rejecting URI schemes and non-host syntax.
- Add regression coverage for malformed dotenv input and Redis stream-wrapper host rejection.

## Unreleased — v0.2.0

- Disabled database and Redis connectivity checks by default; use `--network` to opt in.
- Resolved environment values from process environment sources before falling back to `.env`.
- Improved dotenv parsing for quoted, multiline, escaped, and commented values without evaluating shell expressions.
- Added regression coverage for environment precedence, secret redaction, malformed values, and safe default network behavior.
- Expanded CI to run syntax checks and PHPUnit on PHP 8.2, 8.3, and 8.4.
- Clarified the tool's scope and limitations in documentation.

## 0.1.0

- Initial standalone CLI diagnostic engine and JSON report.
