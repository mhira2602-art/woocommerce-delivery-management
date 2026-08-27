<?php
/** Warehouse application service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use WDM\Application\Contract\WarehouseStore;
use WDM\Application\Exception\NotFoundException;
use WDM\Application\Exception\PersistenceException;
/** Coordinates the minimum warehouse management use cases. */
final class WarehouseService {
	private WarehouseStore $warehouses;
	public function __construct( WarehouseStore $warehouses ) {
		$this->warehouses = $warehouses; }
	/**
	 * Create a warehouse.
	 *
	 * @param array<string,mixed> $data Warehouse values.
	 * @return int Warehouse ID.
	 * @throws PersistenceException When persistence fails.
	 */
	public function create( array $data ): int {
		$data['status'] = $data['status'] ?? 'inactive';
		$id             = $this->warehouses->insert( $data );
		if ( $id < 1 ) {
			throw new PersistenceException( 'Warehouse could not be created.' );
		} return $id; }
	/**
	 * @return array<string,mixed> Warehouse record.
	 * @throws NotFoundException When the warehouse is missing.
	 */ public function get( int $id ): array {
		$warehouse = $this->warehouses->findById( $id );
		if ( null === $warehouse ) {
			throw new NotFoundException( 'Warehouse not found.' );
		} return $warehouse; }
	/**
	 * Update a warehouse.
	 *
	 * @param int                 $id   Warehouse ID.
	 * @param array<string,mixed> $data Warehouse values.
	 * @return array<string,mixed> Updated warehouse.
	 * @throws NotFoundException|PersistenceException When the warehouse is missing or cannot be persisted.
	 */
public function update( int $id, array $data ): array {
	$this->get( $id );
	if ( ! $this->warehouses->update( $id, $data ) ) {
		throw new PersistenceException( 'Warehouse could not be updated.' );
	} return $this->get( $id ); }
	/** @throws NotFoundException|PersistenceException When the warehouse is missing or cannot be persisted. */
public function activate( int $id ): array {
	return $this->update( $id, array( 'status' => 'active' ) ); }
	/**
	 * Deactivate a warehouse.
	 *
	 * @throws NotFoundException|PersistenceException When the warehouse is missing or cannot be persisted.
	 */
public function deactivate( int $id ): array {
	return $this->update( $id, array( 'status' => 'inactive' ) ); }
}
