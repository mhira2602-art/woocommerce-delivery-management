<?php
/**
 * Tests for the internal dependency container.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Tests\Unit;

use RuntimeException;
use WDM\Support\Container;
use PHPUnit\Framework\TestCase;

/**
 * Verifies container registration and resolution behavior.
 */
final class ContainerTest extends TestCase {
	/**
	 * Values can be registered and retrieved.
	 */
	public function test_it_registers_and_retrieves_a_value(): void {
		$container = new Container();
		$value     = new \stdClass();

		$container->set( 'service', $value );

		$this->assertSame( $value, $container->get( 'service' ) );
	}

	/**
	 * Factories can be registered and resolved.
	 */
	public function test_it_registers_and_resolves_a_factory(): void {
		$container = new Container();
		$value     = new \stdClass();

		$container->factory(
			'service',
			static function () use ( $value ): \stdClass {
				return $value;
			}
		);

		$this->assertSame( $value, $container->get( 'service' ) );
	}

	/**
	 * Factory results are resolved only once.
	 */
	public function test_it_caches_a_factory_result(): void {
		$container     = new Container();
		$factory_calls = 0;

		$container->factory(
			'service',
			static function () use ( &$factory_calls ): \stdClass {
				++$factory_calls;

				return new \stdClass();
			}
		);

		$first  = $container->get( 'service' );
		$second = $container->get( 'service' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $factory_calls );
	}

	/**
	 * The container reports registered service identifiers.
	 */
	public function test_it_reports_registered_services(): void {
		$container = new Container();

		$this->assertFalse( $container->has( 'service' ) );

		$container->set( 'service', 'value' );

		$this->assertTrue( $container->has( 'service' ) );
	}

	/**
	 * Missing services produce a useful exception.
	 */
	public function test_it_throws_when_a_service_is_missing(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Service "missing" is not registered.' );

		$container->get( 'missing' );
	}
}
