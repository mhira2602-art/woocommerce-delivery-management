<?php
/** Tests for WooCommerce integration behavior. @package WDM */
declare( strict_types=1 );

namespace WDM\Tests\Unit;

require_once __DIR__ . '/../Support/WooCommerceTestObjects.php';

use PHPUnit\Framework\TestCase;
use WDM\Application\Contract\DeliveryStore;
use WDM\Application\Contract\DriverStore;
use WDM\Application\Contract\StatusHistoryStore;
use WDM\Application\Contract\TransactionManager;
use WDM\Application\Contract\WarehouseStore;
use WDM\Application\DeliveryDateCalculator;
use WDM\Application\DeliveryService;
use WDM\Domain\Delivery\DeliveryStatus;
use WDM\Integration\WooCommerceDeliveryIntegration;
use WDM\Integration\WooCommerceOrderEligibility;
use WDM\Integration\WooCommerceOrderGateway;

final class WooCommerceIntegrationTest extends TestCase {
	public function test_virtual_order_is_rejected(): void {
		$order = $this->makeOrder( 42, 'processing', false );
		$this->assertFalse( ( new WooCommerceOrderEligibility() )->isEligible( $order ) );
	}

	public function test_mixed_order_with_physical_item_is_eligible(): void {
		$order = $this->makeOrder( 42, 'processing', true, false );
		$this->assertTrue( ( new WooCommerceOrderEligibility() )->isEligible( $order ) );
	}

	public function test_shipping_address_is_mapped_for_delivery(): void {
		$order  = $this->makeOrder( 42, 'processing', true );
		$mapped = ( new WooCommerceOrderGateway() )->toDeliveryData( $order );

		$this->assertSame( 42, $mapped['order_id'] );
		$this->assertSame( 'Ada', $mapped['first_name'] );
		$this->assertSame( 'Lovelace', $mapped['last_name'] );
		$this->assertSame( '123 Example Street', $mapped['address_line_1'] );
		$this->assertSame( 'London', $mapped['city'] );
		$this->assertSame( 'SW1A 1AA', $mapped['postcode'] );
	}

	public function test_duplicate_delivery_is_not_created_again(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$deliveries->expects( $this->once() )->method( 'findByOrderId' )->with( 42 )->willReturn(
			array(
				'id'              => 99,
				'order_id'        => 42,
				'status'          => DeliveryStatus::PENDING,
				'delivery_charge' => 0,
			)
		);
		$deliveries->expects( $this->never() )->method( 'insert' );

		$order       = $this->makeOrder( 42, 'processing', true );
		$service     = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$integration = new WooCommerceDeliveryIntegration( $service, new WooCommerceOrderGateway( static fn () => $order ), new WooCommerceOrderEligibility(), new DeliveryDateCalculator() );

		$delivery = $integration->maybeCreateDeliveryForOrder( 42 );
		$this->assertNotNull( $delivery );
		$this->assertSame( 99, $delivery->id() );
	}

	public function test_eligible_order_creates_delivery(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$deliveries->method( 'findByOrderId' )->with( 42 )->willReturn( null );
		$deliveries->expects( $this->once() )->method( 'insert' )->with(
			$this->callback(
				static function ( array $data ): bool {
					return 42 === (int) $data['order_id']
						&& 'pending' === $data['status']
						&& 'Ada' === $data['first_name']
						&& '123 Example Street' === $data['address_line_1'];
				}
			)
		)->willReturn( 101 );

		$order       = $this->makeOrder( 42, 'processing', true );
		$service     = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$integration = new WooCommerceDeliveryIntegration( $service, new WooCommerceOrderGateway( static fn () => $order ), new WooCommerceOrderEligibility(), new DeliveryDateCalculator() );

		$delivery = $integration->maybeCreateDeliveryForOrder( 42 );
		$this->assertNotNull( $delivery );
		$this->assertSame( 101, $delivery->id() );
	}

	public function test_gateway_ignores_invalid_resolver_result(): void {
		$this->assertNull( ( new WooCommerceOrderGateway( static fn (): string => 'not-an-order' ) )->getOrder( 42 ) );
	}

	public function test_cancelled_woocommerce_order_cancels_existing_delivery(): void {
		$transaction = $this->createMock( TransactionManager::class );
		$transaction->expects( $this->once() )->method( 'begin' );
		$transaction->expects( $this->once() )->method( 'commit' );

		$history = $this->createMock( StatusHistoryStore::class );
		$history->expects( $this->once() )->method( 'insert' )->with(
			$this->callback(
				static function ( array $data ): bool {
					return 99 === (int) $data['delivery_id']
						&& 'pending' === $data['previous_status']
						&& 'cancelled' === $data['new_status'];
				}
			)
		)->willReturn( 1 );

		$deliveries = $this->createMock( DeliveryStore::class );
		$deliveries->expects( $this->once() )->method( 'findByOrderId' )->with( 42 )->willReturn(
			array(
				'id'              => 99,
				'order_id'        => 42,
				'status'          => 'pending',
				'delivery_charge' => 0.0,
			)
		);
		$deliveries->expects( $this->exactly( 2 ) )->method( 'findById' )->with( 99 )->willReturnOnConsecutiveCalls(
			array(
				'id'              => 99,
				'order_id'        => 42,
				'status'          => 'pending',
				'delivery_charge' => 0.0,
			),
			array(
				'id'              => 99,
				'order_id'        => 42,
				'status'          => 'cancelled',
				'delivery_charge' => 0.0,
			)
		);
		$deliveries->expects( $this->once() )->method( 'update' )->with( 99, array( 'status' => 'cancelled' ) )->willReturn( true );

		$service     = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $history, $transaction );
		$integration = new WooCommerceDeliveryIntegration( $service, new WooCommerceOrderGateway( static fn () => $this->makeOrder( 42, 'cancelled', true ) ), new WooCommerceOrderEligibility(), new DeliveryDateCalculator() );

		$integration->handleOrderStatusChanged( 42, 'processing', 'cancelled', $this->makeOrder( 42, 'cancelled', true ) );
	}

	public function test_completed_order_does_not_become_delivered_automatically(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$deliveries->expects( $this->never() )->method( 'findByOrderId' );
		$deliveries->expects( $this->never() )->method( 'insert' );

		$order       = $this->makeOrder( 42, 'completed', true );
		$service     = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$integration = new WooCommerceDeliveryIntegration( $service, new WooCommerceOrderGateway( static fn () => $order ), new WooCommerceOrderEligibility(), new DeliveryDateCalculator() );

		$integration->handleOrderStatusChanged( 42, 'processing', 'completed', $order );
	}

	private function makeOrder( int $id, string $status, bool $physical, bool $is_mixed = false ): object {
		$product = new \TestProduct( $physical );
		$item    = new \TestOrderItem( $product );

		if ( $is_mixed ) {
			$virtual_product = new \TestVirtualProduct();
			$item2           = new \TestOrderItem( $virtual_product );
			$items           = array( $item, $item2 );
		} else {
			$items = array( $item );
		}

		return new \TestOrder( $id, $status, $physical, $items );
	}
}
