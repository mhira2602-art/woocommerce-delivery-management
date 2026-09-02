<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
use WDM\Application\Contract\WarehouseStore;
/** Persists warehouse records. */
final class WarehouseRepository extends AbstractRepository implements WarehouseStore {
	public function __construct( DatabaseInterface $database ) {
		parent::__construct( $database, $database->getPrefix() . 'wdm_warehouses', array( 'name', 'code', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'region', 'status', 'created_at', 'updated_at' ) ); }
	/** @param array<string,mixed> $data @return int */
	public function insert( array $data ): int {
		$data   = $this->prepareInsert( $data );
		$result = $this->database->insert( $this->table, $data, $this->formats( $data ) );
		return false === $result ? 0 : $this->database->getInsertId(); }
	/** @return array<string,mixed>|null */
	public function findById( int $id ): ?array {
		$this->requireId( $id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, name, code, address_line_1, address_line_2, city, state, postcode, country, region, status, created_at, updated_at FROM {$this->table} WHERE id = %d", $id ) ); }
	/** @return array<string,mixed>|null */
	public function findByCode( string $code ): ?array {
		return $this->database->getRow( $this->database->prepare( "SELECT id, name, code, address_line_1, address_line_2, city, state, postcode, country, region, status, created_at, updated_at FROM {$this->table} WHERE code = %s", $code ) ); }
	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		$this->requireId( $id );
		$data = $this->prepareUpdate( $data );
		return false !== $this->database->update( $this->table, $data, array( 'id' => $id ), $this->formats( $data ), array( '%d' ) ); }
	public function delete( int $id ): bool {
		$this->requireId( $id );
		return false !== $this->database->delete( $this->table, array( 'id' => $id ), array( '%d' ) ); }

	/**
	 * @param array<string,mixed> $filters Optional status/region/search filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function findAll( int $limit = 20, int $offset = 0, array $filters = array() ): array {
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		$where  = array();
		$args   = array();

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$where[] = 'status = %s';
			$args[]  = (string) $filters['status'];
		}

		if ( isset( $filters['region'] ) && '' !== trim( (string) $filters['region'] ) ) {
			$where[] = 'region = %s';
			$args[]  = trim( (string) $filters['region'] );
		}

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$search  = trim( (string) $filters['search'] );
			$where[] = '(name LIKE %s OR city LIKE %s OR region LIKE %s)';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
		}

		$query = "SELECT id, name, code, address_line_1, address_line_2, city, state, postcode, country, region, status, created_at, updated_at FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}
		$query .= ' ORDER BY name ASC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		return $this->database->getResults( $this->database->prepare( $query, ...$args ) );
	}

	/** @param array<string,mixed> $filters Optional status/region/search filters. */
	public function countAll( array $filters = array() ): int {
		$where = array();
		$args  = array();

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$where[] = 'status = %s';
			$args[]  = (string) $filters['status'];
		}

		if ( isset( $filters['region'] ) && '' !== trim( (string) $filters['region'] ) ) {
			$where[] = 'region = %s';
			$args[]  = trim( (string) $filters['region'] );
		}

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$search  = trim( (string) $filters['search'] );
			$where[] = '(name LIKE %s OR city LIKE %s OR region LIKE %s)';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
			$args[]  = '%' . $search . '%';
		}

		$query = "SELECT COUNT(*) FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		return (int) $this->database->getVar( $this->database->prepare( $query, ...$args ) );
	}
}
