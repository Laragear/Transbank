<?php

namespace Laragear\Transbank\Http\Emulator;

use Illuminate\Contracts\Cache\Repository as CacheContract;

class Store
{
    /**
     * Real Transbank transaction tokens are valid for 5 minutes.
     */
    public const int TTL = 5 * 60;

    /**
     * Creates a new Store instance.
     */
    public function __construct(protected CacheContract $cache)
    {
        //
    }

    /**
     * Store the data of an emulated transaction for the given token.
     */
    public function put(string $token, array $payload): void
    {
        $this->cache->put($this->key($token), $payload, static::TTL);
    }

    /**
     * Retrieve the data of an emulated transaction by its token.
     */
    public function get(string $token): ?array
    {
        return $this->cache->get($this->key($token));
    }

    /**
     * Build the unique cache key for the given token.
     */
    protected function key(string $token): string
    {
        return 'transbank|emulator|'.$token;
    }
}
