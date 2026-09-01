<?php
/** Test support classes for WooCommerce contract tests. @package WDM */
declare( strict_types=1 );

if ( ! class_exists( '\WC_Order', false ) ) {
	class WC_Order {
		private int $id;
		private string $status;
		/** @var array<int, object> */
		private array $items;
		private bool $shipping_address;
		private bool $needs_shipping;
		private array $shipping = array(
			'first_name' => 'Ada',
			'last_name'  => 'Lovelace',
			'company'    => 'Analytical Engines',
			'address_1'  => '123 Example Street',
			'address_2'  => 'Floor 2',
			'city'       => 'London',
			'state'      => 'Greater London',
			'postcode'   => 'SW1A 1AA',
			'country'    => 'GB',
			'phone'      => '+442071234567',
		);

		public function __construct( int $id, string $status, bool $physical, array $items, bool $shipping_address = true, bool $needs_shipping = true ) {
			$this->id               = $id;
			$this->status           = $status;
			$this->items            = $items;
			$this->shipping_address = $shipping_address;
			$this->needs_shipping   = $needs_shipping;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function has_shipping_address(): bool {
			return $this->shipping_address;
		}

		public function needs_shipping_address(): bool {
			return $this->needs_shipping;
		}

		public function get_items(): array {
			return $this->items;
		}

		public function get_shipping_first_name(): string {
			return $this->shipping['first_name'];
		}

		public function get_shipping_last_name(): string {
			return $this->shipping['last_name'];
		}

		public function get_shipping_company(): string {
			return $this->shipping['company'];
		}

		public function get_shipping_address_1(): string {
			return $this->shipping['address_1'];
		}

		public function get_shipping_address_2(): string {
			return $this->shipping['address_2'];
		}

		public function get_shipping_city(): string {
			return $this->shipping['city'];
		}

		public function get_shipping_state(): string {
			return $this->shipping['state'];
		}

		public function get_shipping_postcode(): string {
			return $this->shipping['postcode'];
		}

		public function get_shipping_country(): string {
			return $this->shipping['country'];
		}

		public function get_shipping_phone(): string {
			return $this->shipping['phone'];
		}
	}
}

class TestProduct {
	private bool $physical;

	public function __construct( bool $physical ) {
		$this->physical = $physical;
	}

	public function is_virtual(): bool {
		return ! $this->physical;
	}
}

class TestVirtualProduct {
	public function is_virtual(): bool {
		return true;
	}
}

class TestOrderItem {
	private object $product;

	public function __construct( object $product ) {
		$this->product = $product;
	}

	public function get_product(): object {
		return $this->product;
	}
}

class TestOrder extends WC_Order {
	private int $id;
	private string $status;
	/** @var array<int, object> */
	private array $items;

	public function __construct( int $id, string $status, bool $physical, array $items ) {
		parent::__construct( $id, $status, $physical, $items );
		$this->id     = $id;
		$this->status = $status;
		$this->items  = $items;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function has_shipping_address(): bool {
		return true;
	}

	public function needs_shipping_address(): bool {
		return true;
	}

	public function get_items(): array {
		return $this->items;
	}

	public function get_shipping_first_name(): string {
		return 'Ada';
	}

	public function get_shipping_last_name(): string {
		return 'Lovelace';
	}

	public function get_shipping_company(): string {
		return 'Analytical Engines';
	}

	public function get_shipping_address_1(): string {
		return '123 Example Street';
	}

	public function get_shipping_address_2(): string {
		return 'Floor 2';
	}

	public function get_shipping_city(): string {
		return 'London';
	}

	public function get_shipping_state(): string {
		return 'Greater London';
	}

	public function get_shipping_postcode(): string {
		return 'SW1A 1AA';
	}

	public function get_shipping_country(): string {
		return 'GB';
	}

	public function get_shipping_phone(): string {
		return '+442071234567';
	}
}
