<?php
/**
 * Plugin application bootstrap.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Application;

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
