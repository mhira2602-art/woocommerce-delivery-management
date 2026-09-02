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
		parent::__construct( $database, $database->getPrefix() . 'wdm_deliveries', array( 'order_id', 'driver_id', 'warehouse_id', 'region', 'status', 'scheduled_date', 'estimated_date', 'actual_date', 'time_slot', 'delivery_charge', 'first_name', 'last_name', 'company', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'phone', 'notes', 'created_at', 'updated_at' ) );
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
		return $this->database->getRow( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, first_name, last_name, company, address_line_1, address_line_2, city, state, postcode, country, phone, notes, created_at, updated_at FROM {$this->table} WHERE order_id = %d", $order_id ) );
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
		$limit   = max( 1, min( 1000, $limit ) );
		$offset  = max( 0, $offset );
		return $this->database->getResults( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, created_at, updated_at FROM {$this->table} WHERE status = %s ORDER BY scheduled_date ASC, id ASC LIMIT %d OFFSET %d", $status, $limit, $offset ) );
	}

	/**
	 * @param array<string,mixed> $filters Optional status/search filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function findAll( int $limit = 20, int $offset = 0, array $filters = array() ): array {
		$limit   = max( 1, min( 500, $limit ) );
		$offset  = max( 0, $offset );
		$where   = array();
		$args    = array();

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$where[] = 'status = %s';
			$args[]  = (string) $filters['status'];
		}

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$search = trim( (string) $filters['search'] );
			$where[] = '(CAST(order_id AS CHAR) LIKE %s OR CAST(id AS CHAR) LIKE %s)';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
		}

		$query = "SELECT id, order_id, driver_id, warehouse_id, region, status, scheduled_date, estimated_date, actual_date, time_slot, delivery_charge, created_at, updated_at FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}
		$query .= ' ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		return $this->database->getResults( $this->database->prepare( $query, ...$args ) );
	}

	/**
	 * @param array<string,mixed> $filters Optional status/search filters.
	 */
	public function countAll( array $filters = array() ): int {
		$where  = array();
		$args   = array();

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$where[] = 'status = %s';
			$args[]  = (string) $filters['status'];
		}

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$search = trim( (string) $filters['search'] );
			$where[] = '(CAST(order_id AS CHAR) LIKE %s OR CAST(id AS CHAR) LIKE %s)';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
		}

		$query = "SELECT COUNT(*) FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		return (int) $this->database->getVar( $this->database->prepare( $query, ...$args ) );
	}

	public function countByStatus( string $status ): int {
		return (int) $this->database->getVar( $this->database->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status ) );
	}

	/** @return array<int,array<string,mixed>> */
	public function findRecent( int $limit = 5 ): array {
		$limit = max( 1, min( 50, $limit ) );
		return $this->database->getResults( $this->database->prepare( "SELECT id, order_id, driver_id, warehouse_id, status, updated_at FROM {$this->table} ORDER BY updated_at DESC, id DESC LIMIT %d", $limit ) );
	}
}
