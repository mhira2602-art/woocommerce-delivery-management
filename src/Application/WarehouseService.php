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
		$this->validatePayload( $data );
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
	$current = $this->get( $id );
	$merged  = array_merge( $current, $data );
	$this->validatePayload( $merged, false );
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
	/**
	 * @param array<string,mixed> $data Warehouse values.
	 * @throws \InvalidArgumentException When a warehouse value is invalid.
	 */
private function validatePayload( array $data, bool $require_name = true ): void {
	$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
	if ( $require_name && '' === $name ) {
		throw new \InvalidArgumentException( 'Please enter a valid warehouse name.' );
	}
	if ( '' !== $name && ! preg_match( "~^[\p{L}\p{M}\p{N}\s&/.,()'-:]{1,190}$~u", $name ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid warehouse name.' );
	}
	$code = isset( $data['code'] ) ? strtoupper( trim( (string) $data['code'] ) ) : '';
	if ( ! preg_match( '/^[A-Z0-9-]{2,50}$/', $code ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid warehouse code.' );
	}
	$region = isset( $data['region'] ) ? trim( (string) $data['region'] ) : '';
	if ( '' !== $region && ! preg_match( '/^[\pL\pM\pN\s\'’.-]{1,100}$/u', $region ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid warehouse region.' );
	}
	$status = isset( $data['status'] ) ? strtolower( trim( (string) $data['status'] ) ) : 'inactive';
	if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
		throw new \InvalidArgumentException( 'Please select a valid status.' );
	}
	$data['status'] = $status;
	$data['code']   = $code;
	$data['name']   = $name;
	$data['region'] = $region;
}
}
