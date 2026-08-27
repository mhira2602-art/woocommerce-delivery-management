<?php
/**
 * Shared repository safeguards.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Infrastructure\Repository;

use InvalidArgumentException;
use WDM\Infrastructure\Database\DatabaseInterface;

/**
 * Base for focused WDM repositories.
 */
abstract class AbstractRepository {
	protected DatabaseInterface $database;
	protected string $table;
	/** @var array<int,string> */
	protected array $allowed_fields;

	/**
	 * @param DatabaseInterface $database       Database API boundary.
	 * @param string            $table          Table name.
	 * @param array<int,string> $allowed_fields Persistable columns.
	 */
	public function __construct( DatabaseInterface $database, string $table, array $allowed_fields ) {
		$this->database       = $database;
		$this->table          = $table;
		$this->allowed_fields = $allowed_fields;
	}

	/**
	 * Validate a primary or relationship identifier.
	 *
	 * @param int $id Identifier.
	 * @return int Validated identifier.
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	protected function requireId( int $id ): int {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'IDs must be positive integers.' );
		}

		return $id;
	}

	/**
	 * Validate a required integer relationship in a persistence payload.
	 *
	 * @param array<string,mixed> $data Payload values.
	 * @param string              $key  Relationship key.
	 * @throws InvalidArgumentException When the relationship ID is missing or invalid.
	 */
	protected function requireDataId( array $data, string $key ): void {
		if ( ! isset( $data[ $key ] ) || ! is_int( $data[ $key ] ) ) {
			throw new InvalidArgumentException( 'Relationship IDs must be positive integers.' );
		}

		$this->requireId( $data[ $key ] );
	}

	/**
	 * Build wpdb formats for a row payload.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @return array<int,string> Formats matching the row values.
	 */
	protected function formats( array $data ): array {
		$formats = array();
		foreach ( $data as $key => $value ) {
			$formats[] = is_int( $value ) || in_array( $key, array( 'order_id', 'driver_id', 'warehouse_id', 'delivery_id', 'actor_user_id', 'weekday', 'priority', 'is_active' ), true ) ? '%d' : ( is_float( $value ) || 'delivery_charge' === $key ? '%f' : '%s' );
		}

		return $formats;
	}

	/**
	 * Keep arbitrary input from becoming arbitrary SQL columns.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @return array<string,mixed> Validated row values.
	 * @throws InvalidArgumentException When an unrecognized column is provided.
	 */
	protected function filterData( array $data ): array {
		$unknown = array_diff( array_keys( $data ), $this->allowed_fields );
		if ( ! empty( $unknown ) ) {
			throw new InvalidArgumentException( 'Payload contains unrecognized columns.' );
		}

		return $data;
	}

	/**
	 * Add application-managed timestamps to a new row.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @return array<string,mixed> Timestamped row values.
	 */
	protected function prepareInsert( array $data ): array {
		$data = $this->filterData( $data );
		$now  = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		if ( in_array( 'created_at', $this->allowed_fields, true ) ) {
			$data['created_at'] = $data['created_at'] ?? $now;
		}
		if ( in_array( 'updated_at', $this->allowed_fields, true ) ) {
			$data['updated_at'] = $data['updated_at'] ?? $now;
		}

		return $data;
	}

	/**
	 * Add the application-managed update timestamp.
	 *
	 * @param array<string,mixed> $data Row values.
	 * @return array<string,mixed> Validated timestamped values.
	 */
	protected function prepareUpdate( array $data ): array {
		$data               = $this->filterData( $data );
		$data['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );

		return $data;
	}
}
