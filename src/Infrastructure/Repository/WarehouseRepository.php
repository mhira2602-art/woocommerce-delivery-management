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
}
