<?php
/** Delivery rule application service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use WDM\Application\Contract\DeliveryRuleStore;
use WDM\Application\Exception\NotFoundException;
use WDM\Application\Exception\PersistenceException;
/** Coordinates rule persistence without evaluating rules. */
final class DeliveryRuleService {
	private DeliveryRuleStore $rules;
	public function __construct( DeliveryRuleStore $rules ) {
		$this->rules = $rules; }
	/**
	 * Create a delivery rule.
	 *
	 * @param array<string,mixed> $data Rule values.
	 * @return int Rule ID.
	 * @throws PersistenceException When persistence fails.
	 */
	public function create( array $data ): int {
		$data['is_active'] = $data['is_active'] ?? 1;
		$id                = $this->rules->insert( $data );
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
}
