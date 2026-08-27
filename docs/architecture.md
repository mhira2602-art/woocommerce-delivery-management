# Core Architecture

## Layers

The domain layer contains WordPress-independent delivery concepts. `Delivery` validates its core invariants, and `DeliveryStatus` owns the controlled status lifecycle. Application services coordinate use cases and translate missing or failed persistence into application exceptions. Repositories remain the only layer responsible for SQL and `$wpdb`.

```
Presentation/integration (future REST, admin, hooks)
                 |
          Application services
                 |
       Domain models and rules
                 |
       Repository contracts
                 |
     Infrastructure repositories -> WordPress $wpdb
```

Business classes receive explicit contracts through the existing container. They do not read HTTP globals, create SQL, or depend on REST/admin classes.

## Services

`DeliveryService` creates, retrieves, updates, assigns drivers and warehouses, and changes status. Driver, warehouse, and rule services cover the minimum CRUD and active-state operations. `DeliveryDateCalculator` is a deterministic baseline that adds delivery days and adds one day when an optional cutoff has passed; future rule evaluation belongs outside this calculation primitive.

## Status lifecycle

The supported statuses are `pending`, `scheduled`, `assigned`, `out_for_delivery`, `delivered`, `failed`, and `cancelled`. Normal progression is pending -> scheduled -> assigned -> out_for_delivery -> delivered. Cancellation is allowed before completion, failure is allowed during dispatch, and a failed delivery can be rescheduled. Invalid transitions are rejected before persistence.

## Assignment flow

The delivery service first verifies the delivery, then verifies the requested driver or warehouse. Only records with status `active` may be assigned. The delivery update is then persisted. No routing, GPS, or availability algorithm is included yet.

## Transactions

A status change updates the delivery and appends status history, so `DeliveryService` wraps those two writes in the smallest available transaction boundary. On any failure it rolls back and rethrows an application exception. Other single-write operations do not use transactions. A future WordPress integration test should verify this against a real database.

## Architectural decisions

- Domain classes do not depend on WordPress or WooCommerce objects.
- WooCommerce order data remains external; deliveries retain only the order ID.
- Repository contracts make services unit-testable without pretending to emulate MySQL.
- Array payloads remain temporary at this milestone, while repository allowlists prevent unknown columns from being persisted.
- Hooks and controllers will remain composition/presentation boundaries; business behavior is kept in services so later entry points share the same rules.
