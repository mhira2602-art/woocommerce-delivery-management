<?php
/**
 * WordPress admin bootstrap for the delivery management screens.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Admin;

use WDM\Application\DeliveryService;
use WDM\Application\DriverService;
use WDM\Application\WarehouseService;
use WDM\Application\DeliveryRuleService;
use WDM\Support\Container;

/**
 * Registers the WordPress admin hooks and handles mutation requests.
 */
final class AdminBootstrap {
	/**
	 * The service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * @param Container $container Shared plugin DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register the admin menu and request handlers.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_post_wdm_change_delivery_status', array( $this, 'changeDeliveryStatus' ) );
		add_action( 'admin_post_wdm_assign_driver', array( $this, 'assignDriver' ) );
		add_action( 'admin_post_wdm_assign_warehouse', array( $this, 'assignWarehouse' ) );
		add_action( 'admin_post_wdm_save_driver', array( $this, 'saveDriver' ) );
		add_action( 'admin_post_wdm_save_warehouse', array( $this, 'saveWarehouse' ) );
		add_action( 'admin_post_wdm_save_rule', array( $this, 'saveRule' ) );
		add_action( 'admin_notices', array( $this, 'renderNotice' ) );
	}

	/**
	 * Register the plugin menu and subpages.
	 */
	public function registerMenu(): void {
		if ( ! AdminCapabilities::currentUserCan( 'wdm_view_deliveries' ) ) {
			return;
		}

		if ( ! empty( $GLOBALS['admin_page_hooks']['wdm-delivery-management'] ) ) {
			return;
		}

		add_menu_page(
			__( 'Delivery Management', 'woocommerce-delivery-management' ),
			__( 'Delivery Management', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management',
			array( $this, 'renderDashboard' ),
			'dashicons-clipboard',
			26
		);

		add_submenu_page(
			'wdm-delivery-management',
			__( 'Dashboard', 'woocommerce-delivery-management' ),
			__( 'Dashboard', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management',
			array( $this, 'renderDashboard' )
		);

		add_submenu_page(
			'wdm-delivery-management',
			__( 'Deliveries', 'woocommerce-delivery-management' ),
			__( 'Deliveries', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management-deliveries',
			array( $this, 'renderDeliveries' )
		);

		add_submenu_page(
			'wdm-delivery-management',
			__( 'Drivers', 'woocommerce-delivery-management' ),
			__( 'Drivers', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management-drivers',
			array( $this, 'renderDrivers' )
		);

		add_submenu_page(
			'wdm-delivery-management',
			__( 'Warehouses', 'woocommerce-delivery-management' ),
			__( 'Warehouses', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management-warehouses',
			array( $this, 'renderWarehouses' )
		);

		add_submenu_page(
			'wdm-delivery-management',
			__( 'Delivery Rules', 'woocommerce-delivery-management' ),
			__( 'Delivery Rules', 'woocommerce-delivery-management' ),
			'manage_options',
			'wdm-delivery-management-rules',
			array( $this, 'renderRules' )
		);
	}

	/**
	 * Render the dashboard.
	 */
	public function renderDashboard(): void {
		AdminPages::dashboard();
	}

	/**
	 * Render the deliveries list.
	 */
	public function renderDeliveries(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page filter actions are not state-changing.
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_string( $action ) && 'view' === $action ) {
			AdminPages::deliveryDetail();
			return;
		}

		AdminPages::deliveries();
	}

	/**
	 * Render driver management.
	 */
	public function renderDrivers(): void {
		AdminPages::drivers();
	}

	/**
	 * Render warehouse management.
	 */
	public function renderWarehouses(): void {
		AdminPages::warehouses();
	}

	/**
	 * Render rule management.
	 */
	public function renderRules(): void {
		AdminPages::rules();
	}

	/**
	 * Change a delivery status using the domain service.
	 */
	public function changeDeliveryStatus(): void {
		$this->authorize( 'wdm_manage_deliveries', 'wdm_change_delivery_status' );

		$id     = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( $id < 1 || '' === $status ) {
			$this->redirectWithNotice( 'wdm-delivery-management-deliveries', 'Invalid delivery status request.', false );
		}

		try {
			$this->deliveryService()->changeStatus( $id, $status, get_current_user_id(), 'Updated from WordPress admin.' );
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Delivery status updated.',
				true,
				array(
					'action' => 'view',
					'id'     => $id,
				)
			);
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Delivery status could not be updated.',
				false,
				array(
					'action' => 'view',
					'id'     => $id,
				)
			);
		}
	}

	/**
	 * Assign a driver to a delivery.
	 */
	public function assignDriver(): void {
		$this->authorize( 'wdm_manage_deliveries', 'wdm_assign_driver' );

		$delivery_id = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		$driver_id   = AdminRequest::intParam( $_POST, 'driver_id', 0 );
		if ( $delivery_id < 1 || $driver_id < 1 ) {
			$this->redirectWithNotice( 'wdm-delivery-management-deliveries', 'A valid delivery and driver are required.', false );
		}

		try {
			$this->deliveryService()->assignDriver( $delivery_id, $driver_id );
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Driver assigned successfully.',
				true,
				array(
					'action' => 'view',
					'id'     => $delivery_id,
				)
			);
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Driver assignment failed.',
				false,
				array(
					'action' => 'view',
					'id'     => $delivery_id,
				)
			);
		}
	}

