# WordPress Admin UI

## Admin menu structure

The plugin registers a top-level menu called Delivery Management with these subpages:

- Dashboard
- Deliveries
- Drivers
- Warehouses
- Delivery Rules

The menu is registered through the WordPress admin-menu API and is guarded by the plugin capability layer so the screens do not appear to unauthorized users.

## Capability model

The plugin uses a minimal capability approach with capability names that distinguish operational tasks:

- `wdm_view_deliveries`
- `wdm_manage_deliveries`
- `wdm_manage_drivers`
- `wdm_manage_warehouses`
- `wdm_manage_delivery_rules`

Administrators and users with `manage_options` automatically satisfy these checks. This keeps day-to-day site users out of the operational screens without requiring a full role-management implementation.

## Request flow

The admin layer is intentionally thin:

1. WordPress admin request is received.
2. The screen or action checks the user capability.
3. Nonce verification runs for mutating actions.
4. Input values are sanitized and validated.
5. The action calls the existing application service.
6. The service enforces business rules in the domain/application layer.
7. WordPress redirects back with an admin notice.

The admin layer does not contain raw SQL or delivery business rules.

## Dashboard

The dashboard shows counts for the main delivery states and a brief list of recent deliveries. Queries are intentionally efficient and use repository-level count/list functions instead of fetching the full table into PHP.

## Delivery management

The deliveries list supports pagination and simple status/search filtering. Each row includes order linkage, delivery status, driver, warehouse, region, scheduled date, and updated date.

The detail screen shows the operational record and status history, and allows status changes plus driver and warehouse assignment through the existing `DeliveryService` methods.

## Driver, warehouse, and rule management

Each management screen supports a form for create/update alongside a paginated list. The admin layer simply translates user input into `DriverService`, `WarehouseService`, or `DeliveryRuleService` calls.

## Security and nonces

Mutating admin actions require:

- capability check
- nonce verification
- input sanitization
- validation of IDs and required fields
- redirect after successful submission

This follows the standard WordPress pattern and avoids duplicate submissions on refresh.

## Pagination and performance

The list screens use repository-level `LIMIT`/`OFFSET` queries and only load the current page. This keeps the admin UI efficient and avoids `SELECT *` style patterns.

## Destructive actions

The plugin does not expose unrestricted delete operations for deliveries. Delivery records are treated as operational history. Drivers, warehouses, and rules are managed by activation/deactivation or update semantics where appropriate rather than broad destructive deletion.

## Future enhancement

Bulk actions are intentionally not implemented in this milestone. The current goal is a clean, maintainable admin interface that stays within the existing architecture.
