<?php
/**
 * Plugin Name: WooCommerce Delivery & Logistics Management
 * Description: Foundation for a maintainable WooCommerce delivery and logistics management plugin.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: woocommerce-delivery-management
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WDM_VERSION' ) ) {
	define( 'WDM_VERSION', '0.1.0' );
}

if ( ! defined( 'WDM_FILE' ) ) {
	define( 'WDM_FILE', __FILE__ );
}

if ( ! defined( 'WDM_PATH' ) ) {
	define( 'WDM_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WDM_URL' ) ) {
	define( 'WDM_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WDM_BASENAME' ) ) {
	define( 'WDM_BASENAME', plugin_basename( __FILE__ ) );
}

$autoload_file = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $autoload_file ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'WooCommerce Delivery & Logistics Management requires Composer dependencies. Run composer install in the plugin directory.',
				'woocommerce-delivery-management'
			);
			echo '</p></div>';
		}
	);

	return;
}

require_once $autoload_file;

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'WooCommerce Delivery & Logistics Management requires WooCommerce to be installed and active.',
						'woocommerce-delivery-management'
					);
					echo '</p></div>';
				}
			);

			return;
		}

		$container = new \WDM\Support\Container();
		$bootstrap = new \WDM\Application\Bootstrap(
			$container,
			array(
				'plugin.file' => WDM_FILE,
				'plugin.path' => WDM_PATH,
				'plugin.url' => WDM_URL,
				'plugin.basename' => WDM_BASENAME,
				'plugin.version' => WDM_VERSION,
				'text_domain' => 'woocommerce-delivery-management',
			)
		);

		$bootstrap->boot();
	}
);
