<?php
/**
 * Small admin request validation helpers.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Admin;

/**
 * Centralizes request sanitization for admin forms.
 */
final class AdminRequest {
	/**
	 * Normalize a positive integer from a request vector.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param int                 $fallback Fallback value.
	 * @return int
	 */
	public static function intParam( array $request, string $key, int $fallback = 0 ): int {
		$value = $request[ $key ] ?? $fallback;
		if ( is_string( $value ) ) {
			$value = trim( (string) wp_unslash( $value ) );
		}

		if ( is_int( $value ) ) {
			return $value > 0 ? $value : $fallback;
		}

		if ( is_string( $value ) ) {
			if ( '' === $value ) {
				return $fallback;
			}
			if ( preg_match( '/^-?\d+$/', $value ) ) {
				$int = (int) $value;
				return $int > 0 ? $int : $fallback;
			}
		}

		return $fallback;
	}

	/**
	 * Sanitize a text value.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function textParam( array $request, string $key, string $fallback = '' ): string {
		$value = $request[ $key ] ?? $fallback;
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = trim( (string) wp_unslash( $value ) );
		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = sanitize_text_field( $value );
		}

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Sanitize a date input.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @return string
	 */
	public static function dateParam( array $request, string $key ): string {
		$value = self::textParam( $request, $key );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$stamp = strtotime( $value );
			if ( false !== $stamp ) {
				return gmdate( 'Y-m-d', $stamp );
			}
		}

		return '';
	}

	/**
	 * Sanitize a float amount.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param float               $fallback Fallback value.
	 * @return float
	 */
	public static function floatParam( array $request, string $key, float $fallback = 0.0 ): float {
		$value = $request[ $key ] ?? $fallback;
		if ( is_numeric( $value ) ) {
			$float = (float) $value;
			return $float >= 0 ? $float : $fallback;
		}

		return $fallback;
	}
}