	/**
	 * Assign a warehouse to a delivery.
	 */
	public function assignWarehouse(): void {
		$this->authorize( 'wdm_manage_deliveries', 'wdm_assign_warehouse' );

		$delivery_id  = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		$warehouse_id = AdminRequest::intParam( $_POST, 'warehouse_id', 0 );
		if ( $delivery_id < 1 || $warehouse_id < 1 ) {
			$this->redirectWithNotice( 'wdm-delivery-management-deliveries', 'A valid delivery and warehouse are required.', false );
		}

		try {
			$this->deliveryService()->assignWarehouse( $delivery_id, $warehouse_id );
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Warehouse assigned successfully.',
				true,
				array(
					'action' => 'view',
					'id'     => $delivery_id,
				)
			);
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice(
				'wdm-delivery-management-deliveries',
				'Warehouse assignment failed.',
				false,
				array(
					'action' => 'view',
					'id'     => $delivery_id,
				)
			);
		}
	}

	/**
	 * Save or update a driver.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize().
	public function saveDriver(): void {
		$this->authorize( 'wdm_manage_drivers', 'wdm_save_driver' );

		$post_email = filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL );
		$post_status = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$id          = AdminRequest::intParam( $_POST, 'id', 0 );
		$data        = array(
			'name'               => AdminRequest::textParam( $_POST, 'name' ),
			'email'              => false === $post_email || null === $post_email ? '' : sanitize_email( wp_unslash( $post_email ) ),
			'phone'              => AdminRequest::textParam( $_POST, 'phone' ),
			'employee_reference' => AdminRequest::textParam( $_POST, 'employee_reference' ),
			'status'             => false === $post_status || null === $post_status ? 'inactive' : sanitize_key( wp_unslash( $post_status ) ),
		);

		try {
			if ( $id > 0 ) {
				$this->driverService()->update( $id, $data );
				$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Driver updated.', true );
			}
			$this->driverService()->create( $data );
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Driver created.', true );
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Driver could not be saved.', false );
		}
	}

	/**
	 * Save or update a warehouse.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize().
	public function saveWarehouse(): void {
		$this->authorize( 'wdm_manage_warehouses', 'wdm_save_warehouse' );

		$post_status = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$id          = AdminRequest::intParam( $_POST, 'id', 0 );
		$data        = array(
			'name'   => AdminRequest::textParam( $_POST, 'name' ),
			'code'   => strtoupper( AdminRequest::textParam( $_POST, 'code' ) ),
			'region' => AdminRequest::textParam( $_POST, 'region' ),
			'status' => false === $post_status || null === $post_status ? 'inactive' : sanitize_key( wp_unslash( $post_status ) ),
		);

		try {
			if ( $id > 0 ) {
				$this->warehouseService()->update( $id, $data );
				$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Warehouse updated.', true );
			}
			$this->warehouseService()->create( $data );
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Warehouse created.', true );
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Warehouse could not be saved.', false );
		}
	}

	/**
	 * Save or update a delivery rule.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize().
	public function saveRule(): void {
		$this->authorize( 'wdm_manage_delivery_rules', 'wdm_save_rule' );

		$post_cutoff = filter_input( INPUT_POST, 'cutoff_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_active = filter_input( INPUT_POST, 'is_active', FILTER_SANITIZE_NUMBER_INT );
		$id          = AdminRequest::intParam( $_POST, 'id', 0 );
		$data        = array(
			'region'        => AdminRequest::textParam( $_POST, 'region' ),
			'warehouse_id'  => AdminRequest::intParam( $_POST, 'warehouse_id', 0 ),
			'weekday'       => AdminRequest::intParam( $_POST, 'weekday', 0 ),
			'cutoff_time'   => false === $post_cutoff || null === $post_cutoff ? '' : sanitize_text_field( wp_unslash( $post_cutoff ) ),
			'delivery_slot' => AdminRequest::textParam( $_POST, 'delivery_slot' ),
			'delivery_days' => AdminRequest::textParam( $_POST, 'delivery_days' ),
			'priority'      => AdminRequest::intParam( $_POST, 'priority', 0 ),
			'is_active'     => false === $post_active || null === $post_active ? 1 : (int) $post_active,
		);

		try {
			if ( $id > 0 ) {
				$this->ruleService()->update( $id, $data );
				$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Delivery rule updated.', true );
			}
			$this->ruleService()->create( $data );
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Delivery rule created.', true );
		} catch ( \Throwable $exception ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Delivery rule could not be saved.', false );
		}
	}

	/**
	 * Display a notice stored in the query string.
	 */
	public function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin notice data is read-only and not state-changing.
		$notice = filter_input( INPUT_GET, 'wdm_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( false === $notice || null === $notice ) {
			return;
		}

		$type_value = filter_input( INPUT_GET, 'wdm_success', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$type       = '1' === $type_value ? 'notice-success' : 'notice-error';
		$notice     = sanitize_text_field( wp_unslash( $notice ) );
		if ( '' === $notice ) {
			return;
		}

		echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	/**
	 * Redirect with an admin notice.
	 *
	 * @param string               $page    Target page slug.
	 * @param string               $message User-facing message.
	 * @param bool                 $success Whether the action succeeded.
	 * @param array<string,mixed>  $extra   Extra query arguments.
	 */
	private function redirectWithNotice( string $page, string $message, bool $success = true, array $extra = array() ): void {
		$args = array(
			'page'        => $page,
			'wdm_notice'  => $message,
			'wdm_success' => $success ? '1' : '0',
		);
		foreach ( $extra as $key => $value ) {
			$args[ $key ] = $value;
		}

		$location = admin_url( 'admin.php?' . http_build_query( $args ) );
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Perform capability and nonce checks for a mutating request.
	 *
	 * @param string $capability Capability required.
	 * @param string $nonce_action Nonce action.
	 */
	private function authorize( string $capability, string $nonce_action ): void {
		if ( ! AdminCapabilities::currentUserCan( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'woocommerce-delivery-management' ) );
		}

		$nonce = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( false === $nonce || null === $nonce || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'woocommerce-delivery-management' ) );
		}
	}

	/**
	 * @return DeliveryService
	 */
	private function deliveryService(): DeliveryService {
		return $this->container->get( DeliveryService::class );
	}

	/**
	 * @return DriverService
	 */
	private function driverService(): DriverService {
		return $this->container->get( DriverService::class );
	}

	/**
	 * @return WarehouseService
	 */
	private function warehouseService(): WarehouseService {
		return $this->container->get( WarehouseService::class );
	}

	/**
	 * @return DeliveryRuleService
	 */
	private function ruleService(): DeliveryRuleService {
		return $this->container->get( DeliveryRuleService::class );
	}
}
