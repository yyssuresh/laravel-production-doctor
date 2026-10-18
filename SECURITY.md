# Security Policy

## Reporting a vulnerability

Please report security issues privately to the maintainer through the contact route on the GitHub profile at https://github.com/yyssuresh. Do not include production secrets, credentials, application keys, or customer data. A dedicated private vulnerability reporting channel should be enabled as the project grows.

## Security design

- The tool does not boot Laravel or execute application configuration files.
- Environment values are parsed as data; they are never evaluated as PHP or shell commands.
- Reports intentionally omit secret values and suppress raw connection exceptions.
- Database and Redis connectivity checks are disabled by default. The operator must pass `--network` to opt in to contacting configured endpoints.
- Database DSN delimiters/control characters are rejected before attempting a connection.
- Network timeout behavior depends partly on the PDO driver, DNS resolution, and operating system; `--network` should only be used when the operator intends to contact the target services.
- Run the tool against staging first and review the code before using it in production.
