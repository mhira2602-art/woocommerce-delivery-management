<?php
/**
 * WordPress capability helpers for the admin UI.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Admin;

/**
 * Minimal capability layer for the plugin admin screens.
 */
final class AdminCapabilities {
	/**
	 * The custom capabilities exposed by the plugin.
	 *
	 * @var array<int,string>
	 */
	private const CAPABILITIES = array(
		'wdm_view_deliveries',
		'wdm_manage_deliveries',
		'wdm_manage_drivers',
		'wdm_manage_warehouses',
		'wdm_manage_delivery_rules',
	);

	/**
	 * Register the plugin capability mapping.
	 */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'mapMetaCap' ), 10, 4 );
	}

	/**
	 * Grant plugin caps to administrators by default.
	 *
	 * @param array<int,string> $caps    The capabilities to return.
	 * @param string            $cap     Capability being checked.
	 * @param int               $user_id User ID.
	 * @param array<int,mixed>  $args    Additional arguments.
	 * @return array<int,string>
	 */
	public static function mapMetaCap( array $caps, string $cap, int $user_id, array $args ): array {
		unset( $args );

		if ( ! in_array( $cap, self::CAPABILITIES, true ) ) {
			return $caps;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'do_not_allow' );
		}

		$roles = $user->roles;
		if ( in_array( 'administrator', $roles, true ) ) {
			return array( 'exist' );
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return array( 'exist' );
		}

		if ( user_can( $user, $cap ) ) {
			return array( 'exist' );
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Check whether the current user has a plugin capability.
	 *
	 * @param string $cap Capability to check.
	 * @return bool
	 */
	public static function currentUserCan( string $cap ): bool {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		if ( $user->has_cap( 'manage_options' ) ) {
			return true;
		}

		return $user->has_cap( $cap );
	}
}
