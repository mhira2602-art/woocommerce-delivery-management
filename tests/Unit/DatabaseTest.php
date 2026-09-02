<?php
/**
 * Tests for database boundaries and repositories.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WDM\Infrastructure\Database\DatabaseInterface;
use WDM\Infrastructure\Database\SchemaManager;
use WDM\Infrastructure\Repository\DeliveryRepository;
use WDM\Infrastructure\Repository\DeliveryStatusHistoryRepository;
use WDM\Infrastructure\Repository\DriverRepository;
use WDM\Infrastructure\Repository\WarehouseRepository;

/**
 * Verifies SQL construction is delegated through the database boundary.
 */
final class DatabaseTest extends TestCase {
	public function test_schema_uses_configured_prefix_and_expected_indexes(): void {
		$database = new RecordingDatabase( 'custom_' );
		$schema   = new SchemaManager( $database );
		$sql      = $schema->schemaSql();

		$this->assertStringContainsString( 'custom_wdm_deliveries', $sql );
		$this->assertStringContainsString( 'KEY status_date (status, scheduled_date)', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY code (code)', $sql );
		$this->assertStringContainsString( 'KEY delivery_created (delivery_id, created_at)', $sql );
	}

	public function test_delivery_repository_inserts_and_retrieves_by_order(): void {
		$database   = new RecordingDatabase();
		$repository = new DeliveryRepository( $database );

		$id     = $repository->insert(
			array(
				'order_id' => 42,
				'status'   => 'pending',
			)
		);
		$record = $repository->findByOrderId( 42 );

		$this->assertSame( 17, $id );
		$this->assertStringContainsString( 'order_id = 42', $database->last_prepared_query );
		$this->assertSame(
			array(
				'order_id'   => 42,
				'status'     => 'pending',
				'created_at' => $database->last_insert_data['created_at'],
				'updated_at' => $database->last_insert_data['updated_at'],
			),
			$database->last_insert_data
		);
		$this->assertArrayNotHasKey( 'unrecognized_column', $database->last_insert_data );
		$this->assertSame(
			array(
				'id'       => 17,
				'order_id' => 42,
			),
			$record
		);
	}

	public function test_delivery_repository_updates_and_filters(): void {
		$database   = new RecordingDatabase();
		$repository = new DeliveryRepository( $database );

		$this->assertTrue( $repository->update( 17, array( 'status' => 'assigned' ) ) );
		$repository->findByStatus( 'assigned', 25, 10 );

		$this->assertSame( array( 'id' => 17 ), $database->last_update_where );
		$this->assertArrayHasKey( 'updated_at', $database->last_update_data );
		$this->assertStringContainsString( 'status = assigned', $database->last_prepared_query );
		$this->assertStringContainsString( 'LIMIT 25 OFFSET 10', $database->last_prepared_query );
	}

	public function test_invalid_ids_are_rejected_and_missing_records_are_null(): void {
		$database   = new RecordingDatabase();
		$repository = new DeliveryRepository( $database );

		$this->assertNull( $repository->findById( 99 ) );
		$this->expectException( InvalidArgumentException::class );
		$repository->findById( 0 );
	}

	public function test_driver_repository_searches_server_side(): void {
		$database   = new RecordingDatabase();
		$repository = new DriverRepository( $database );

		$repository->findAll( 20, 0, array( 'search' => 'smith' ) );
		$repository->countAll( array( 'search' => 'smith' ) );

		$this->assertStringContainsString( 'name LIKE %smith%', $database->last_prepared_query );
		$this->assertStringContainsString( 'email LIKE %smith%', $database->last_prepared_query );
		$this->assertStringContainsString( 'phone LIKE %smith%', $database->last_prepared_query );
	}

	public function test_warehouse_repository_searches_server_side(): void {
		$database   = new RecordingDatabase();
		$repository = new WarehouseRepository( $database );

		$repository->findAll( 20, 0, array( 'search' => 'harbor' ) );
		$repository->countAll( array( 'search' => 'harbor' ) );

		$this->assertStringContainsString( 'name LIKE %harbor%', $database->last_prepared_query );
		$this->assertStringContainsString( 'city LIKE %harbor%', $database->last_prepared_query );
		$this->assertStringContainsString( 'region LIKE %harbor%', $database->last_prepared_query );
	}

	public function test_delivery_repository_searches_by_customer_and_order_server_side(): void {
		$database   = new RecordingDatabase();
		$repository = new DeliveryRepository( $database );

		$repository->findAll(
			20,
			0,
			array(
				'status' => 'assigned',
				'search' => 'smith',
			)
		);
		$repository->countAll(
			array(
				'status' => 'assigned',
				'search' => 'smith',
			)
		);

		$this->assertStringContainsString( 'status = assigned', $database->last_prepared_query );
		$this->assertStringContainsString( 'first_name LIKE %smith%', $database->last_prepared_query );
		$this->assertStringContainsString( 'last_name LIKE %smith%', $database->last_prepared_query );
	}

	public function test_delivery_insert_requires_a_valid_order_id(): void {
		$this->expectException( InvalidArgumentException::class );
		( new DeliveryRepository( new RecordingDatabase() ) )->insert( array( 'status' => 'pending' ) );
	}

	public function test_repository_rejects_unrecognized_columns(): void {
		$this->expectException( InvalidArgumentException::class );
		( new DeliveryRepository( new RecordingDatabase() ) )->insert(
			array(
				'order_id'            => 42,
				'unrecognized_column' => 'blocked',
			)
		);
	}

	public function test_status_history_persists_and_filters_by_delivery(): void {
		$database   = new RecordingDatabase();
		$repository = new DeliveryStatusHistoryRepository( $database );

		$id   = $repository->insert(
			array(
				'delivery_id' => 17,
				'new_status'  => 'delivered',
			)
		);
		$rows = $repository->findByDeliveryId( 17 );

		$this->assertSame( 17, $id );
		$this->assertSame(
			array(
				array(
					'id'          => 18,
					'delivery_id' => 17,
				),
			),
			$rows
		);
		$this->assertStringContainsString( 'delivery_id = 17', $database->last_prepared_query );
	}
}
