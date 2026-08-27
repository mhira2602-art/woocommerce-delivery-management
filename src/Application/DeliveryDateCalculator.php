<?php
/** Delivery date calculation service. @package WDM */
declare( strict_types=1 );
namespace WDM\Application;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
/** Provides a deterministic baseline date calculation for later rule integration. */
final class DeliveryDateCalculator {
	/**
	 * Add delivery days, rolling forward from the supplied date.
	 *
	 * @param DateTimeInterface $from Starting date.
	 * @param int               $delivery_days Number of calendar days.
	 * @param string|null       $cutoff_time Optional HH:MM cutoff.
	 * @return DateTimeImmutable Calculated date.
	 * @throws InvalidArgumentException When delivery days are negative.
	 */
	public function calculate( DateTimeInterface $from, int $delivery_days = 2, ?string $cutoff_time = null ): DateTimeImmutable {
		if ( $delivery_days < 0 ) {
			throw new InvalidArgumentException( 'Delivery days cannot be negative.' ); }
		$result = new DateTimeImmutable( $from->format( 'Y-m-d H:i:s' ), $from->getTimezone() );
		if ( null !== $cutoff_time && $result->format( 'H:i' ) >= $cutoff_time ) {
			++$delivery_days; }
		return $result->modify( '+' . $delivery_days . ' days' );
	}
}
