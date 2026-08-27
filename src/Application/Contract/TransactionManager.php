<?php
/** Transaction contract. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Contract;

/** Minimal transaction boundary for multi-write use cases. */
interface TransactionManager {
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
}
