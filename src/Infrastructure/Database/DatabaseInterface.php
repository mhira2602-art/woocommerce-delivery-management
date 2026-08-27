<?php
/**
 * Database operations required by the plugin.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Infrastructure\Database;

/**
 * Small boundary around WordPress's wpdb API.
 */
interface DatabaseInterface {
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
	/**
	 * Get the WordPress database prefix.
	 */
	public function getPrefix(): string;

	/**
	 * Prepare a query with wpdb's native placeholder handling.
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, ...$args ): string;

	/**
	 * Execute a query.
	 *
	 * @return int|false
	 */
	public function query( string $query );

	/**
	 * Insert a row using wpdb's typed API.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @param array<int,string>   $formats Formats matching values.
	 * @return int|false
	 */
	public function insert( string $table, array $data, array $formats = array() );

	/**
	 * Update a row using wpdb's typed API.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @param array<string,mixed> $where Conditions.
	 * @param array<int,string>   $formats Data formats.
	 * @param array<int,string>   $where_formats Condition formats.
	 * @return int|false
	 */
	public function update( string $table, array $data, array $where, array $formats = array(), array $where_formats = array() );

	/**
	 * Delete a row using wpdb's typed API.
	 *
	 * @param array<string,mixed> $where Conditions.
	 * @param array<int,string>   $where_formats Condition formats.
	 * @return int|false
	 */
	public function delete( string $table, array $where, array $where_formats = array() );

	/**
	 * Retrieve one row as an associative array.
	 *
	 * @return array<string,mixed>|null
	 */
	public function getRow( string $query ): ?array;

	/**
	 * Retrieve multiple rows as associative arrays.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getResults( string $query ): array;

	/**
	 * Return the last inserted ID.
	 */
	public function getInsertId(): int;
}
