<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Support\Str;
use function ctype_upper;
use function str_starts_with;
use function substr;

trait DynamicallyAccess
{
    /**
     * @inheritDoc
     */
    public function __call($method, $parameters)
    {
        // If the call starts with "get", the developer is getting a property.
        if (str_starts_with($method, 'get') && ctype_upper($method[3])) {
            $method = Str::snake(substr($method, 3));
        }

        return $this->get($method);
    }


    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        // Immutable.
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        // Immutable
    }
}
