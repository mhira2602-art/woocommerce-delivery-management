<?php
/** WooCommerce delivery creation integration. @package WDM */
declare( strict_types=1 );

namespace WDM\Integration;

use DateTimeImmutable;
use WDM\Application\DeliveryDateCalculator;
use WDM\Application\DeliveryService;
use WDM\Domain\Delivery\Delivery;
use WDM\Domain\Delivery\DeliveryStatus;

/** Coordinates WooCommerce order events into the delivery application layer. */
final class WooCommerceDeliveryIntegration {
	private DeliveryService $delivery_service;
	private WooCommerceOrderGateway $order_gateway;
	private WooCommerceOrderEligibility $eligibility;
	private DeliveryDateCalculator $date_calculator;

	public function __construct( DeliveryService $delivery_service, WooCommerceOrderGateway $order_gateway, WooCommerceOrderEligibility $eligibility, DeliveryDateCalculator $date_calculator ) {
		$this->delivery_service = $delivery_service;
		$this->order_gateway    = $order_gateway;
		$this->eligibility      = $eligibility;
		$this->date_calculator  = $date_calculator;
	}

	/**
	 * Create a delivery if the WooCommerce order is eligible and no delivery exists yet.
	 *
	 * @return Delivery|null
	 */
	public function maybeCreateDeliveryForOrder( int $order_id ): ?Delivery {
		if ( $order_id < 1 ) {
			return null;
		}

		$order = $this->order_gateway->getOrder( $order_id );
		if ( null === $order ) {
			return null;
		}

		if ( ! $this->eligibility->isEligible( $order ) ) {
			return null;
		}

		$existing = $this->delivery_service->findByOrderId( $order_id );
		if ( null !== $existing ) {
			return $existing;
		}

		$data                    = $this->order_gateway->toDeliveryData( $order );
		$data['status']          = DeliveryStatus::PENDING;
		$data['scheduled_date']  = $this->date_calculator->calculate( new DateTimeImmutable( 'now' ), 2 )->format( 'Y-m-d' );
		$data['estimated_date']  = $this->date_calculator->calculate( new DateTimeImmutable( 'now' ), 2 )->format( 'Y-m-d' );
		$data['delivery_charge'] = 0.0;

		return $this->delivery_service->create( $data );
	}

	/**
	 * @param mixed $order Optional WooCommerce order instance.
	 */
	public function handleOrderStatusChanged( int $order_id, string $from_status, string $to_status, $order = null ): void {
		unset( $from_status );
		$normalized_to = strtolower( $to_status );

		if ( 'completed' === $normalized_to ) {
			return;
		}

		if ( in_array( $normalized_to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			$delivery = $this->delivery_service->findByOrderId( $order_id );
			if ( null !== $delivery ) {
				$this->delivery_service->changeStatus( $delivery->id(), DeliveryStatus::CANCELLED, null, 'WooCommerce order status changed to ' . $to_status );
			}
			return;
		}

		if ( ! in_array( $normalized_to, array( 'pending', 'processing', 'on-hold' ), true ) ) {
			return;
		}

		if ( null !== $order && is_object( $order ) ) {
			if ( ! $this->eligibility->isEligible( $order ) ) {
				return;
			}
			if ( null !== $this->delivery_service->findByOrderId( $order_id ) ) {
				return;
			}
			$this->maybeCreateDeliveryForOrder( $order_id );
			return;
		}

		$this->maybeCreateDeliveryForOrder( $order_id );
	}
}
