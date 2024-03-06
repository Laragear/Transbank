<?php

namespace Laragear\Transbank\Listeners;

use Illuminate\Contracts\Cache\Factory as CacheContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Laragear\Transbank\Events\TransactionCreated;

class SaveTransactionToken
{
    /**
     * Create a new listener instance.
     */
    public function __construct(protected ConfigContract $config, protected CacheContract $cache)
    {
        //
    }

    /**
     * Handle the fired event.
     */
    public function handle(TransactionCreated $event): void
    {
        $this->cache
            ->store($this->config->get('transbank.protect.store'))
            ->put($this->config->get('transbank.protect.prefix') . '|' . $event->response->getToken(), true, 300);
    }
}
