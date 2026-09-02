<?php
/** Delivery rule application service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use WDM\Application\Contract\DeliveryRuleStore;
use WDM\Application\Contract\WarehouseStore;
use WDM\Application\Exception\NotFoundException;
use WDM\Application\Exception\PersistenceException;
/** Coordinates rule persistence without evaluating rules. */
final class DeliveryRuleService {
	private DeliveryRuleStore $rules;
	private WarehouseStore $warehouses;
	public function __construct( DeliveryRuleStore $rules, WarehouseStore $warehouses ) {
		$this->rules      = $rules;
		$this->warehouses = $warehouses;
	}
	/**
	 * Create a delivery rule.
	 *
	 * @param array<string,mixed> $data Rule values.
	 * @return int Rule ID.
	 * @throws PersistenceException When persistence fails.
	 */
	public function create( array $data ): int {
		$data['is_active'] = $data['is_active'] ?? 1;
		$this->validatePayload( $data );
		$id = $this->rules->insert( $data );
		if ( $id < 1 ) {
			throw new PersistenceException( 'Delivery rule could not be created.' );
		} return $id; }
	/**
	 * @return array<string,mixed> Delivery rule.
	 * @throws NotFoundException When the rule is missing.
	 */ public function get( int $id ): array {
		$rule = $this->rules->findById( $id );
		if ( null === $rule ) {
			throw new NotFoundException( 'Delivery rule not found.' );
		} return $rule; }
	/**
	 * Update a delivery rule.
	 *
	 * @param int                 $id   Rule ID.
	 * @param array<string,mixed> $data Rule values.
	 * @return array<string,mixed> Updated rule.
	 * @throws NotFoundException|PersistenceException When the rule is missing or cannot be persisted.
	 */
public function update( int $id, array $data ): array {
	$this->get( $id );
	$this->validatePayload( $data );
	if ( ! $this->rules->update( $id, $data ) ) {
		throw new PersistenceException( 'Delivery rule could not be updated.' );
	} return $this->get( $id ); }
	/** @throws NotFoundException|PersistenceException When the rule is missing or cannot be persisted. */
public function activate( int $id ): array {
	return $this->update( $id, array( 'is_active' => 1 ) ); }
	/**
	 * Deactivate a delivery rule.
	 *
	 * @throws NotFoundException|PersistenceException When the rule is missing or cannot be persisted.
	 */
public function deactivate( int $id ): array {
	return $this->update( $id, array( 'is_active' => 0 ) ); }

	/**
	 * Validate rule values before they reach persistence.
	 *
	 * @param array<string,mixed> $data Rule values.
	 * @throws \InvalidArgumentException When a rule value is invalid.
	 * @throws NotFoundException         When a referenced warehouse is missing.
	 */
private function validatePayload( array $data ): void {
	if ( isset( $data['region'] ) && ( ! is_string( $data['region'] ) || strlen( $data['region'] ) > 100 || ( '' !== $data['region'] && ! preg_match( '~^[\p{L}\p{M}\p{N}\s.\x{27}\x{2019}-]{1,100}$~u', $data['region'] ) ) ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid rule region.' );
	}
	if ( array_key_exists( 'warehouse_id', $data ) && null !== $data['warehouse_id'] ) {
		if ( ! is_int( $data['warehouse_id'] ) || $data['warehouse_id'] < 1 ) {
			throw new \InvalidArgumentException( 'Please select a valid warehouse.' );
		}
		if ( null === $this->warehouses->findById( $data['warehouse_id'] ) ) {
			throw new NotFoundException( 'Warehouse not found.' );
		}
	}
	if ( array_key_exists( 'weekday', $data ) && null !== $data['weekday'] && ( ! is_int( $data['weekday'] ) || $data['weekday'] < 0 || $data['weekday'] > 6 ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid weekday.' );
	}
	if ( isset( $data['cutoff_time'] ) && '' !== $data['cutoff_time'] ) {
		if ( ! is_string( $data['cutoff_time'] ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $data['cutoff_time'] ) ) {
			throw new \InvalidArgumentException( 'Please enter a valid cut-off time.' );
		}
	}
	foreach ( array( 'delivery_slot', 'delivery_days' ) as $field ) {
		if ( isset( $data[ $field ] ) && ( ! is_string( $data[ $field ] ) || strlen( $data[ $field ] ) > 100 ) ) {
			throw new \InvalidArgumentException( 'Please enter valid delivery rule text.' );
		}
	}
	if ( isset( $data['priority'] ) && ( ! is_int( $data['priority'] ) || $data['priority'] < 0 || $data['priority'] > 2147483647 ) ) {
		throw new \InvalidArgumentException( 'Please enter a valid rule priority.' );
	}
	if ( isset( $data['is_active'] ) && ( ! is_int( $data['is_active'] ) || ! in_array( $data['is_active'], array( 0, 1 ), true ) ) ) {
		throw new \InvalidArgumentException( 'Please select a valid rule status.' );
	}
}
}
