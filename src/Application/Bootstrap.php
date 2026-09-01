<?php
/**
 * Plugin application bootstrap.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Application;

use WDM\Infrastructure\Database\SchemaManager;
use WDM\Infrastructure\Database\WordPressDatabase;
use WDM\Infrastructure\Repository\DeliveryRepository;
use WDM\Infrastructure\Repository\DeliveryRuleRepository;
use WDM\Infrastructure\Repository\DeliveryStatusHistoryRepository;
use WDM\Infrastructure\Repository\DriverRepository;
use WDM\Infrastructure\Repository\WarehouseRepository;
use WDM\Integration\WooCommerceDeliveryIntegration;
use WDM\Integration\WooCommerceHooks;
use WDM\Integration\WooCommerceOrderEligibility;
use WDM\Integration\WooCommerceOrderGateway;
use WDM\Support\Container;

/**
 * Initializes the plugin foundation and registers shared configuration.
 */
final class Bootstrap {
	/**
	 * The service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Shared plugin configuration.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Create the application bootstrap.
	 *
	 * @param Container           $container Service container.
	 * @param array<string,mixed> $config    Shared plugin configuration.
	 */
	public function __construct( Container $container, array $config ) {
		$this->container = $container;
		$this->config    = $config;
	}

	/**
	 * Boot the application layer.
	 */
	public function boot(): void {
		$this->container->set( 'wdm.config', $this->config );
		$this->container->set( self::class, $this );

		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) ) {
			$database = new WordPressDatabase( $GLOBALS['wpdb'] );
			$schema   = new SchemaManager( $database );

			$this->container->set( WordPressDatabase::class, $database );
			$this->container->set( SchemaManager::class, $schema );
			$this->container->factory(
				DeliveryService::class,
				static function ( Container $container ): DeliveryService {
					return new DeliveryService( $container->get( DeliveryRepository::class ), $container->get( DriverRepository::class ), $container->get( WarehouseRepository::class ), $container->get( DeliveryStatusHistoryRepository::class ), $container->get( WordPressDatabase::class ) );
				}
			);
			$this->container->factory(
				WooCommerceOrderGateway::class,
				static function (): WooCommerceOrderGateway {
					return new WooCommerceOrderGateway();
				}
			);
			$this->container->factory(
				WooCommerceOrderEligibility::class,
				static function (): WooCommerceOrderEligibility {
					return new WooCommerceOrderEligibility();
				}
			);
			$this->container->factory(
				WooCommerceDeliveryIntegration::class,
				static function ( Container $container ): WooCommerceDeliveryIntegration {
					return new WooCommerceDeliveryIntegration( $container->get( DeliveryService::class ), $container->get( WooCommerceOrderGateway::class ), $container->get( WooCommerceOrderEligibility::class ), $container->get( DeliveryDateCalculator::class ) );
				}
			);
			$this->container->factory(
				WooCommerceHooks::class,
				static function ( Container $container ): WooCommerceHooks {
					return new WooCommerceHooks( $container->get( WooCommerceDeliveryIntegration::class ) );
				}
			);
			$this->container->factory(
				DriverService::class,
				static function ( Container $container ): DriverService {
					return new DriverService( $container->get( DriverRepository::class ) );
				}
			);
			$this->container->factory(
				WarehouseService::class,
				static function ( Container $container ): WarehouseService {
					return new WarehouseService( $container->get( WarehouseRepository::class ) );
				}
			);
			$this->container->factory(
				DeliveryRuleService::class,
				static function ( Container $container ): DeliveryRuleService {
					return new DeliveryRuleService( $container->get( DeliveryRuleRepository::class ) );
				}
			);
			$this->container->set( DeliveryDateCalculator::class, new DeliveryDateCalculator() );
			$this->container->factory(
				DeliveryRepository::class,
				static function ( Container $container ): DeliveryRepository {
					return new DeliveryRepository( $container->get( WordPressDatabase::class ) );
				}
			);
			$this->container->factory(
				DriverRepository::class,
				static function ( Container $container ): DriverRepository {
					return new DriverRepository( $container->get( WordPressDatabase::class ) );
				}
			);
			$this->container->factory(
				WarehouseRepository::class,
				static function ( Container $container ): WarehouseRepository {
					return new WarehouseRepository( $container->get( WordPressDatabase::class ) );
				}
			);
			$this->container->factory(
				DeliveryRuleRepository::class,
				static function ( Container $container ): DeliveryRuleRepository {
					return new DeliveryRuleRepository( $container->get( WordPressDatabase::class ) );
				}
			);
			$this->container->factory(
				DeliveryStatusHistoryRepository::class,
				static function ( Container $container ): DeliveryStatusHistoryRepository {
					return new DeliveryStatusHistoryRepository( $container->get( WordPressDatabase::class ) );
				}
			);
		}
	}

	/**
	 * Get the shared plugin configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return $this->config;
	}
}
