<?php
/** Status history persistence contract. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Contract;

interface StatusHistoryStore {
	/** @param array<string,mixed> $data */ public function insert( array $data ): int;
}
