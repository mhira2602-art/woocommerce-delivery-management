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
	 * Return an unmodified request value for boundary type validation.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @return mixed
	 */
	public static function rawParam( array $request, string $key ) {
		return $request[ $key ] ?? null;
	}

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
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
			if ( false !== $date && $date->format( 'Y-m-d' ) === $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Validate and normalize a person name.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function nameParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		if ( preg_match( '/\d/', $value ) ) {
			return $fallback;
		}

		if ( ! preg_match( '~^[\p{L}\p{M}\s.\x{27}\x{2019}-]{1,190}$~u', $value ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Validate and normalize an email address.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function emailParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		$value = strtolower( $value );
		return false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $fallback : $value;
	}

	/**
	 * Validate and normalize a 10-digit phone number.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function phoneParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		if ( ! preg_match( '/^\d{10}$/D', $value ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Validate a warehouse-style label.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function labelParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		if ( preg_match( '/^\d+$/', $value ) ) {
			return $fallback;
		}

		if ( ! preg_match( "~^[\p{L}\p{M}\p{N}\s&/.,()'-:]{1,190}$~u", $value ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Validate a location-like field such as city or region.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function locationParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		if ( preg_match( '/^\d+$/', $value ) ) {
			return $fallback;
		}

		if ( ! preg_match( '~^[\p{L}\p{M}\p{N}\s.\x{27}\x{2019}-]{1,100}$~u', $value ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Validate a driver employee/reference value.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function referenceParam( array $request, string $key, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		return strlen( $value ) > 100 ? $fallback : $value;
	}

	/**
	 * Validate and normalize a status value from a whitelist.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param array<int,string>   $allowed Allowed values.
	 * @param string              $fallback Fallback value.
	 * @return string
	 */
	public static function statusParam( array $request, string $key, array $allowed, string $fallback = '' ): string {
		$value = self::textParam( $request, $key, $fallback );
		if ( '' === $value ) {
			return $fallback;
		}

		$value = sanitize_key( $value );
		if ( ! in_array( $value, $allowed, true ) ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Validate and normalize an admin search term.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param int                 $max_length Maximum safe search length.
	 * @return string
	 */
	public static function searchParam( array $request, string $key, int $max_length = 100 ): string {
		$value = self::textParam( $request, $key, '' );
		if ( '' === $value ) {
			return '';
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( $length > $max_length ) {
			return '';
		}

		return $value;
	}

	/**
	 * Validate and normalize a page number used in admin list screens.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param int                 $fallback Fallback value.
	 * @return int
	 */
	public static function pageParam( array $request, string $key, int $fallback = 1 ): int {
		$page = self::intParam( $request, $key, $fallback );
		return min( 100000, max( 1, $page ) );
	}

	/**
	 * Parse an integer without accepting decimals or arbitrary strings.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param int|null            $fallback Fallback value.
	 * @return int|null
	 */
	public static function integerParam( array $request, string $key, ?int $fallback = null ): ?int {
		if ( ! array_key_exists( $key, $request ) || ! is_scalar( $request[ $key ] ) ) {
			return $fallback;
		}

		$value = trim( (string) wp_unslash( $request[ $key ] ) );
		if ( '' === $value || ! preg_match( '/^-?\d+$/D', $value ) ) {
			return $fallback;
		}

		return (int) $value;
	}

	/**
	 * Validate an optional time value in the database-compatible formats.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @return string
	 */
	public static function timeParam( array $request, string $key ): string {
		$value = self::textParam( $request, $key );
		if ( '' === $value ) {
			return '';
		}

		foreach ( array( 'H:i', 'H:i:s' ) as $format ) {
			$time = \DateTimeImmutable::createFromFormat( '!' . $format, $value );
			if ( false !== $time && $time->format( $format ) === $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Validate and normalize a per-page value used in admin list screens.
	 *
	 * @param array<string,mixed> $request Request array.
	 * @param string              $key     Request key.
	 * @param int                 $fallback Fallback value.
	 * @param int                 $max_value Maximum allowed per-page size.
	 * @return int
	 */
	public static function perPageParam( array $request, string $key, int $fallback = 20, int $max_value = 100 ): int {
		$value = self::intParam( $request, $key, $fallback );
		if ( $value < 1 ) {
			return $fallback;
		}

		return min( $value, $max_value );
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
