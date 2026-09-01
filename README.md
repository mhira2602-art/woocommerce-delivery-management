# WooCommerce Delivery & Logistics Management

Foundation for a maintainable WooCommerce delivery and logistics management plugin.

## Current Status

This project is in active development. The foundation and database persistence layer are implemented. Delivery workflows, admin screens, REST API routes, AJAX endpoints, cron jobs, reporting UI, and business logic belong to later milestones.

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

## WooCommerce Integration

The plugin is structured to work with WooCommerce without hard-coding WooCommerce internals into the delivery domain. When WooCommerce is active, the plugin registers a small integration layer that listens for order lifecycle events, checks whether the order is physical and eligible, and then creates a delivery via the existing application service.

The integration uses the public WooCommerce APIs (`wc_get_order()`, `WC_Order`, and order getters) and is compatible with HPOS by avoiding direct reads from `wp_posts`, `wp_postmeta`, or WooCommerce's internal table structure. The delivery record stores only the operational order ID and delivery data it actually needs.

Idempotency is enforced at the application layer and in the delivery schema by requiring a unique `order_id` per delivery. Repeated WooCommerce events will not create duplicate deliveries.

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
- `src/Infrastructure/Database/` provides the `$wpdb` boundary and versioned schema manager.
- `src/Infrastructure/Repository/` contains focused repositories for plugin-owned operational data.
- `tests/Unit/` covers persistence contracts without pretending to simulate MySQL.
- `docs/database.md` documents the schema, indexes, relationships, and scaling considerations.

This project is under active development.
