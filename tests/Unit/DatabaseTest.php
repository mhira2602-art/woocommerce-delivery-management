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
