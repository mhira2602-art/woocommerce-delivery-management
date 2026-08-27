<?php
/** Driver application service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use WDM\Application\Contract\DriverStore;
use WDM\Application\Exception\NotFoundException;
use WDM\Application\Exception\PersistenceException;
/** Coordinates the minimum driver management use cases. */
final class DriverService {
	private DriverStore $drivers;
	public function __construct( DriverStore $drivers ) {
		$this->drivers = $drivers; }
	/**
	 * Create a driver.
	 *
	 * @param array<string,mixed> $data Driver values.
	 * @return int Driver ID.
	 * @throws PersistenceException When persistence fails.
	 */
	public function create( array $data ): int {
		$data['status'] = $data['status'] ?? 'inactive';
		$id             = $this->drivers->insert( $data );
		if ( $id < 1 ) {
			throw new PersistenceException( 'Driver could not be created.' );
		} return $id; }
	/**
	 * @return array<string,mixed> Driver record.
	 * @throws NotFoundException When the driver is missing.
	 */ public function get( int $id ): array {
		$driver = $this->drivers->findById( $id );
		if ( null === $driver ) {
			throw new NotFoundException( 'Driver not found.' );
		} return $driver; }
	/**
	 * Update a driver.
	 *
	 * @param int                 $id   Driver ID.
	 * @param array<string,mixed> $data Driver values.
	 * @return array<string,mixed> Updated driver.
	 * @throws NotFoundException|PersistenceException When the driver is missing or cannot be persisted.
	 */
public function update( int $id, array $data ): array {
	$this->get( $id );
	if ( ! $this->drivers->update( $id, $data ) ) {
		throw new PersistenceException( 'Driver could not be updated.' );
	} return $this->get( $id ); }
	/** @throws NotFoundException|PersistenceException When the driver is missing or cannot be persisted. */
public function activate( int $id ): array {
	return $this->update( $id, array( 'status' => 'active' ) ); }
	/**
	 * Deactivate a driver.
	 *
	 * @throws NotFoundException|PersistenceException When the driver is missing or cannot be persisted.
	 */
public function deactivate( int $id ): array {
	return $this->update( $id, array( 'status' => 'inactive' ) ); }
}
