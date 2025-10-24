<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class CapacityExceededException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?: "The requested capacity exceeds the available limit.", $code, $previous);
    }
}
