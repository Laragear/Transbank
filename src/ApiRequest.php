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
     *
     * @param  string  $service
     * @param  string  $action
     * @param  array  $attributes
     */
    public function __construct(public string $service, public string $action, public array $attributes = [])
    {
        //
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
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
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * Whether an offset exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? throw new Error("Undefined array key \"$offset\"");
    }

    /**
     * Offset to set.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Offset to unset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
