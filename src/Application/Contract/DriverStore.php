<?php
/** Driver persistence contract. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Contract;

interface DriverStore {
	/** @param array<string,mixed> $data */ public function insert( array $data ): int;
	/** @return array<string,mixed>|null */ public function findById( int $id ): ?array;
	/** @param array<string,mixed> $data */ public function update( int $id, array $data ): bool;
}
