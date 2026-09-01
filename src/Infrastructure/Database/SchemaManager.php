<?php
/**
 * Plugin database schema manager.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Infrastructure\Database;

use RuntimeException;

/**
 * Creates and upgrades plugin-owned operational tables.
 */
final class SchemaManager {
	public const VERSION = '1.0.1';
	public const OPTION  = 'wdm_schema_version';

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/**
	 * @param DatabaseInterface $database Database API boundary.
	 */
	public function __construct( DatabaseInterface $database ) {
		$this->database = $database;
	}

	/**
	 * Install the complete schema during plugin activation.
	 *
	 * @throws RuntimeException When WordPress schema functions are unavailable.
	 */
	public function install(): void {
		$this->runSchemaUpdate();
	}

	/**
	 * Upgrade an existing installation only when its version is outdated.
	 *
	 * @return bool Whether an upgrade was performed.
	 * @throws RuntimeException When WordPress schema functions are unavailable.
	 */
	public function upgradeIfNeeded(): bool {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			throw new RuntimeException( 'WordPress functions are required to install the database schema.' );
		}

		$version = get_option( self::OPTION );
		if ( self::VERSION === $version ) {
			return false;
		}

		$this->runSchemaUpdate();
		return true;
	}

	/**
	 * Run dbDelta and persist the current schema version.
	 *
	 * @throws RuntimeException When WordPress schema functions are unavailable.
	 */
	private function runSchemaUpdate(): void {
		if ( ! function_exists( 'update_option' ) ) {
			throw new RuntimeException( 'WordPress functions are required to install the database schema.' );
		}

		$this->loadDbDelta();
		if ( ! function_exists( 'dbDelta' ) ) {
			throw new RuntimeException( 'WordPress dbDelta() is required to install the database schema.' );
		}

		dbDelta( $this->schemaSql() );
		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Get a plugin table name from its logical name.
	 *
	 * @throws RuntimeException When the logical table name is unknown.
	 */
	public function table( string $name ): string {
		$tables = $this->tableNames();
		if ( ! isset( $tables[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This value is part of an internal exception, not rendered output.
			throw new RuntimeException( sprintf( 'Unknown WDM table: %s', $name ) );
		}

		return $tables[ $name ];
	}

	/**
	 * @return array<string,string>
	 */
	public function tableNames(): array {
		$prefix = $this->database->getPrefix();

		return array(
			'deliveries'     => $prefix . 'wdm_deliveries',
			'drivers'        => $prefix . 'wdm_drivers',
			'warehouses'     => $prefix . 'wdm_warehouses',
			'delivery_rules' => $prefix . 'wdm_delivery_rules',
			'status_history' => $prefix . 'wdm_delivery_status_history',
		);
	}

	/**
	 * Exposed for schema-focused tests and documentation tooling.
	 */
	public function schemaSql(): string {
		$tables  = $this->tableNames();
		$charset = function_exists( 'get_charset_collate' ) ? get_charset_collate() : '';

		return "CREATE TABLE {$tables['deliveries']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			driver_id bigint(20) unsigned NULL,
			warehouse_id bigint(20) unsigned NULL,
			region varchar(100) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'pending',
			scheduled_date date NULL,
			estimated_date date NULL,
			actual_date date NULL,
			time_slot varchar(100) NOT NULL DEFAULT '',
			delivery_charge decimal(19,4) NOT NULL DEFAULT 0.0000,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			address_line_1 varchar(255) NOT NULL DEFAULT '',
			address_line_2 varchar(255) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			state varchar(100) NOT NULL DEFAULT '',
			postcode varchar(30) NOT NULL DEFAULT '',
			country varchar(2) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			notes text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY order_id (order_id), KEY status_date (status, scheduled_date), KEY driver_date (driver_id, scheduled_date), KEY warehouse_date (warehouse_id, scheduled_date), KEY region_date (region, scheduled_date)
		) {$charset};
		CREATE TABLE {$tables['drivers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'inactive',
			employee_reference varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY status (status), KEY employee_reference (employee_reference)
		) {$charset};
		CREATE TABLE {$tables['warehouses']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			code varchar(50) NOT NULL,
			address_line_1 varchar(255) NOT NULL DEFAULT '',
			address_line_2 varchar(255) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			state varchar(100) NOT NULL DEFAULT '',
			postcode varchar(30) NOT NULL DEFAULT '',
			country varchar(2) NOT NULL DEFAULT '',
			region varchar(100) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'inactive',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY code (code), KEY region_status (region, status)
		) {$charset};
		CREATE TABLE {$tables['delivery_rules']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			region varchar(100) NOT NULL DEFAULT '',
			warehouse_id bigint(20) unsigned NULL,
			weekday tinyint unsigned NULL,
			cutoff_time time NULL,
			delivery_slot varchar(100) NOT NULL DEFAULT '',
			holiday_date date NULL,
			delivery_days varchar(100) NOT NULL DEFAULT '',
			priority int unsigned NOT NULL DEFAULT 0,
			conditions longtext NULL,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY active_priority (is_active, priority), KEY region_weekday (region, weekday), KEY warehouse_active (warehouse_id, is_active)
		) {$charset};
		CREATE TABLE {$tables['status_history']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			delivery_id bigint(20) unsigned NOT NULL,
			previous_status varchar(30) NULL,
			new_status varchar(30) NOT NULL,
			actor_user_id bigint(20) unsigned NULL,
			note text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id), KEY delivery_created (delivery_id, created_at), KEY created_at (created_at)
		) {$charset};";
	}

	private function loadDbDelta(): void {
		if ( function_exists( 'dbDelta' ) || ! defined( 'ABSPATH' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
}
