# Database Architecture

## Purpose

Operational delivery data uses plugin-owned custom tables because delivery records, assignment, scheduling, and status history are queried independently of WordPress content. Storing this data in postmeta would create many rows per delivery, make operational filtering expensive, and couple the data model to content APIs.

WooCommerce orders remain owned by WooCommerce. The deliveries table stores only `order_id`; it does not copy the order object, customer metadata, or order totals. Order data must be read through WooCommerce order APIs, which keeps the plugin compatible with HPOS and avoids direct assumptions about WordPress posts or WooCommerce order tables.

## Tables

- `{prefix}wdm_deliveries`: one operational delivery record per WooCommerce order delivery.
- `{prefix}wdm_drivers`: driver identity and availability state.
- `{prefix}wdm_warehouses`: warehouse identity, code, location, and state.
- `{prefix}wdm_delivery_rules`: future scheduling and eligibility rule definitions.
- `{prefix}wdm_delivery_status_history`: append-only status audit records.

A delivery references a driver and warehouse by application-level IDs. A rule may reference a warehouse. History references a delivery. These are intentionally not MySQL foreign keys so upgrades remain compatible across hosts and deletion behavior can be controlled by application services later.

## Important columns and indexes

`wdm_deliveries` includes `order_id`, assignment IDs, `region`, `status`, schedule/actual dates, slot, charge, address fields, notes, and UTC timestamps. `order_id` supports order lookup. `status_date` supports operational queues by state and date. `driver_date`, `warehouse_date`, and `region_date` support dispatch and scheduling filters without adding an index for every column.

`wdm_drivers` indexes `status` for available-driver lists and `employee_reference` for external lookup. `wdm_warehouses` has a unique `code` index for stable warehouse lookup and `region_status` for location filtering. Rules use `active_priority`, `region_weekday`, and `warehouse_active` for future rule selection. History uses `delivery_created` for chronological delivery timelines and `created_at` for time-based maintenance queries.

Indexes have been chosen for expected reads. Additional indexes increase write cost and should be justified by a measured query pattern.

## Schema lifecycle

`SchemaManager` stores version `1.0.0` in the `wdm_schema_version` option. Activation invokes the manager, and normal plugin boot invokes its version-gated check. `dbDelta()` creates missing tables and applies compatible changes only when the stored version is missing or older. Future changes should add an explicit migration step keyed by the previous version, rather than changing historical migrations in place.

Table names always derive from `$wpdb->prefix`. Dynamic values go through `$wpdb->prepare()` or the typed `$wpdb` insert/update/delete methods. No database foreign keys are created against WordPress or WooCommerce tables.

## Transactions

Current repositories perform one write per operation, so they do not add artificial transactions. A future delivery-status application service should introduce a transaction when changing the current delivery row and inserting its history record together. That multi-write operation must commit both changes or roll both back.

## One million delivery records

At one million records, queries must remain selective and paginated. Use the existing status/date, assignment/date, region/date, and order indexes with bounded `LIMIT`/`OFFSET` or a later keyset-pagination strategy. Avoid unbounded result sets and avoid `SELECT *`; repositories project only the columns needed by each operation.

Filter before sorting, keep status and date predicates aligned with the composite indexes, and inspect slow queries with the database's query tools before adding indexes. Large installations may eventually need archival or retention policies for completed deliveries and old status history. Partitioning, read replicas, and denormalized reporting structures are future considerations, not part of this foundation.

## Integration testing

The repository unit tests use a recording database boundary to verify query construction and persistence delegation. They do not fake MySQL behavior. A WordPress integration suite should be added when the project has a configured WordPress test installation; it should run `dbDelta()` and repositories against a real test database.
