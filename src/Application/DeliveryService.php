<?php
/** Delivery application service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use InvalidArgumentException;
use WDM\Application\Contract\DeliveryStore;
use WDM\Application\Contract\DriverStore;
use WDM\Application\Contract\StatusHistoryStore;
use WDM\Application\Contract\TransactionManager;
use WDM\Application\Contract\WarehouseStore;
use WDM\Application\Exception\InvalidTransitionException;
use WDM\Application\Exception\NotFoundException;
use WDM\Application\Exception\PersistenceException;
use WDM\Domain\Delivery\Delivery;
use WDM\Domain\Delivery\DeliveryStatus;
/** Coordinates delivery use cases and domain behavior. */
final class DeliveryService {
	private DeliveryStore $deliveries;
	private DriverStore $drivers;
	private WarehouseStore $warehouses;
	private StatusHistoryStore $history;
	private TransactionManager $transactions;
	public function __construct( DeliveryStore $deliveries, DriverStore $drivers, WarehouseStore $warehouses, StatusHistoryStore $history, TransactionManager $transactions ) {
		$this->deliveries   = $deliveries;
		$this->drivers      = $drivers;
		$this->warehouses   = $warehouses;
		$this->history      = $history;
		$this->transactions = $transactions; }
	/**
	 * Create a delivery.
	 *
	 * @param array<string,mixed> $data Delivery values.
	 * @throws InvalidArgumentException|PersistenceException When validation or persistence fails.
	 */
	public function create( array $data ): Delivery {
		$delivery = new Delivery( $data );
		$id       = $this->deliveries->insert( $delivery->toArray() );
		if ( $id < 1 ) {
			throw new PersistenceException( 'Delivery could not be created.' );
		} $data['id'] = $id;
		return new Delivery( $data ); }
	/** @throws NotFoundException When the delivery is missing. */
	public function get( int $id ): Delivery {
		$record = $this->deliveries->findById( $id );
		if ( null === $record ) {
			throw new NotFoundException( 'Delivery not found.' );
		} return new Delivery( $record ); }
	/** @return Delivery|null */
	public function findByOrderId( int $order_id ): ?Delivery {
		$record = $this->deliveries->findByOrderId( $order_id );
		if ( null === $record ) {
			return null;
		}
		return new Delivery( $record ); }
	/**
	 * Update a delivery.
	 *
	 * @param int                 $id   Delivery ID.
	 * @param array<string,mixed> $data Delivery values.
	 * @throws InvalidArgumentException When the update is invalid.
	 * @throws PersistenceException When persistence fails.
	 */
	public function update( int $id, array $data ): Delivery {
		$current = $this->get( $id );
		if ( isset( $data['status'] ) && $data['status'] !== $current->status() ) {
			throw new InvalidArgumentException( 'Use changeStatus to change delivery status.' );
		} $updated = array_merge( $current->toArray(), $data );
		$updated   = ( new Delivery( $updated ) )->toArray();
		if ( ! $this->deliveries->update( $id, $data ) ) {
			throw new PersistenceException( 'Delivery could not be updated.' );
		} return new Delivery( $updated ); }
	/**
	 * Change status and append an audit record atomically.
	 *
	 * @throws InvalidTransitionException When the transition is not allowed.
	 * @throws PersistenceException When either write fails.
	 * @throws \Throwable When the transaction must be rolled back.
	 */
	public function changeStatus( int $id, string $status, ?int $actor_user_id = null, string $note = '' ): Delivery {
		$current = $this->get( $id );
		DeliveryStatus::assertValid( $status );
		if ( ! DeliveryStatus::canTransition( $current->status(), $status ) ) {
			throw new InvalidTransitionException( 'Delivery status transition is not allowed.' );
		} $this->transactions->begin();
		try {
			if ( ! $this->deliveries->update( $id, array( 'status' => $status ) ) ) {
				throw new PersistenceException( 'Delivery status could not be updated.' );
			} $history = array(
				'delivery_id'     => $id,
				'previous_status' => $current->status(),
				'new_status'      => $status,
				'note'            => $note,
			);
			if ( null !== $actor_user_id ) {
				$history['actor_user_id'] = $actor_user_id;
			} if ( $this->history->insert( $history ) < 1 ) {
				throw new PersistenceException( 'Delivery status history could not be recorded.' );
			} $this->transactions->commit();
		} catch ( \Throwable $exception ) {
			$this->transactions->rollback();
			throw $exception;
		} return $this->get( $id ); }
	public function assignDriver( int $id, int $driver_id ): Delivery {
		$this->get( $id );
		$driver = $this->drivers->findById( $driver_id );
		if ( null === $driver ) {
			throw new NotFoundException( 'Driver not found.' );
		} if ( 'active' !== (string) ( $driver['status'] ?? '' ) ) {
			throw new InvalidArgumentException( 'Inactive drivers cannot be assigned.' );
		} if ( ! $this->deliveries->update( $id, array( 'driver_id' => $driver_id ) ) ) {
			throw new PersistenceException( 'Driver assignment failed.' );
		} return $this->get( $id ); }
	public function assignWarehouse( int $id, int $warehouse_id ): Delivery {
		$this->get( $id );
		$warehouse = $this->warehouses->findById( $warehouse_id );
		if ( null === $warehouse ) {
			throw new NotFoundException( 'Warehouse not found.' );
		} if ( 'active' !== (string) ( $warehouse['status'] ?? '' ) ) {
			throw new InvalidArgumentException( 'Inactive warehouses cannot be assigned.' );
		} if ( ! $this->deliveries->update( $id, array( 'warehouse_id' => $warehouse_id ) ) ) {
			throw new PersistenceException( 'Warehouse assignment failed.' );
		} return $this->get( $id ); }
}
