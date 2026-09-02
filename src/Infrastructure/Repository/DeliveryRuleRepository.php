<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
use WDM\Application\Contract\DeliveryRuleStore;
/** Persists delivery rule definitions for a future rule engine. */
final class DeliveryRuleRepository extends AbstractRepository implements DeliveryRuleStore {
	public function __construct( DatabaseInterface $database ) {
		parent::__construct( $database, $database->getPrefix() . 'wdm_delivery_rules', array( 'region', 'warehouse_id', 'weekday', 'cutoff_time', 'delivery_slot', 'holiday_date', 'delivery_days', 'priority', 'conditions', 'is_active', 'created_at', 'updated_at' ) ); }
	/** @param array<string,mixed> $data @return int */
	public function insert( array $data ): int {
		$data   = $this->prepareInsert( $data );
		$result = $this->database->insert( $this->table, $data, $this->formats( $data ) );
		return false === $result ? 0 : $this->database->getInsertId(); }
	/** @return array<string,mixed>|null */
	public function findById( int $id ): ?array {
		$this->requireId( $id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, region, warehouse_id, weekday, cutoff_time, delivery_slot, holiday_date, delivery_days, priority, conditions, is_active, created_at, updated_at FROM {$this->table} WHERE id = %d", $id ) ); }
	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		$this->requireId( $id );
		$data = $this->prepareUpdate( $data );
		return false !== $this->database->update( $this->table, $data, array( 'id' => $id ), $this->formats( $data ), array( '%d' ) ); }
	public function delete( int $id ): bool {
		$this->requireId( $id );
		return false !== $this->database->delete( $this->table, array( 'id' => $id ), array( '%d' ) ); }
	/** @return array<int,array<string,mixed>> */
	public function findActiveByRegion( string $region ): array {
		return $this->database->getResults( $this->database->prepare( "SELECT id, region, warehouse_id, weekday, cutoff_time, delivery_slot, holiday_date, delivery_days, priority, conditions, is_active, created_at, updated_at FROM {$this->table} WHERE region = %s AND is_active = 1 ORDER BY priority DESC, id ASC", $region ) ); }

	/**
	 * @param array<string,mixed> $filters Optional region/status filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function findAll( int $limit = 20, int $offset = 0, array $filters = array() ): array {
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		$where  = array();
		$args   = array();

		if ( isset( $filters['region'] ) && '' !== trim( (string) $filters['region'] ) ) {
			$where[] = 'region = %s';
			$args[]  = trim( (string) $filters['region'] );
		}

		if ( isset( $filters['is_active'] ) ) {
			$where[] = 'is_active = %d';
			$args[]  = (int) $filters['is_active'];
		}
		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$like    = '%' . $this->database->escapeLike( trim( (string) $filters['search'] ) ) . '%';
			$where[] = '(region LIKE %s OR delivery_slot LIKE %s OR delivery_days LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}

		$query = "SELECT id, region, warehouse_id, weekday, cutoff_time, delivery_slot, holiday_date, delivery_days, priority, conditions, is_active, created_at, updated_at FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}
		$query .= ' ORDER BY priority DESC, id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		return $this->database->getResults( $this->database->prepare( $query, ...$args ) );
	}

	/** @param array<string,mixed> $filters Optional region/status filters. */
	public function countAll( array $filters = array() ): int {
		$where = array();
		$args  = array();

		if ( isset( $filters['region'] ) && '' !== trim( (string) $filters['region'] ) ) {
			$where[] = 'region = %s';
			$args[]  = trim( (string) $filters['region'] );
		}

		if ( isset( $filters['is_active'] ) ) {
			$where[] = 'is_active = %d';
			$args[]  = (int) $filters['is_active'];
		}
		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$like    = '%' . $this->database->escapeLike( trim( (string) $filters['search'] ) ) . '%';
			$where[] = '(region LIKE %s OR delivery_slot LIKE %s OR delivery_days LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}

		$query = "SELECT COUNT(*) FROM {$this->table}";
		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		return (int) $this->database->getVar( $this->database->prepare( $query, ...$args ) );
	}
}
