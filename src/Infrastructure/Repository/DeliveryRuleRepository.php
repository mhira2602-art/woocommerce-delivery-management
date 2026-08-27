<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
/** Persists delivery rule definitions for a future rule engine. */
final class DeliveryRuleRepository extends AbstractRepository {
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
}
