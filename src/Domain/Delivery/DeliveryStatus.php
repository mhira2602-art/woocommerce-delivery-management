<?php
/** Delivery status lifecycle. @package WDM */
declare( strict_types=1 );
namespace WDM\Domain\Delivery;

use InvalidArgumentException;
/** Controlled delivery statuses and transitions. */
final class DeliveryStatus {
	public const PENDING          = 'pending';
	public const SCHEDULED        = 'scheduled';
	public const ASSIGNED         = 'assigned';
	public const OUT_FOR_DELIVERY = 'out_for_delivery';
	public const DELIVERED        = 'delivered';
	public const FAILED           = 'failed';
	public const CANCELLED        = 'cancelled';
	/** @return array<int,string> */
	public static function all(): array {
		return array( self::PENDING, self::SCHEDULED, self::ASSIGNED, self::OUT_FOR_DELIVERY, self::DELIVERED, self::FAILED, self::CANCELLED ); }
	public static function isValid( string $status ): bool {
		return in_array( $status, self::all(), true ); }
	public static function canTransition( string $from, string $to ): bool {
		$transitions = array(
			self::PENDING          => array( self::SCHEDULED, self::CANCELLED ),
			self::SCHEDULED        => array( self::ASSIGNED, self::CANCELLED ),
			self::ASSIGNED         => array( self::OUT_FOR_DELIVERY, self::FAILED, self::CANCELLED ),
			self::OUT_FOR_DELIVERY => array( self::DELIVERED, self::FAILED ),
			self::FAILED           => array( self::SCHEDULED, self::CANCELLED ),
		);
		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}
	public static function assertValid( string $status ): void {
		if ( ! self::isValid( $status ) ) {
			throw new InvalidArgumentException( 'Invalid delivery status.' ); } }
}
