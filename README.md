# WooCommerce Delivery & Logistics Management

Foundation for a maintainable WooCommerce delivery and logistics management plugin.

## Current Status

This project is in active development. Only the foundation and plugin bootstrap exist at this stage. No delivery workflows, admin screens, REST API routes, database tables, AJAX endpoints, cron jobs, or business logic have been implemented yet.

## Requirements

- WordPress
- WooCommerce
- PHP 7.4 or later
- Composer

## Development Setup

1. Place the plugin in `wp-content/plugins/woocommerce-delivery-management`.
2. Run `composer install` inside the plugin directory.
3. Activate the plugin from the WordPress admin Plugins screen.
4. Ensure WooCommerce is installed and active.

## Development

Install Composer dependencies from the plugin directory:

```bash
composer install
```

Run the unit tests:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

Run WordPress Coding Standards checks:

```bash
composer phpcs
```

Run the complete quality check:

```bash
composer check
```

## Architecture Overview

- `woocommerce-delivery-management.php` handles WordPress bootstrap and dependency checks.
- `src/Application/Bootstrap.php` initializes the application layer.
- `src/Support/Container.php` provides a minimal internal dependency-injection foundation.
- `src/` is reserved for future admin, API, application, domain, infrastructure, integration, and support code.
- `tests/`, `docs/`, `assets/`, and `languages/` are reserved for future project growth.

This project is under active development.
