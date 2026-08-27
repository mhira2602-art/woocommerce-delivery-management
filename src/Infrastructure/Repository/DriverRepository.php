<?php
/** @package WDM */
declare( strict_types=1 );
namespace WDM\Infrastructure\Repository;

use WDM\Infrastructure\Database\DatabaseInterface;
/** Persists driver records. */
final class DriverRepository extends AbstractRepository {
	public function __construct( DatabaseInterface $database ) {
		parent::__construct( $database, $database->getPrefix() . 'wdm_drivers', array( 'name', 'email', 'phone', 'status', 'employee_reference', 'created_at', 'updated_at' ) ); }
	/** @param array<string,mixed> $data @return int */
	public function insert( array $data ): int {
		$data   = $this->prepareInsert( $data );
		$result = $this->database->insert( $this->table, $data, $this->formats( $data ) );
		return false === $result ? 0 : $this->database->getInsertId(); }
	/** @return array<string,mixed>|null */
	public function findById( int $id ): ?array {
		$this->requireId( $id );
		return $this->database->getRow( $this->database->prepare( "SELECT id, name, email, phone, status, employee_reference, created_at, updated_at FROM {$this->table} WHERE id = %d", $id ) ); }
	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		$this->requireId( $id );
		$data = $this->prepareUpdate( $data );
		return false !== $this->database->update( $this->table, $data, array( 'id' => $id ), $this->formats( $data ), array( '%d' ) ); }
	public function delete( int $id ): bool {
		$this->requireId( $id );
		return false !== $this->database->delete( $this->table, array( 'id' => $id ), array( '%d' ) ); }
	/** @return array<int,array<string,mixed>> */
	public function findByStatus( string $status ): array {
		return $this->database->getResults( $this->database->prepare( "SELECT id, name, email, phone, status, employee_reference, created_at, updated_at FROM {$this->table} WHERE status = %s ORDER BY name ASC", $status ) ); }
}
