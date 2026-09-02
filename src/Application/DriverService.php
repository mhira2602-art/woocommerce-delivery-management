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
		$this->validatePayload( $data );
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
	$current = $this->get( $id );
	$merged  = array_merge( $current, $data );
	$this->validatePayload( $merged, false );
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
	/**
	 * @param array<string,mixed> $data Driver values.
	 * @throws \InvalidArgumentException When a driver value is invalid.
	 */
private function validatePayload( array $data, bool $require_name = true ): void {
	$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
	if ( $require_name && '' === $name ) {
		throw new \InvalidArgumentException( 'Please enter a valid driver name.' );
	}
	if ( '' !== $name && preg_match( '/\d/', $name ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid driver name.' );
	}
	if ( '' !== $name && ! preg_match( '~^[\p{L}\p{M}\s.\x{27}\x{2019}-]{1,190}$~u', $name ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid driver name.' );
	}
	$email = isset( $data['email'] ) ? strtolower( trim( (string) $data['email'] ) ) : '';
	if ( strlen( $email ) > 190 || ( '' !== $email && false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid email address.' );
	}
	$phone = isset( $data['phone'] ) ? trim( (string) $data['phone'] ) : '';
	if ( '' !== $phone && ! preg_match( '/^\d{10}$/D', $phone ) ) {
		throw new \InvalidArgumentException( 'Phone number must contain exactly 10 digits.' );
	}
	$reference = isset( $data['employee_reference'] ) ? trim( (string) $data['employee_reference'] ) : '';
	if ( strlen( $reference ) > 100 ) {
		throw new \InvalidArgumentException( 'Please enter a valid employee reference.' );
	}
	$status = isset( $data['status'] ) ? strtolower( trim( (string) $data['status'] ) ) : 'inactive';
	if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
		throw new \InvalidArgumentException( 'Please select a valid status.' );
	}
	$data['status'] = $status;
}
}
