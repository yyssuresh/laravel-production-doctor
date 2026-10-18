# Security Policy

## Reporting a vulnerability

Please report security issues privately to the maintainer through the contact route on the GitHub profile at https://github.com/yyssuresh. Do not include production secrets, credentials, application keys, or customer data. A dedicated private vulnerability reporting channel should be enabled as the project grows.

## Security design

- The tool does not boot Laravel or execute application configuration files.
- Environment values are parsed as data; they are never evaluated as PHP or shell commands.
- Reports intentionally omit secret values and suppress raw connection exceptions.
- Network checks are opt-out with `--no-network`; they connect only to endpoints configured in the inspected environment file.
- Database DSN delimiters are rejected before attempting a connection.
- Run the tool against staging first and review the code before using it in production.
