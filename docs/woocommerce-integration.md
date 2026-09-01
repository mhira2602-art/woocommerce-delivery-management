# WooCommerce integration

## Dependency and HPOS compatibility

This plugin is designed to work with WooCommerce but it does not assume WooCommerce is always active. The plugin bootstrap checks whether WooCommerce is available and only registers lifecycle hooks when it is.

HPOS compatibility is declared through WooCommerce's official feature-compatibility API, using `before_woocommerce_init` and `Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility()`. This is the correct WooCommerce mechanism for custom order tables support and keeps the plugin aligned with modern WooCommerce installations without reading order storage directly.

## Public API usage

All WooCommerce order access goes through public WooCommerce APIs: `wc_get_order()`, `WC_Order`, and the order getter methods. The integration layer does not perform direct SQL against WooCommerce order tables or the WordPress order tables. The order gateway is a thin adapter and the delivery domain remains separate from WooCommerce internals.

## Order-to-delivery flow

The WooCommerce integration layer is intentionally small and sits between WordPress/WooCommerce callbacks and the existing delivery application layer.

1. A WooCommerce order-status hook fires.
2. The integration receives the order or resolves it via `wc_get_order()`.
3. An eligibility check decides whether the order is physical and suitable for delivery creation.
4. The application service checks whether a delivery already exists for the matching WooCommerce order ID.
5. If the order is eligible and no delivery exists, the application creates the delivery domain record.

The selected hook is `woocommerce_order_status_changed`.

## Eligible order rules

A WooCommerce order is considered eligible for automatic delivery creation when it:

- is a valid order object;
- contains at least one physical/shippable item;
- has a usable shipping address when shipping is required;
- is not cancelled, refunded, or failed.

Virtual or downloadable-only orders are rejected before any delivery is created.

## Shipping address mapping

The order gateway maps only the operational delivery fields from the WooCommerce order: first and last name, company, address lines, city, state, postcode, country, and phone. It does not map WooCommerce-specific non-delivery data and does not compute delivery dates.

## Delivery-date calculation

Delivery dates are not computed in the WooCommerce gateway. The gateway only maps order data. The delivery application layer uses the existing `DeliveryDateCalculator` as the single source of truth for scheduled and estimated dates. This keeps date logic consistent and avoids duplicate calculation paths.

## Idempotency and duplicate protection

The plugin performs duplicate protection at two levels:

- application-level protection: `DeliveryService::findByOrderId()` prevents duplicate delivery creation for the same WooCommerce order;
- database-level protection: the delivery schema keeps a unique `order_id` index.

This prevents repeated WooCommerce lifecycle events from creating duplicate deliveries.

## Status mapping and cancellation behavior

The integration only creates deliveries for delivery-appropriate statuses such as `pending`, `processing`, and `on-hold`.

If a WooCommerce order is cancelled, failed, or refunded and a delivery already exists for that order, the integration explicitly moves the delivery through the domain lifecycle by changing its status to `cancelled`. This keeps the delivery lifecycle owned by the delivery domain rather than being inferred from WooCommerce completion.

A WooCommerce `completed` status does not automatically mark the delivery as delivered. Completion and delivery fulfillment are separate concerns. The delivery system remains the source of truth for actual delivery status progression.

## Behavior when WooCommerce is unavailable

The plugin safely loads its generic foundation and database schema without fatal errors when WooCommerce is not active. WooCommerce-specific hooks are only registered when WooCommerce is present and active.

## Performance and errors

The implementation keeps the flow lightweight: it reads the order once, checks the existing delivery once, and writes a new delivery only when the order is eligible and no duplicate exists. Errors are intentionally tolerated at the integration boundary so missing order data or duplicate events do not crash request processing.
