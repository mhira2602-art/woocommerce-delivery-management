<?php
/** Missing resource exception. @package WDM */
declare( strict_types=1 );
namespace WDM\Application\Exception;

/** Indicates a requested operational record does not exist. */
final class NotFoundException extends ApplicationException {}
