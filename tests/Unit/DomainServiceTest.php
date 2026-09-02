<?php
/** Tests for domain and application behavior. @package WDM */
declare( strict_types=1 );
namespace WDM\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WDM\Application\Contract\DeliveryStore;
use WDM\Application\Contract\DriverStore;
use WDM\Application\Contract\StatusHistoryStore;
use WDM\Application\Contract\TransactionManager;
use WDM\Application\Contract\WarehouseStore;
use WDM\Application\DeliveryDateCalculator;
use WDM\Application\DeliveryService;
use WDM\Application\DeliveryRuleService;
use WDM\Application\DriverService;
use WDM\Application\Exception\InvalidTransitionException;
use WDM\Application\Exception\NotFoundException;
use WDM\Domain\Delivery\DeliveryStatus;
/** Verifies business behavior independently of WordPress. */
final class DomainServiceTest extends TestCase {
	public function test_valid_status_change_updates_and_records_history(): void {
		$deliveries   = $this->createMock( DeliveryStore::class );
		$drivers      = $this->createMock( DriverStore::class );
		$warehouses   = $this->createMock( WarehouseStore::class );
		$history      = $this->createMock( StatusHistoryStore::class );
		$transactions = $this->createMock( TransactionManager::class );
		$record       = array(
			'id'              => 3,
			'order_id'        => 8,
			'status'          => DeliveryStatus::PENDING,
			'delivery_charge' => 0,
		);
		$updated      = array(
			'id'              => 3,
			'order_id'        => 8,
			'status'          => DeliveryStatus::SCHEDULED,
			'delivery_charge' => 0,
		);
		$deliveries->expects( $this->exactly( 2 ) )->method( 'findById' )->willReturnOnConsecutiveCalls( $record, $updated );
		$deliveries->expects( $this->once() )->method( 'update' )->with( 3, array( 'status' => DeliveryStatus::SCHEDULED ) )->willReturn( true );
		$history->expects( $this->once() )->method( 'insert' )->with( $this->arrayHasKey( 'previous_status' ) )->willReturn( 4 );
		$transactions->expects( $this->once() )->method( 'begin' );
		$transactions->expects( $this->once() )->method( 'commit' );
		$service = new DeliveryService( $deliveries, $drivers, $warehouses, $history, $transactions );
		$this->assertSame( DeliveryStatus::SCHEDULED, $service->changeStatus( 3, DeliveryStatus::SCHEDULED )->status() );
	}

	public function test_invalid_transition_is_rejected_before_writes(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$record     = array(
			'id'              => 3,
			'order_id'        => 8,
			'status'          => DeliveryStatus::PENDING,
			'delivery_charge' => 0,
		);
		$deliveries->expects( $this->once() )->method( 'findById' )->willReturn( $record );
		$deliveries->expects( $this->never() )->method( 'update' );
		$service = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$this->expectException( InvalidTransitionException::class );
		$service->changeStatus( 3, DeliveryStatus::DELIVERED );
	}

