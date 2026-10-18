# Laravel Production Doctor

A lightweight, read-only command-line diagnostic tool for Laravel deployment directories. It checks common production configuration risks and generates human-readable or JSON reports suitable for deployment and CI workflows.

> **Current release: v0.2.1.** Test in a disposable or staging environment first. This tool is not a substitute for a security audit, monitoring, or a full Laravel application boot test.

## What makes this tool different?

Laravel Production Doctor is a standalone CLI tool that does not boot the target Laravel application, execute its `artisan` command, or evaluate its `config/*.php` files. This provides a cautious first-pass inspection of a deployment directory while keeping the tool's execution surface small.

It is not intended to replace Laravel's first-party diagnostic tools or comprehensive application monitoring.

## Requirements

- PHP 8.2 or newer
- Composer
- A Laravel application directory containing `artisan` and `composer.json`
- Appropriate PDO driver extensions for any database connectivity checks you explicitly enable
