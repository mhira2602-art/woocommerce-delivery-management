<?php
/** Tests for admin request validation helpers. @package WDM */
declare( strict_types=1 );

namespace WDM\Tests\Unit;

	use PHPUnit\Framework\TestCase;
	use WDM\Admin\AdminRequest;

final class AdminRequestTest extends TestCase {
	/**
	 * @dataProvider phoneProvider
	 */
	public function test_phone_requires_exactly_ten_digits( string $phone, string $expected ): void {
		$this->assertSame( $expected, AdminRequest::phoneParam( array( 'phone' => $phone ), 'phone' ) );
	}

	/** @return array<string,array<int,string>> */
	public function phoneProvider(): array {
		return array(
			'valid'   => array( '1234567890', '1234567890' ),
			'short'   => array( '123456789', '' ),
			'long'    => array( '12345678901', '' ),
			'letters' => array( '12345abc90', '' ),
			'hyphens' => array( '12345-67890', '' ),
			'spaces'  => array( '12345 67890', '' ),
			'plus'    => array( '+911234567890', '' ),
			'empty'   => array( '', '' ),
		);
	}

	public function test_search_is_bounded_and_pagination_is_safe(): void {
		$this->assertSame( '', AdminRequest::searchParam( array( 's' => str_repeat( 'x', 101 ) ), 's' ) );
		$this->assertSame( 1, AdminRequest::pageParam( array( 'paged' => '-2' ), 'paged' ) );
		$this->assertSame( 100, AdminRequest::perPageParam( array( 'per_page' => '500' ), 'per_page' ) );
	}
}
