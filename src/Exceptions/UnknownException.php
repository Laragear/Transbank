<?php

namespace Laragear\Transbank\Exceptions;

use RuntimeException;

class UnknownException extends RuntimeException implements TransbankException
{
    use HandlesException;

    /**
     * The log level to report to the app.
     *
     * @var int
     */
    public const LOG_LEVEL = LOG_CRIT;
}