	public function test_inactive_driver_cannot_be_assigned(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$drivers    = $this->createMock( DriverStore::class );
		$deliveries->method( 'findById' )->willReturn(
			array(
				'id'              => 3,
				'order_id'        => 8,
				'status'          => DeliveryStatus::PENDING,
				'delivery_charge' => 0,
			)
		);
		$drivers->method( 'findById' )->willReturn(
			array(
				'id'     => 9,
				'status' => 'inactive',
			)
		);
		$deliveries->expects( $this->never() )->method( 'update' );
		$service = new DeliveryService( $deliveries, $drivers, $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$this->expectException( InvalidArgumentException::class );
		$service->assignDriver( 3, 9 );
	}

	public function test_missing_delivery_is_reported(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$deliveries->method( 'findById' )->willReturn( null );
		$service = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $this->createMock( WarehouseStore::class ), $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$this->expectException( NotFoundException::class );
		$service->get( 404 );
	}

	public function test_inactive_warehouse_cannot_be_assigned(): void {
		$deliveries = $this->createMock( DeliveryStore::class );
		$warehouses = $this->createMock( WarehouseStore::class );
		$deliveries->method( 'findById' )->willReturn(
			array(
				'id'              => 3,
				'order_id'        => 8,
				'status'          => DeliveryStatus::PENDING,
				'delivery_charge' => 0,
			)
		);
		$warehouses->method( 'findById' )->willReturn(
			array(
				'id'     => 11,
				'status' => 'inactive',
			)
		);
		$deliveries->expects( $this->never() )->method( 'update' );
		$service = new DeliveryService( $deliveries, $this->createMock( DriverStore::class ), $warehouses, $this->createMock( StatusHistoryStore::class ), $this->createMock( TransactionManager::class ) );
		$this->expectException( InvalidArgumentException::class );
		$service->assignWarehouse( 3, 11 );
	}

	public function test_rule_can_be_deactivated(): void {
		$rules = $this->createMock( \WDM\Application\Contract\DeliveryRuleStore::class );
		$rules->method( 'findById' )->willReturnOnConsecutiveCalls(
			array(
				'id'        => 2,
				'is_active' => 1,
			),
			array(
				'id'        => 2,
				'is_active' => 0,
			)
		);
		$rules->expects( $this->once() )->method( 'update' )->with( 2, array( 'is_active' => 0 ) )->willReturn( true );
		$this->assertSame( 0, ( new DeliveryRuleService( $rules, $this->createMock( WarehouseStore::class ) ) )->deactivate( 2 )['is_active'] );
	}

	public function test_date_calculation_is_deterministic_and_honors_cutoff(): void {
		$calculator = new DeliveryDateCalculator();
		$from       = new DateTimeImmutable( '2026-08-27 15:00:00' );
		$this->assertSame( '2026-08-30', $calculator->calculate( $from, 2, '14:00' )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-08-29', $calculator->calculate( $from, 2, '16:00' )->format( 'Y-m-d' ) );
	}

	public function test_driver_service_creates_and_deactivates_driver(): void {
		$drivers = $this->createMock( DriverStore::class );
		$drivers->expects( $this->once() )->method( 'insert' )->with( $this->arrayHasKey( 'status' ) )->willReturn( 5 );
		$drivers->method( 'findById' )->willReturnOnConsecutiveCalls(
			array(
				'id'     => 5,
				'status' => 'active',
			),
			array(
				'id'     => 5,
				'status' => 'inactive',
			)
		);
		$drivers->expects( $this->once() )->method( 'update' )->with( 5, array( 'status' => 'inactive' ) )->willReturn( true );
		$service = new DriverService( $drivers );
		$this->assertSame( 5, $service->create( array( 'name' => 'Driver' ) ) );
		$this->assertSame( 'inactive', $service->deactivate( 5 )['status'] );
	}

	public function test_driver_service_rejects_invalid_name_and_phone(): void {
		$drivers = $this->createMock( DriverStore::class );
		$service = new DriverService( $drivers );

		$this->expectException( InvalidArgumentException::class );
		$service->create(
			array(
				'name'  => 'Driver42',
				'email' => 'driver@example.com',
				'phone' => '5551234567',
			)
		);
	}

	public function test_driver_service_rejects_invalid_phone_before_persistence(): void {
		$drivers = $this->createMock( DriverStore::class );
		$drivers->expects( $this->never() )->method( 'insert' );
		$service = new DriverService( $drivers );

		$this->expectException( InvalidArgumentException::class );
		$service->create(
			array(
				'name'  => 'Driver',
				'phone' => '12345-67890',
			)
		);
	}

	public function test_driver_optional_contact_fields_and_reference_limit_are_enforced(): void {
		$drivers = $this->createMock( DriverStore::class );
		$drivers->expects( $this->once() )->method( 'insert' )->willReturn( 6 );
		$service = new DriverService( $drivers );

		$this->assertSame( 6, $service->create( array( 'name' => 'Driver' ) ) );
		$drivers->expects( $this->never() )->method( 'update' );
		$this->expectException( InvalidArgumentException::class );
		$service->create(
			array(
				'name'               => 'Driver',
				'employee_reference' => str_repeat( 'x', 101 ),
			)
		);
	}

	public function test_warehouse_region_is_optional_but_bounded(): void {
		$warehouses = $this->createMock( WarehouseStore::class );
		$warehouses->expects( $this->once() )->method( 'insert' )->willReturn( 8 );
		$service = new \WDM\Application\WarehouseService( $warehouses );

		$this->assertSame(
			8,
			$service->create(
				array(
					'name' => 'Warehouse',
					'code' => 'WH-1',
				)
			)
		);
		$this->expectException( InvalidArgumentException::class );
		$service->create(
			array(
				'name'   => 'Warehouse',
				'code'   => 'WH-1',
				'region' => str_repeat( 'x', 101 ),
			)
		);
	}

	public function test_invalid_rule_is_rejected_before_persistence(): void {
		$rules = $this->createMock( \WDM\Application\Contract\DeliveryRuleStore::class );
		$rules->expects( $this->never() )->method( 'insert' );
		$service = new DeliveryRuleService( $rules, $this->createMock( WarehouseStore::class ) );

		$this->expectException( InvalidArgumentException::class );
		$service->create( array( 'weekday' => 7 ) );
	}

	public function test_rule_with_missing_warehouse_is_rejected_before_persistence(): void {
		$rules      = $this->createMock( \WDM\Application\Contract\DeliveryRuleStore::class );
		$warehouses = $this->createMock( WarehouseStore::class );
		$rules->expects( $this->never() )->method( 'insert' );
		$warehouses->expects( $this->once() )->method( 'findById' )->with( 404 )->willReturn( null );
		$service = new DeliveryRuleService( $rules, $warehouses );

		$this->expectException( NotFoundException::class );
		$service->create( array( 'warehouse_id' => 404 ) );
	}

	public function test_warehouse_service_rejects_invalid_name_and_status(): void {
		$warehouses = $this->createMock( WarehouseStore::class );
		$service    = new \WDM\Application\WarehouseService( $warehouses );

		$this->expectException( InvalidArgumentException::class );
		$service->create(
			array(
				'name'   => 'Warehouse',
				'code'   => 'WH-1',
				'region' => 'North',
				'status' => 'unknown',
			)
		);
	}
}
