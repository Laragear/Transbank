<?php

namespace Laragear\Transbank;

use ArrayAccess;
use Error;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class ApiRequest implements JsonSerializable, ArrayAccess, Jsonable
{
    /**
     * Create a new API Request instance.
     */
    public function __construct(public string $service, public string $action, public array $attributes = [])
    {
        //
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * Whether an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Offset to retrieve.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? throw new Error("Undefined array key \"$offset\"");
    }

    /**
     * Offset to set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
