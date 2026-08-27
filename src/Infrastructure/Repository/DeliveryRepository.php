<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
use WDM\Application\Contract\DeliveryStore;

/** Persists delivery records without owning delivery business rules. */
final class DeliveryRepository extends AbstractRepository implements DeliveryStore {
	/** @param DatabaseInterface $database Database API boundary. */
	public function __construct( DatabaseInterface $database ) {
		parent::__construct( $database, $database->getPrefix() . 'wdm_deliveries', array( 'order_id', 'driver_id', 'warehouse_id', 'region', 'status', 'scheduled_date', 'estimated_date', 'actual_date', 'time_slot', 'delivery_charge', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'notes', 'created_at', 'updated_at' ) );
	}

	/** @param array<string,mixed> $data @return int */
	public function insert( array $data ): int {
		$this->requireDataId( $data, 'order_id' );
		$data   = $this->prepareInsert( $data );
		$result = $this->database->insert( $this->table, $data, $this->formats( $data ) );
		return false === $result ? 0 : $this->database->getInsertId();
	}

	/** @return array<string,mixed>|null */
	public function findById( int $id ): ?array {
		$this->requireId( $id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, address_line_1, address_line_2, city, state, postcode, country, notes, created_at, updated_at FROM {$this->table} WHERE id = %d", $id ) );
	}

	/** @return array<string,mixed>|null */
	public function findByOrderId( int $order_id ): ?array {
		$this->requireId( $order_id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, address_line_1, address_line_2, city, state, postcode, country, notes, created_at, updated_at FROM {$this->table} WHERE order_id = %d", $order_id ) );
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		$this->requireId( $id );
		$data = $this->prepareUpdate( $data );
		return false !== $this->database->update( $this->table, $data, array( 'id' => $id ), $this->formats( $data ), array( '%d' ) );
	}

	public function delete( int $id ): bool {
		$this->requireId( $id );
		return false !== $this->database->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/** @return array<int,array<string,mixed>> */
	public function findByStatus( string $status, int $limit = 100, int $offset = 0 ): array {
		$limit  = max( 1, min( 1000, $limit ) );
		$offset = max( 0, $offset );
		return $this->database->getResults( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, created_at, updated_at FROM {$this->table} WHERE status = %s ORDER BY scheduled_date ASC, id ASC LIMIT %d OFFSET %d", $status, $limit, $offset ) );
	}
}
