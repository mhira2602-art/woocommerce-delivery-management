<?php
/** Application exception base. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Exception;

/** Expected application failure without database details. */
class ApplicationException extends \RuntimeException {}
