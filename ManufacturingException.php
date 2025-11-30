<?php
/**
 * Manufacturing Exception Class
 */

namespace FA\Modules\AdvancedManufacturing;

/**
 * Manufacturing-specific exception
 */
class ManufacturingException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}