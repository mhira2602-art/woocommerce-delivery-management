<?php
/** WooCommerce order eligibility rules. @package WDM */
declare( strict_types=1 );

namespace WDM\Integration;

/** Decides whether a WooCommerce order should trigger delivery creation. */
final class WooCommerceOrderEligibility {
	/**
	 * Check whether an order qualifies for delivery creation.
	 *
	 * @param object $order WooCommerce order or test double.
	 */
	public function isEligible( object $order ): bool {
		if ( ! is_object( $order ) ) {
			return false;
		}

		if ( method_exists( $order, 'get_status' ) ) {
			$status = strtolower( (string) $order->get_status() );
			if ( in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
				return false;
			}
		}

		if ( ! $this->hasPhysicalItems( $order ) ) {
			return false;
		}

		if ( method_exists( $order, 'needs_shipping_address' ) && ! $order->needs_shipping_address() ) {
			return false;
		}

		if ( method_exists( $order, 'has_shipping_address' ) && ! $order->has_shipping_address() ) {
			return false;
		}

		return true;
	}

	/**
	 * @param object $order WooCommerce order or test double.
	 */
	private function hasPhysicalItems( object $order ): bool {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return false;
		}

		$items = $order->get_items();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			$product = null;
			if ( is_object( $item ) && method_exists( $item, 'get_product' ) ) {
				$product = $item->get_product();
			}

			if ( null !== $product && method_exists( $product, 'is_virtual' ) && $product->is_virtual() ) {
				continue;
			}

			return true;
		}

		return false;
	}
}
