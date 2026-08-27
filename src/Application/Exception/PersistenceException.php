<?php
/** Persistence failure exception. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Exception;

/** Indicates an expected write could not be persisted. */
final class PersistenceException extends ApplicationException {}
