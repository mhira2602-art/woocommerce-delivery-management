<?php
/**
 * WordPress database API adapter.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Infrastructure\Database;

use InvalidArgumentException;

/**
 * Keeps direct wpdb interaction in one infrastructure boundary.
 */
final class WordPressDatabase implements DatabaseInterface, \WDM\Application\Contract\TransactionManager {
	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	private object $wpdb;

	/**
	 * @param object $wpdb The global wpdb instance.
	 * @throws InvalidArgumentException When the object is not wpdb-compatible.
	 */
	public function __construct( object $wpdb ) {
		foreach ( array( 'prefix', 'prepare', 'query', 'insert', 'update', 'delete', 'get_row', 'get_results', 'insert_id' ) as $member ) {
			if ( ! property_exists( $wpdb, $member ) && ! method_exists( $wpdb, $member ) ) {
				throw new InvalidArgumentException( 'The supplied object is not compatible with wpdb.' );
			}
		}

		$this->wpdb = $wpdb;
	}

	public function getPrefix(): string {
		return (string) $this->wpdb->prefix;
	}

	public function begin(): void {
		$this->query( 'START TRANSACTION' ); }
	public function commit(): void {
		$this->query( 'COMMIT' ); }
	public function rollback(): void {
		$this->query( 'ROLLBACK' ); }

	public function prepare( string $query, ...$args ): string {
		$prepared = call_user_func_array( array( $this->wpdb, 'prepare' ), array_merge( array( $query ), $args ) );

		return is_string( $prepared ) ? $prepared : $query;
	}

	public function query( string $query ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The caller supplies only internal schema/query SQL.
		return $this->wpdb->query( $query );
	}

	public function insert( string $table, array $data, array $formats = array() ) {
		return $this->wpdb->insert( $table, $data, $formats );
	}

	public function update( string $table, array $data, array $where, array $formats = array(), array $where_formats = array() ) {
		return $this->wpdb->update( $table, $data, $where, $formats, $where_formats );
	}

	public function delete( string $table, array $where, array $where_formats = array() ) {
		return $this->wpdb->delete( $table, $where, $where_formats );
	}

	public function getRow( string $query ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Repository queries are prepared before crossing this boundary.
		$row = $this->wpdb->get_row( $query, 'ARRAY_A' );

		return is_array( $row ) ? $row : null;
	}

	public function getResults( string $query ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Repository queries are prepared before crossing this boundary.
		$rows = $this->wpdb->get_results( $query, 'ARRAY_A' );

		return is_array( $rows ) ? $rows : array();
	}

	public function getInsertId(): int {
		return (int) $this->wpdb->insert_id;
	}
}
