<?php

namespace Laragear\Transbank\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laragear\Transbank\Services\Transactions\Response create(string $buyOrder, int|float $amount, string $returnUrl)
 * @method static \Laragear\Transbank\Services\Transactions\Transaction commit(string $token)
 * @method static \Laragear\Transbank\Services\Transactions\Transaction status(string $token)
 * @method static \Laragear\Transbank\Services\Transactions\Transaction refund(string $token, int|float $amount)
 * @method static \Laragear\Transbank\Services\Transactions\Transaction capture(string $token, string $buyOrder, int $code, int|float $amount)
 *
 * @method static \Laragear\Transbank\Services\Webpay getFacadeRoot()
 */
class Webpay extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return \Laragear\Transbank\Services\Webpay::class;
    }
}
