<?php
/**
 * Render WordPress admin screens for delivery management.
 *
 * @package WDM
 */

declare( strict_types=1 );

namespace WDM\Admin;

use WDM\Application\DeliveryRuleService;
use WDM\Application\DeliveryService;
use WDM\Application\DriverService;
use WDM\Application\WarehouseService;
use WDM\Infrastructure\Database\WordPressDatabase;
use WDM\Infrastructure\Repository\DeliveryRepository;
use WDM\Infrastructure\Repository\DeliveryRuleRepository;
use WDM\Infrastructure\Repository\DeliveryStatusHistoryRepository;
use WDM\Infrastructure\Repository\DriverRepository;
use WDM\Infrastructure\Repository\WarehouseRepository;

/**
 * Small, server-rendered admin pages for operational management.
 */
final class AdminPages {
	/**
	 * Render the dashboard page.
	 */
	public static function dashboard(): void {
		$repo          = self::deliveryRepository();
		$status_counts = array(
			'pending'          => $repo->countByStatus( 'pending' ),
			'scheduled'        => $repo->countByStatus( 'scheduled' ),
			'assigned'         => $repo->countByStatus( 'assigned' ),
			'out_for_delivery' => $repo->countByStatus( 'out_for_delivery' ),
			'delivered'        => $repo->countByStatus( 'delivered' ),
			'failed'           => $repo->countByStatus( 'failed' ),
			'cancelled'        => $repo->countByStatus( 'cancelled' ),
		);
		$recent        = $repo->findRecent( 5 );

		echo '<div class="wrap wdm-admin-wrap">';
		echo '<h1>' . esc_html__( 'Delivery Management Dashboard', 'woocommerce-delivery-management' ) . '</h1>';
		echo '<div class="wdm-dashboard-grid">';
		foreach ( $status_counts as $status => $count ) {
			echo '<div class="wdm-stat-card"><span class="wdm-stat-label">' . esc_html( self::statusLabel( $status ) ) . '</span><strong>' . esc_html( (string) $count ) . '</strong></div>';
		}
		echo '</div>';
		echo '<h2>' . esc_html__( 'Recent deliveries', 'woocommerce-delivery-management' ) . '</h2>';
		echo '<table class="wp-list-table widefat striped"><thead><tr><th>' . esc_html__( 'ID', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Order', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Status', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Driver', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Warehouse', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Updated', 'woocommerce-delivery-management' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No deliveries yet.', 'woocommerce-delivery-management' ) . '</td></tr>';
		} else {
			foreach ( $recent as $row ) {
				$driver_name    = self::driverName( (int) ( $row['driver_id'] ?? 0 ) );
				$warehouse_name = self::warehouseName( (int) ( $row['warehouse_id'] ?? 0 ) );
				echo '<tr>';
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-deliveries&action=view&id=' . (int) $row['id'] ) ) . '">#' . esc_html( (string) $row['id'] ) . '</a></td>';
				echo '<td>#' . esc_html( (string) $row['order_id'] ) . '</td>';
				echo '<td>' . esc_html( self::statusLabel( (string) ( $row['status'] ?? 'pending' ) ) ) . '</td>';
				echo '<td>' . esc_html( $driver_name ) . '</td>';
				echo '<td>' . esc_html( $warehouse_name ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['updated_at'] ?? '' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render the deliveries list screen.
	 */
	public static function deliveries(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search and filter values are used for listing, not mutation.
		$repo          = self::deliveryRepository();
		$status_filter = self::stringParam( 'status' );
		$search        = self::stringParam( 's' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination args are not state-changing.
		$page       = max( 1, AdminRequest::intParam( $_GET, 'paged', 1 ) );
		$per_page   = 20;
		$offset     = ( $page - 1 ) * $per_page;
		$total      = $repo->countAll(
			array(
				'status' => $status_filter,
				'search' => $search,
			)
		);
		$deliveries = $repo->findAll(
			$per_page,
			$offset,
			array(
				'status' => $status_filter,
				'search' => $search,
			)
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'Deliveries', 'woocommerce-delivery-management' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-deliveries&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'woocommerce-delivery-management' ) . '</a></h1>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="wdm-delivery-management-deliveries" /><div class="tablenav top"><div class="alignleft actions">';
		echo '<select name="status"><option value="">All statuses</option>';
		foreach ( array( 'pending', 'scheduled', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'cancelled' ) as $status ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $status_filter, $status, false ) . '>' . esc_html( self::statusLabel( $status ) ) . '</option>';
		}
		echo '</select> <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Order ID / Customer name" /> <input type="submit" class="button" value="Filter" />';
		echo '</div></div></form>';
		echo '<table class="wp-list-table widefat striped"><thead><tr><th>' . esc_html__( 'ID', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Order', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Status', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Driver', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Warehouse', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Region', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Scheduled', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Updated', 'woocommerce-delivery-management' ) . '</th></tr></thead><tbody>';
		if ( empty( $deliveries ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No deliveries found.', 'woocommerce-delivery-management' ) . '</td></tr>';
		} else {
			foreach ( $deliveries as $delivery ) {
				$driver    = self::driverName( (int) ( $delivery['driver_id'] ?? 0 ) );
				$warehouse = self::warehouseName( (int) ( $delivery['warehouse_id'] ?? 0 ) );
				echo '<tr>';
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-deliveries&action=view&id=' . (int) $delivery['id'] ) ) . '">#' . esc_html( (string) $delivery['id'] ) . '</a></td>';
				echo '<td><a href="' . esc_url( self::woocommerceOrderUrl( (int) ( $delivery['order_id'] ?? 0 ) ) ) . '">#' . esc_html( (string) $delivery['order_id'] ) . '</a></td>';
				echo '<td>' . esc_html( self::statusLabel( (string) ( $delivery['status'] ?? 'pending' ) ) ) . '</td>';
				echo '<td>' . esc_html( $driver ) . '</td>';
				echo '<td>' . esc_html( $warehouse ) . '</td>';
				echo '<td>' . esc_html( (string) ( $delivery['region'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $delivery['scheduled_date'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $delivery['updated_at'] ?? '' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		self::pagination(
			$total,
			$per_page,
			$page,
			'wdm-delivery-management-deliveries',
			array(
				'status' => $status_filter,
				's'      => $search,
			)
		);
		echo '</div>';
	}

	/**
	 * Render a single delivery detail screen.
	 */
	public static function deliveryDetail(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- detail screen loads a specific record using a safe read-only ID.
		$id = AdminRequest::intParam( $_GET, 'id', 0 );
		if ( 0 === $id ) {
			wp_die( esc_html__( 'A valid delivery ID is required.', 'woocommerce-delivery-management' ) );
		}

		$delivery = self::deliveryRepository()->findById( $id );
		if ( null === $delivery ) {
			wp_die( esc_html__( 'Delivery not found.', 'woocommerce-delivery-management' ) );
		}

		$history    = self::statusHistoryRepository()->findByDeliveryId( $id, 50 );
		$drivers    = self::driverRepository()->findAll( 200, 0, array( 'status' => 'active' ) );
		$warehouses = self::warehouseRepository()->findAll( 200, 0, array( 'status' => 'active' ) );

		echo '<div class="wrap"><h1>' . esc_html__( 'Delivery #', 'woocommerce-delivery-management' ) . esc_html( (string) $id ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-deliveries' ) ) . '">&larr; ' . esc_html__( 'Back to deliveries', 'woocommerce-delivery-management' ) . '</a></p>';
		echo '<div class="wdm-detail-grid"><div class="wdm-detail-card"><h2>' . esc_html__( 'Delivery details', 'woocommerce-delivery-management' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		foreach ( array(
			'Delivery ID'       => $delivery['id'],
			'WooCommerce Order' => '<a href="' . esc_url( self::woocommerceOrderUrl( (int) $delivery['order_id'] ) ) . '">#' . esc_html( (string) $delivery['order_id'] ) . '</a>',
			'Customer'          => trim( (string) ( $delivery['first_name'] ?? '' ) . ' ' . (string) ( $delivery['last_name'] ?? '' ) ),
			'Status'            => self::statusLabel( (string) ( $delivery['status'] ?? 'pending' ) ),
			'Scheduled date'    => (string) ( $delivery['scheduled_date'] ?? '' ),
			'Estimated date'    => (string) ( $delivery['estimated_date'] ?? '' ),
			'Actual date'       => (string) ( $delivery['actual_date'] ?? '' ),
			'Delivery slot'     => (string) ( $delivery['time_slot'] ?? '' ),
			'Delivery charge'   => (string) ( $delivery['delivery_charge'] ?? 0 ),
			'Driver'            => self::driverName( (int) ( $delivery['driver_id'] ?? 0 ) ),
			'Warehouse'         => self::warehouseName( (int) ( $delivery['warehouse_id'] ?? 0 ) ),
			'Notes'             => (string) ( $delivery['notes'] ?? '' ),
			'Created'           => (string) ( $delivery['created_at'] ?? '' ),
			'Updated'           => (string) ( $delivery['updated_at'] ?? '' ),
		) as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<div class="wdm-detail-card"><h2>' . esc_html__( 'Status actions', 'woocommerce-delivery-management' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_change_delivery_status', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_change_delivery_status" /><input type="hidden" name="delivery_id" value="' . esc_attr( (string) $id ) . '" />';
		echo '<label><span class="screen-reader-text">Status</span><select name="status">';
		foreach ( array( 'pending', 'scheduled', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'cancelled' ) as $status ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $delivery['status'] ?? 'pending', $status, false ) . '>' . esc_html( self::statusLabel( $status ) ) . '</option>';
		}
		echo '</select></label> <input type="submit" class="button button-primary" value="' . esc_attr__( 'Change status', 'woocommerce-delivery-management' ) . '" />';
		echo '</form>';
		echo '<h3>' . esc_html__( 'Assign driver', 'woocommerce-delivery-management' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_assign_driver', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_assign_driver" /><input type="hidden" name="delivery_id" value="' . esc_attr( (string) $id ) . '" /><select name="driver_id">';
		foreach ( $drivers as $driver ) {
			echo '<option value="' . esc_attr( (string) $driver['id'] ) . '"' . selected( (string) ( $delivery['driver_id'] ?? '' ), (string) $driver['id'], false ) . '>' . esc_html( (string) ( $driver['name'] ?? '' ) ) . '</option>';
		}
		echo '</select> <input type="submit" class="button" value="' . esc_attr__( 'Assign driver', 'woocommerce-delivery-management' ) . '" /></form>';
		echo '<h3>' . esc_html__( 'Assign warehouse', 'woocommerce-delivery-management' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_assign_warehouse', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_assign_warehouse" /><input type="hidden" name="delivery_id" value="' . esc_attr( (string) $id ) . '" /><select name="warehouse_id">';
		foreach ( $warehouses as $warehouse ) {
			echo '<option value="' . esc_attr( (string) $warehouse['id'] ) . '"' . selected( (string) ( $delivery['warehouse_id'] ?? '' ), (string) $warehouse['id'], false ) . '>' . esc_html( (string) ( $warehouse['name'] ?? '' ) ) . '</option>';
		}
		echo '</select> <input type="submit" class="button" value="' . esc_attr__( 'Assign warehouse', 'woocommerce-delivery-management' ) . '" /></form>';
		echo '</div></div>';
		echo '<div class="wdm-detail-card"><h2>' . esc_html__( 'Status history', 'woocommerce-delivery-management' ) . '</h2><table class="wp-list-table widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'From', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'To', 'woocommerce-delivery-management' ) . '</th><th>' . esc_html__( 'Note', 'woocommerce-delivery-management' ) . '</th></tr></thead><tbody>';
		if ( empty( $history ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No history yet.', 'woocommerce-delivery-management' ) . '</td></tr>';
		} else {
			foreach ( $history as $entry ) {
				echo '<tr><td>' . esc_html( (string) ( $entry['created_at'] ?? '' ) ) . '</td><td>' . esc_html( self::statusLabel( (string) ( $entry['previous_status'] ?? 'pending' ) ) ) . '</td><td>' . esc_html( self::statusLabel( (string) ( $entry['new_status'] ?? 'pending' ) ) ) . '</td><td>' . esc_html( (string) ( $entry['note'] ?? '' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</div>';
	}

	/**
	 * Render the driver management screen.
	 */
	public static function drivers(): void {
		$repo   = self::driverRepository();
		$search = self::stringParam( 's' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list pagination is read-only and not a mutation.
		$page     = max( 1, AdminRequest::intParam( $_GET, 'paged', 1 ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;
		$total    = $repo->countAll(
			array(
				'search' => $search,
			)
		);
		$drivers  = $repo->findAll( $per_page, $offset, array( 'search' => $search ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- edit selection is a read-only screen parameter.
		$editing  = AdminRequest::intParam( $_GET, 'edit_id', 0 );
		$existing = $editing > 0 ? $repo->findById( $editing ) : null;
		echo '<div class="wrap"><h1>' . esc_html__( 'Drivers', 'woocommerce-delivery-management' ) . '</h1>';
		echo '<div class="wdm-row"><div class="wdm-form-card"><h2>' . ( $existing ? esc_html__( 'Edit driver', 'woocommerce-delivery-management' ) : esc_html__( 'Add driver', 'woocommerce-delivery-management' ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_save_driver', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_save_driver" /><input type="hidden" name="id" value="' . esc_attr( (string) $editing ) . '" />';
		echo '<table class="form-table"><tr><th><label for="wdm_driver_name">' . esc_html__( 'Name', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_driver_name" name="name" type="text" value="' . esc_attr( (string) ( $existing['name'] ?? '' ) ) . '" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="wdm_driver_email">' . esc_html__( 'Email', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_driver_email" name="email" type="email" value="' . esc_attr( (string) ( $existing['email'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_driver_phone">' . esc_html__( 'Phone', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_driver_phone" name="phone" type="text" value="' . esc_attr( (string) ( $existing['phone'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_driver_reference">' . esc_html__( 'Reference', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_driver_reference" name="employee_reference" type="text" value="' . esc_attr( (string) ( $existing['employee_reference'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_driver_status">' . esc_html__( 'Status', 'woocommerce-delivery-management' ) . '</label></th><td><select id="wdm_driver_status" name="status"><option value="active"' . selected( (string) ( $existing['status'] ?? 'active' ), 'active', false ) . '>' . esc_html__( 'Active', 'woocommerce-delivery-management' ) . '</option><option value="inactive"' . selected( (string) ( $existing['status'] ?? 'active' ), 'inactive', false ) . '>' . esc_html__( 'Inactive', 'woocommerce-delivery-management' ) . '</option></select></td></tr>';
		echo '</table><p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr( $existing ? __( 'Update driver', 'woocommerce-delivery-management' ) : __( 'Add driver', 'woocommerce-delivery-management' ) ) . '" /></p></form></div>';
		echo '<div class="wdm-list-card"><h2>' . esc_html__( 'Existing drivers', 'woocommerce-delivery-management' ) . '</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="wdm-delivery-management-drivers" /><div class="tablenav top"><div class="alignleft actions"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Search drivers" /><input type="submit" class="button" value="Search" /></div></div></form>';
		echo '<table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Reference</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		if ( empty( $drivers ) ) {
			echo '<tr><td colspan="7">No drivers found.</td></tr>';
		} else {
			foreach ( $drivers as $driver ) {
				echo '<tr><td>' . esc_html( (string) $driver['id'] ) . '</td><td>' . esc_html( (string) ( $driver['name'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $driver['email'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $driver['phone'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $driver['employee_reference'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $driver['status'] ?? 'inactive' ) ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-drivers&edit_id=' . (int) $driver['id'] ) ) . '">Edit</a></td></tr>';
			}
		}
		echo '</tbody></table>';
		self::pagination( $total, $per_page, $page, 'wdm-delivery-management-drivers', array( 's' => $search ) );
		echo '</div></div></div>';
	}

	/**
	 * Render the warehouse management screen.
	 */
	public static function warehouses(): void {
		$repo   = self::warehouseRepository();
		$search = self::stringParam( 's' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list pagination is read-only and not a mutation.
		$page       = max( 1, AdminRequest::intParam( $_GET, 'paged', 1 ) );
		$per_page   = 20;
		$offset     = ( $page - 1 ) * $per_page;
		$warehouses = $repo->findAll( $per_page, $offset, array( 'search' => $search ) );
		$total      = $repo->countAll( array( 'search' => $search ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- edit selection is a read-only screen parameter.
		$editing  = AdminRequest::intParam( $_GET, 'edit_id', 0 );
		$existing = $editing > 0 ? $repo->findById( $editing ) : null;
		echo '<div class="wrap"><h1>' . esc_html__( 'Warehouses', 'woocommerce-delivery-management' ) . '</h1>';
		echo '<div class="wdm-row"><div class="wdm-form-card"><h2>' . ( $existing ? esc_html__( 'Edit warehouse', 'woocommerce-delivery-management' ) : esc_html__( 'Add warehouse', 'woocommerce-delivery-management' ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_save_warehouse', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_save_warehouse" /><input type="hidden" name="id" value="' . esc_attr( (string) $editing ) . '" />';
		echo '<table class="form-table"><tr><th><label for="wdm_warehouse_name">' . esc_html__( 'Name', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_warehouse_name" name="name" type="text" value="' . esc_attr( (string) ( $existing['name'] ?? '' ) ) . '" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="wdm_warehouse_code">' . esc_html__( 'Code', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_warehouse_code" name="code" type="text" value="' . esc_attr( (string) ( $existing['code'] ?? '' ) ) . '" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="wdm_warehouse_region">' . esc_html__( 'Region', 'woocommerce-delivery-management' ) . '</label></th><td><input id="wdm_warehouse_region" name="region" type="text" value="' . esc_attr( (string) ( $existing['region'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_warehouse_status">' . esc_html__( 'Status', 'woocommerce-delivery-management' ) . '</label></th><td><select id="wdm_warehouse_status" name="status"><option value="active"' . selected( (string) ( $existing['status'] ?? 'active' ), 'active', false ) . '>' . esc_html__( 'Active', 'woocommerce-delivery-management' ) . '</option><option value="inactive"' . selected( (string) ( $existing['status'] ?? 'active' ), 'inactive', false ) . '>' . esc_html__( 'Inactive', 'woocommerce-delivery-management' ) . '</option></select></td></tr>';
		echo '</table><p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr( $existing ? __( 'Update warehouse', 'woocommerce-delivery-management' ) : __( 'Add warehouse', 'woocommerce-delivery-management' ) ) . '" /></p></form></div>';
		echo '<div class="wdm-list-card"><h2>' . esc_html__( 'Existing warehouses', 'woocommerce-delivery-management' ) . '</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="wdm-delivery-management-warehouses" /><div class="tablenav top"><div class="alignleft actions"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Search warehouses" /><input type="submit" class="button" value="Search" /></div></div></form>';
		echo '<table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Region</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		if ( empty( $warehouses ) ) {
			echo '<tr><td colspan="6">No warehouses found.</td></tr>';
		} else {
			foreach ( $warehouses as $warehouse ) {
				echo '<tr><td>' . esc_html( (string) $warehouse['id'] ) . '</td><td>' . esc_html( (string) ( $warehouse['name'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $warehouse['code'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $warehouse['region'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $warehouse['status'] ?? 'inactive' ) ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-warehouses&edit_id=' . (int) $warehouse['id'] ) ) . '">Edit</a></td></tr>';
			}
		}
		echo '</tbody></table>';
		self::pagination( $total, $per_page, $page, 'wdm-delivery-management-warehouses', array( 's' => $search ) );
		echo '</div></div></div>';
	}

	/**
	 * Render the delivery rules management screen.
	 */
	public static function rules(): void {
		$repo = self::ruleRepository();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list pagination is read-only and not a mutation.
		$page     = max( 1, AdminRequest::intParam( $_GET, 'paged', 1 ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;
		$rules    = $repo->findAll( $per_page, $offset );
		$total    = $repo->countAll();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- edit selection is a read-only screen parameter.
		$editing  = AdminRequest::intParam( $_GET, 'edit_id', 0 );
		$existing = $editing > 0 ? $repo->findById( $editing ) : null;
		echo '<div class="wrap"><h1>' . esc_html__( 'Delivery Rules', 'woocommerce-delivery-management' ) . '</h1>';
		echo '<div class="wdm-row"><div class="wdm-form-card"><h2>' . ( $existing ? esc_html__( 'Edit rule', 'woocommerce-delivery-management' ) : esc_html__( 'Add rule', 'woocommerce-delivery-management' ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wdm_save_rule', '_wpnonce' );
		echo '<input type="hidden" name="action" value="wdm_save_rule" /><input type="hidden" name="id" value="' . esc_attr( (string) $editing ) . '" />';
		echo '<table class="form-table"><tr><th><label for="wdm_rule_region">Region</label></th><td><input id="wdm_rule_region" name="region" type="text" value="' . esc_attr( (string) ( $existing['region'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_warehouse_id">Warehouse</label></th><td><select id="wdm_rule_warehouse_id" name="warehouse_id"><option value="0">—</option>';
		foreach ( self::warehouseRepository()->findAll( 100, 0 ) as $warehouse ) {
			echo '<option value="' . esc_attr( (string) $warehouse['id'] ) . '"' . selected( (string) ( $existing['warehouse_id'] ?? '0' ), (string) $warehouse['id'], false ) . '>' . esc_html( (string) $warehouse['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="wdm_rule_weekday">Weekday</label></th><td><input id="wdm_rule_weekday" name="weekday" type="number" min="0" max="6" value="' . esc_attr( (string) ( $existing['weekday'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_cutoff_time">Cut-off time</label></th><td><input id="wdm_rule_cutoff_time" name="cutoff_time" type="time" value="' . esc_attr( (string) ( $existing['cutoff_time'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_slot">Delivery slot</label></th><td><input id="wdm_rule_slot" name="delivery_slot" type="text" value="' . esc_attr( (string) ( $existing['delivery_slot'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_days">Delivery days</label></th><td><input id="wdm_rule_days" name="delivery_days" type="text" value="' . esc_attr( (string) ( $existing['delivery_days'] ?? '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_priority">Priority</label></th><td><input id="wdm_rule_priority" name="priority" type="number" min="0" value="' . esc_attr( (string) ( $existing['priority'] ?? 0 ) ) . '" /></td></tr>';
		echo '<tr><th><label for="wdm_rule_active">Active</label></th><td><select id="wdm_rule_active" name="is_active"><option value="1"' . selected( (string) ( $existing['is_active'] ?? '1' ), '1', false ) . '>Active</option><option value="0"' . selected( (string) ( $existing['is_active'] ?? '1' ), '0', false ) . '>Inactive</option></select></td></tr>';
		echo '</table><p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr( $existing ? __( 'Update rule', 'woocommerce-delivery-management' ) : __( 'Add rule', 'woocommerce-delivery-management' ) ) . '" /></p></form></div>';
		echo '<div class="wdm-list-card"><h2>Existing rules</h2><table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Region</th><th>Warehouse</th><th>Weekday</th><th>Slot</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		if ( empty( $rules ) ) {
			echo '<tr><td colspan="8">No rules yet.</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$warehouse = self::warehouseName( (int) ( $rule['warehouse_id'] ?? 0 ) );
				echo '<tr><td>' . esc_html( (string) $rule['id'] ) . '</td><td>' . esc_html( (string) ( $rule['region'] ?? '' ) ) . '</td><td>' . esc_html( $warehouse ) . '</td><td>' . esc_html( (string) ( $rule['weekday'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $rule['delivery_slot'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $rule['priority'] ?? 0 ) ) . '</td><td>' . esc_html( (string) ( ( $rule['is_active'] ?? 1 ) ? 'active' : 'inactive' ) ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=wdm-delivery-management-rules&edit_id=' . (int) $rule['id'] ) ) . '">Edit</a></td></tr>';
			}
		}
		echo '</tbody></table>';
		self::pagination( $total, $per_page, $page, 'wdm-delivery-management-rules' );
		echo '</div></div></div>';
	}

	/**
	 * Return the WordPress order URL when WooCommerce is available.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private static function woocommerceOrderUrl( int $order_id ): string {
		if ( $order_id < 1 ) {
			return admin_url( 'admin.php?page=wdm-delivery-management-deliveries' );
		}

		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( is_object( $order ) && method_exists( $order, 'get_edit_url' ) ) {
				return (string) $order->get_edit_url();
			}
		}

		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	/**
	 * Human-friendly status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private static function statusLabel( string $status ): string {
		$labels = array(
			'pending'          => 'Pending',
			'scheduled'        => 'Scheduled',
			'assigned'         => 'Assigned',
			'out_for_delivery' => 'Out for Delivery',
			'delivered'        => 'Delivered',
			'failed'           => 'Failed',
			'cancelled'        => 'Cancelled',
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Return a driver label.
	 *
	 * @param int $id Driver ID.
	 * @return string
	 */
	private static function driverName( int $id ): string {
		if ( $id < 1 ) {
			return 'Unassigned';
		}

		$row = self::driverRepository()->findById( $id );
		return $row ? (string) ( $row['name'] ?? 'Unknown driver' ) : 'Unknown driver';
	}

	/**
	 * Return a warehouse label.
	 *
	 * @param int $id Warehouse ID.
	 * @return string
	 */
	private static function warehouseName( int $id ): string {
		if ( $id < 1 ) {
			return 'Unassigned';
		}

		$row = self::warehouseRepository()->findById( $id );
		return $row ? (string) ( $row['name'] ?? 'Unknown warehouse' ) : 'Unknown warehouse';
	}

	/**
	 * Return a sanitized string from the request.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private static function stringParam( string $key ): string {
		$value = filter_input( INPUT_GET, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( false === $value || null === $value ) {
			return '';
		}

		return trim( sanitize_text_field( wp_unslash( $value ) ) );
	}

	/**
	 * Render basic pagination controls.
	 *
	 * @param int                  $total     Total items.
	 * @param int                  $per_page  Number per page.
	 * @param int                  $page      Current page.
	 * @param string               $page_slug Page slug.
	 * @param array<string,string> $extra     Extra query args.
	 */
	private static function pagination( int $total, int $per_page, int $page, string $page_slug, array $extra = array() ): void {
		$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( (string) $total ) . ' items</span>';
		$base = array(
			'page'  => $page_slug,
			'paged' => '%s',
		);
		foreach ( $extra as $key => $value ) {
			if ( '' !== $value ) {
				$base[ $key ] = $value;
			}
		}
		if ( 1 !== $page ) {
			echo '<a class="prev-page button" href="' . esc_url( admin_url( 'admin.php?' . http_build_query( array_merge( $base, array( 'paged' => $page - 1 ) ) ) ) ) . '">&laquo;</a>';
		}
		echo '<span class="paging-input">' . esc_html( (string) $page ) . ' / ' . esc_html( (string) $total_pages ) . '</span>';
		if ( $page < $total_pages ) {
			echo '<a class="next-page button" href="' . esc_url( admin_url( 'admin.php?' . http_build_query( array_merge( $base, array( 'paged' => $page + 1 ) ) ) ) ) . '">&raquo;</a>';
		}
		echo '</div></div>';
	}

	/**
	 * @return DeliveryRepository
	 */
	private static function deliveryRepository(): DeliveryRepository {
		return self::container()->get( DeliveryRepository::class );
	}

	/**
	 * @return DeliveryStatusHistoryRepository
	 */
	private static function statusHistoryRepository(): DeliveryStatusHistoryRepository {
		return self::container()->get( DeliveryStatusHistoryRepository::class );
	}

	/**
	 * @return DriverRepository
	 */
	private static function driverRepository(): DriverRepository {
		return self::container()->get( DriverRepository::class );
	}

	/**
	 * @return WarehouseRepository
	 */
	private static function warehouseRepository(): WarehouseRepository {
		return self::container()->get( WarehouseRepository::class );
	}

	/**
	 * @return DeliveryRuleRepository
	 */
	private static function ruleRepository(): DeliveryRuleRepository {
		return self::container()->get( DeliveryRuleRepository::class );
	}

	/**
	 * @return \WDM\Support\Container
	 */
	/**
	 * @throws \RuntimeException When the plugin dependency container is not initialized.
	 * @return \WDM\Support\Container
	 */
	private static function container() {
		if ( isset( $GLOBALS['wdm_container'] ) ) {
			return $GLOBALS['wdm_container'];
		}

		throw new \RuntimeException( 'The WDM dependency container is not initialized.' );
	}
}
