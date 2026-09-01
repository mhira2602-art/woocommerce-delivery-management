<?php
/** WooCommerce hook registration. @package WDM */
declare( strict_types=1 );

namespace WDM\Integration;

/** Registers the WooCommerce order lifecycle callbacks for the delivery application. */
final class WooCommerceHooks {
	private WooCommerceDeliveryIntegration $integration;
	private bool $registered = false;

	public function __construct( WooCommerceDeliveryIntegration $integration ) {
		$this->integration = $integration;
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'handleOrderStatusChanged' ), 10, 4 );
		}

		$this->registered = true;
	}

	/**
	 * @param mixed $order Optional WooCommerce order.
	 */
	public function handleOrderStatusChanged( int $order_id, string $from_status, string $to_status, $order = null ): void {
		$this->integration->handleOrderStatusChanged( $order_id, $from_status, $to_status, $order );
	}
}
