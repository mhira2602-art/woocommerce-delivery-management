<?php
/** Invalid status transition exception. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Exception;

/** Indicates a delivery status change is not allowed. */
final class InvalidTransitionException extends ApplicationException {}
