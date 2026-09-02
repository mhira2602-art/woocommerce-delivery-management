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
use WDM\Domain\Delivery\DeliveryStatus;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
		$id = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
		$status = isset( $_POST['status'] ) && is_scalar( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( $id < 1 || '' === $status ) {
			$this->redirectWithNotice( 'wdm-delivery-management-deliveries', 'Invalid delivery status request.', false );
		}
		if ( ! in_array( $status, DeliveryStatus::all(), true ) ) {
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
		$delivery_id = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
		$driver_id = AdminRequest::intParam( $_POST, 'driver_id', 0 );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
		$delivery_id = AdminRequest::intParam( $_POST, 'delivery_id', 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified centrally in authorize().
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
	/* phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize(). */
	public function saveDriver(): void {
		$this->authorize( 'wdm_manage_drivers', 'wdm_save_driver' );

		$id            = AdminRequest::intParam( $_POST, 'id', 0 );
		$name          = AdminRequest::nameParam( $_POST, 'name' );
		$email         = AdminRequest::emailParam( $_POST, 'email' );
		$phone         = AdminRequest::phoneParam( $_POST, 'phone' );
		$status        = AdminRequest::statusParam( $_POST, 'status', array( 'active', 'inactive' ), 'inactive' );
		$email_raw     = AdminRequest::rawParam( $_POST, 'email' );
		$phone_raw     = AdminRequest::rawParam( $_POST, 'phone' );
		$reference     = AdminRequest::referenceParam( $_POST, 'employee_reference' );
		$reference_raw = AdminRequest::rawParam( $_POST, 'employee_reference' );
		if ( ( null !== $email_raw && ! is_scalar( $email_raw ) ) || ( null !== $phone_raw && ! is_scalar( $phone_raw ) ) || ( null !== $reference_raw && ! is_scalar( $reference_raw ) ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Please submit valid driver fields.', false );
		}
		$email_raw     = trim( (string) wp_unslash( $email_raw ) );
		$phone_raw     = trim( (string) wp_unslash( $phone_raw ) );
		$reference_raw = trim( (string) wp_unslash( $reference_raw ) );
		if ( '' === $name ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Please enter a valid driver name.', false );
		}
		if ( '' !== $email_raw && '' === $email ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Please enter a valid email address.', false );
		}
		if ( '' !== $phone_raw && '' === $phone ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Phone number must contain exactly 10 digits.', false );
		}
		if ( '' !== $reference_raw && '' === $reference ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Please enter a valid employee reference.', false );
		}
		if ( '' === $status ) {
			$this->redirectWithNotice( 'wdm-delivery-management-drivers', 'Please select a valid status.', false );
		}

		$data = array(
			'name'               => $name,
			'email'              => $email,
			'phone'              => $phone,
			'employee_reference' => $reference,
			'status'             => $status,
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
	/* phpcs:enable WordPress.Security.NonceVerification.Missing */

	/**
	 * Save or update a warehouse.
	 */
	/* phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize(). */
	public function saveWarehouse(): void {
		$this->authorize( 'wdm_manage_warehouses', 'wdm_save_warehouse' );

		$id         = AdminRequest::intParam( $_POST, 'id', 0 );
		$name       = AdminRequest::labelParam( $_POST, 'name' );
		$code       = strtoupper( trim( AdminRequest::textParam( $_POST, 'code' ) ) );
		$region     = AdminRequest::locationParam( $_POST, 'region' );
		$status     = AdminRequest::statusParam( $_POST, 'status', array( 'active', 'inactive' ), 'inactive' );
		$region_raw = AdminRequest::rawParam( $_POST, 'region' );
		if ( null !== $region_raw && ! is_scalar( $region_raw ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Please submit a valid warehouse region.', false );
		}
		$region_raw = trim( (string) wp_unslash( $region_raw ) );
		if ( '' === $name ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Please enter a valid warehouse name.', false );
		}
		if ( ! preg_match( '/^[A-Z0-9-]{2,50}$/', $code ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Please enter a valid warehouse code.', false );
		}
		if ( '' !== $region_raw && '' === $region ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Please enter a valid warehouse region.', false );
		}
		if ( '' === $status ) {
			$this->redirectWithNotice( 'wdm-delivery-management-warehouses', 'Please select a valid status.', false );
		}

		$data = array(
			'name'   => $name,
			'code'   => $code,
			'region' => $region,
			'status' => $status,
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
	/* phpcs:enable WordPress.Security.NonceVerification.Missing */

	/**
	 * Save or update a delivery rule.
	 */
	/* phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified centrally in authorize(). */
	public function saveRule(): void {
		$this->authorize( 'wdm_manage_delivery_rules', 'wdm_save_rule' );

		$id            = AdminRequest::intParam( $_POST, 'id', 0 );
		$region        = AdminRequest::locationParam( $_POST, 'region' );
		$warehouse_id  = AdminRequest::integerParam( $_POST, 'warehouse_id' );
		$weekday       = AdminRequest::integerParam( $_POST, 'weekday' );
		$cutoff_time   = AdminRequest::timeParam( $_POST, 'cutoff_time' );
		$slot          = AdminRequest::textParam( $_POST, 'delivery_slot' );
		$days          = AdminRequest::textParam( $_POST, 'delivery_days' );
		$priority      = AdminRequest::integerParam( $_POST, 'priority', 0 );
		$is_active     = AdminRequest::integerParam( $_POST, 'is_active', 1 );
		$region_raw    = AdminRequest::rawParam( $_POST, 'region' );
		$cutoff_raw    = AdminRequest::rawParam( $_POST, 'cutoff_time' );
		$active_raw    = AdminRequest::rawParam( $_POST, 'is_active' );
		$warehouse_raw = AdminRequest::rawParam( $_POST, 'warehouse_id' );
		$weekday_raw   = AdminRequest::rawParam( $_POST, 'weekday' );
		$priority_raw  = AdminRequest::rawParam( $_POST, 'priority' );
		if ( ( null !== $region_raw && ! is_scalar( $region_raw ) ) || ( null !== $cutoff_raw && ! is_scalar( $cutoff_raw ) ) || ( null !== $active_raw && ! is_scalar( $active_raw ) ) || ( null !== $warehouse_raw && ! is_scalar( $warehouse_raw ) ) || ( null !== $weekday_raw && ! is_scalar( $weekday_raw ) ) || ( null !== $priority_raw && ! is_scalar( $priority_raw ) ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please submit valid delivery rule fields.', false );
		}
		$region_raw    = trim( (string) wp_unslash( $region_raw ) );
		$cutoff_raw    = trim( (string) wp_unslash( $cutoff_raw ) );
		$warehouse_raw = trim( (string) wp_unslash( $warehouse_raw ) );
		$weekday_raw   = trim( (string) wp_unslash( $weekday_raw ) );
		$priority_raw  = trim( (string) wp_unslash( $priority_raw ) );
		$active_raw    = null === $active_raw ? '1' : (string) wp_unslash( $active_raw );
		if ( '' !== $region_raw && '' === $region ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please enter a valid rule region.', false );
		}
		if ( '' !== $cutoff_raw && '' === $cutoff_time ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please enter a valid cut-off time.', false );
		}
		if ( null === $warehouse_id && '' !== $warehouse_raw ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please select a valid warehouse.', false );
		}
		if ( null === $weekday && '' !== $weekday_raw ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please enter a valid weekday.', false );
		}
		if ( null === $priority || ( null !== $priority_raw && '' === $priority_raw ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please enter a valid rule priority.', false );
		}
		if ( null === $is_active || ! in_array( $active_raw, array( '0', '1' ), true ) ) {
			$this->redirectWithNotice( 'wdm-delivery-management-rules', 'Please select a valid rule status.', false );
		}
		$data = array(
			'region'        => $region,
			'warehouse_id'  => 0 === $warehouse_id ? null : $warehouse_id,
			'weekday'       => $weekday,
			'cutoff_time'   => $cutoff_time,
			'delivery_slot' => $slot,
			'delivery_days' => $days,
			'priority'      => $priority,
			'is_active'     => $is_active,
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
	/* phpcs:enable WordPress.Security.NonceVerification.Missing */

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
