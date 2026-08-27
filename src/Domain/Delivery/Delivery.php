<?php
/** Delivery domain model. @package WDM */
declare( strict_types=1 );
namespace WDM\Domain\Delivery;

use InvalidArgumentException;
/** WordPress-independent delivery record. */
final class Delivery {
	/** @var array<string,mixed> */
	private array $attributes;
	/**
	 * @param array<string,mixed> $attributes Delivery values.
	 * @throws InvalidArgumentException When core delivery values are invalid.
	 */
	public function __construct( array $attributes ) {
		$order_id = isset( $attributes['order_id'] ) ? (int) $attributes['order_id'] : 0;
		if ( $order_id < 1 ) {
			throw new InvalidArgumentException( 'A valid WooCommerce order ID is required.' ); }
		$status = isset( $attributes['status'] ) ? (string) $attributes['status'] : DeliveryStatus::PENDING;
		DeliveryStatus::assertValid( $status );
		$charge = isset( $attributes['delivery_charge'] ) ? (float) $attributes['delivery_charge'] : 0.0;
		if ( $charge < 0 ) {
			throw new InvalidArgumentException( 'Delivery charge cannot be negative.' ); }
		$this->attributes                    = $attributes;
		$this->attributes['order_id']        = $order_id;
		$this->attributes['status']          = $status;
		$this->attributes['delivery_charge'] = $charge;
	}
	/** @return array<string,mixed> */
	public function toArray(): array {
		return $this->attributes; }
	public function status(): string {
		return (string) $this->attributes['status']; }
	public function id(): int {
		return isset( $this->attributes['id'] ) ? (int) $this->attributes['id'] : 0; }
}
