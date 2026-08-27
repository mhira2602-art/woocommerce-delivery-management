<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
/** Persists the append-only audit trail for delivery status changes. */
final class DeliveryStatusHistoryRepository extends AbstractRepository {
	public function __construct( DatabaseInterface $database ) {
		parent::__construct( $database, $database->getPrefix() . 'wdm_delivery_status_history', array( 'delivery_id', 'previous_status', 'new_status', 'actor_user_id', 'note', 'created_at' ) ); }
	/** @param array<string,mixed> $data @return int */
	public function insert( array $data ): int {
		$this->requireDataId( $data, 'delivery_id' );
		$data   = $this->prepareInsert( $data );
		$result = $this->database->insert( $this->table, $data, $this->formats( $data ) );
		return false === $result ? 0 : $this->database->getInsertId(); }
	/** @return array<string,mixed>|null */
	public function findById( int $id ): ?array {
		$this->requireId( $id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, delivery_id, previous_status, new_status, actor_user_id, note, created_at FROM {$this->table} WHERE id = %d", $id ) ); }
	/** @return array<int,array<string,mixed>> */
	public function findByDeliveryId( int $delivery_id, int $limit = 100, int $offset = 0 ): array {
		$this->requireId( $delivery_id );
		$limit  = max( 1, min( 1000, $limit ) );
		$offset = max( 0, $offset );
		return $this->database->getResults( $this->database->prepare( "SELECT id, delivery_id, previous_status, new_status, actor_user_id, note, created_at FROM {$this->table} WHERE delivery_id = %d ORDER BY created_at ASC, id ASC LIMIT %d OFFSET %d", $delivery_id, $limit, $offset ) );
	}
}
