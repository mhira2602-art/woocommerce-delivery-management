<?php
/** WooCommerce order access gateway. @package WDM */
declare( strict_types=1 );

namespace WDM\Integration;

use DateTimeImmutable;

/** Small adapter for WooCommerce order reads used by the application. */
final class WooCommerceOrderGateway {
	/** @var callable|null */
	private $resolver;

	/**
	 * @param callable|null $resolver Optional resolver used in tests or custom integrations.
	 */
	public function __construct( ?callable $resolver = null ) {
		$this->resolver = $resolver;
	}

	/**
	 * Read a WooCommerce order by ID using the public API.
	 *
	 * @return object|null
	 */
	public function getOrder( int $order_id ) {
		if ( $order_id < 1 ) {
			return null;
		}

		if ( is_callable( $this->resolver ) ) {
			$order = call_user_func( $this->resolver, $order_id );
			return is_object( $order ) && method_exists( $order, 'get_id' ) ? $order : null;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return is_object( $order ) && method_exists( $order, 'get_id' ) ? $order : null;
	}

	/**
	 * Convert the order into the operational delivery payload used by DeliveryService.
	 *
	 * @param WC_Order|object $order WooCommerce order object.
	 * @return array<string,mixed>
	 */
	public function toDeliveryData( $order ): array {
		if ( ! is_object( $order ) ) {
			return array(
				'order_id'        => 0,
				'first_name'      => '',
				'last_name'       => '',
				'company'         => '',
				'address_line_1'  => '',
				'address_line_2'  => '',
				'city'            => '',
				'state'           => '',
				'postcode'        => '',
				'country'         => '',
				'phone'           => '',
				'delivery_charge' => 0.0,
				'status'          => 'pending',
			);
		}

		return array(
			'order_id'        => (int) $order->get_id(),
			'first_name'      => (string) $this->getShippingValue( $order, 'get_shipping_first_name' ),
			'last_name'       => (string) $this->getShippingValue( $order, 'get_shipping_last_name' ),
			'company'         => (string) $this->getShippingValue( $order, 'get_shipping_company' ),
			'address_line_1'  => (string) $this->getShippingValue( $order, 'get_shipping_address_1' ),
			'address_line_2'  => (string) $this->getShippingValue( $order, 'get_shipping_address_2' ),
			'city'            => (string) $this->getShippingValue( $order, 'get_shipping_city' ),
			'state'           => (string) $this->getShippingValue( $order, 'get_shipping_state' ),
			'postcode'        => (string) $this->getShippingValue( $order, 'get_shipping_postcode' ),
			'country'         => (string) $this->getShippingValue( $order, 'get_shipping_country' ),
			'phone'           => (string) $this->getShippingValue( $order, 'get_shipping_phone' ),
			'delivery_charge' => 0.0,
			'status'          => 'pending',
		);
	}

	/**
	 * @param WC_Order|object $order WooCommerce order object.
	 * @param string          $method Method name.
	 */
	private function getShippingValue( $order, string $method ): string {
		if ( ! is_object( $order ) || ! method_exists( $order, $method ) ) {
			return '';
		}

		$value = $order->{$method}();

		return is_scalar( $value ) ? (string) $value : '';
	}
}
