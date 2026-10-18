# Roadmap

This roadmap describes possible work, not delivered functionality or promised release dates.

## Initial release

- [x] Read-only Laravel project detection
- [x] Safe basic `.env` parsing
- [x] Debug, environment, key-presence, PHP, extension, cache-file, and permission checks
- [x] Optional database connection and Redis TCP reachability checks
- [x] Human-readable and JSON output
- [x] PHPUnit tests and GitHub Actions matrix

## Possible follow-up releases

- [ ] Add a broader dotenv compatibility test suite
- [ ] Add optional HTTP/TLS checks for a user-supplied application URL
- [ ] Add queue-worker and scheduler checks with documented integration requirements
- [ ] Add SARIF or other CI report format if users request it
- [ ] Publish signed release artifacts and package metadata after a stable release process is established

Features will be prioritized from real user reports and contributions. No adoption or release claims are implied by this roadmap.
