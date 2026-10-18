# Contributing

Thanks for considering a contribution.

1. Open an issue to discuss substantial changes before starting work.
2. Keep checks read-only by default and avoid booting the target Laravel application.
3. Network checks must remain opt-in; do not introduce implicit outbound connections.
4. Never print or commit `.env` contents, application keys, passwords, tokens, or production host details.
5. Add tests for new checks and edge cases, including failure behavior.
6. Run `composer check` and `composer test` before opening a pull request.
7. Explain platform-specific assumptions and document false positives.

By submitting a contribution, you agree that it may be distributed under this project's MIT licence.
