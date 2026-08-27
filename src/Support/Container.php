<?php
/**
 * Internal dependency container.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Support;

use Closure;
use RuntimeException;

/**
 * Minimal internal dependency container for shared plugin services.
 */
final class Container {
	/**
	 * Registered service entries.
	 *
	 * @var array<string, mixed>
	 */
	private array $entries = array();

	/**
	 * Resolved and cached service entries.
	 *
	 * @var array<string, mixed>
	 */
	private array $resolved = array();

	/**
	 * Register a concrete service instance or scalar value.
	 *
	 * @param string $id    Service identifier.
	 * @param mixed  $value Service value.
	 */
	public function set( string $id, $value ): void {
		$this->entries[ $id ] = $value;
		unset( $this->resolved[ $id ] );
	}

	/**
	 * Register a lazy factory that will be resolved once and cached.
	 *
	 * @param string  $id      Service identifier.
	 * @param Closure $factory Factory callback.
	 */
	public function factory( string $id, Closure $factory ): void {
		$this->entries[ $id ] = $factory;
		unset( $this->resolved[ $id ] );
	}

	/**
	 * Retrieve a service from the container.
	 *
	 * @param string $id Service identifier.
	 * @throws RuntimeException If the service is not registered.
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}

		if ( ! array_key_exists( $id, $this->entries ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The identifier is part of an exception message, not rendered output.
			throw new RuntimeException( sprintf( 'Service "%s" is not registered.', $id ) );
		}

		$entry = $this->entries[ $id ];

		if ( $entry instanceof Closure ) {
			$entry = $entry( $this );
		}

		$this->resolved[ $id ] = $entry;

		return $entry;
	}

	/**
	 * Determine whether a service has been registered.
	 *
	 * @param string $id Service identifier.
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->entries );
	}
}
