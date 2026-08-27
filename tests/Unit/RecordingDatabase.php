<?php
/**
 * Recording database boundary for unit tests.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Tests\Unit;

use WDM\Infrastructure\Database\DatabaseInterface;

/**
 * Records repository calls without simulating WordPress or MySQL.
 */
final class RecordingDatabase implements DatabaseInterface {
	public string $last_prepared_query = '';
	/** @var array<string,mixed> */
	public array $last_insert_data = array();
	/** @var array<string,mixed> */
	public array $last_update_where = array();
	/** @var array<string,mixed> */
	public array $last_update_data = array();
	private string $prefix;

	public function __construct( string $prefix = 'custom_' ) {
		$this->prefix = $prefix;
	}

	public function getPrefix(): string {
		return $this->prefix;
	}
	public function begin(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function prepare( string $query, ...$args ): string {
		$this->last_prepared_query = preg_replace_callback(
			'/%[sdf]/',
			static function ( array $placeholder ) use ( &$args ): string {
				unset( $placeholder );
				$value = array_shift( $args );
				return is_string( $value ) ? $value : (string) $value;
			},
			$query
		);
		return $this->last_prepared_query;
	}

	public function query( string $query ) {
		return 1; }

	public function insert( string $table, array $data, array $formats = array() ) {
		$this->last_insert_data = $data;
		return 1;
	}

	public function update( string $table, array $data, array $where, array $formats = array(), array $where_formats = array() ) {
		$this->last_update_data  = $data;
		$this->last_update_where = $where;
		return 1;
	}

	public function delete( string $table, array $where, array $where_formats = array() ) {
		return 1; }

	public function getRow( string $query ): ?array {
		return false !== strpos( $query, 'order_id = 42' ) ? array(
			'id'       => 17,
			'order_id' => 42,
		) : null;
	}

	public function getResults( string $query ): array {
		return false !== strpos( $query, 'delivery_id = 17' ) ? array(
			array(
				'id'          => 18,
				'delivery_id' => 17,
			),
		) : array();
	}

	public function getInsertId(): int {
		return 17; }
}
