<?php

namespace Laragear\Transbank\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laragear\Transbank\Services\Transactions\Response register(string $username, string $email, string $responseUrl)
 * @method static \Laragear\Transbank\Services\Transactions\Transaction confirm(string $token)
 * @method static void delete(string $tbkUser, string $username)
 * @method static \Laragear\Transbank\Services\Transactions\TransactionDetailed commit(string $tbkUser, string $username, string $buyOrder, iterable $details)
 * @method static \Laragear\Transbank\Services\Transactions\TransactionDetailed status(string $buyOrder)
 * @method static \Laragear\Transbank\Services\Transactions\TransactionDetailed refund(string $buyOrder, string $commerceCode, string $detailBuyOrder, int|float $amount)
 * @method static \Laragear\Transbank\Services\Transactions\TransactionDetailed capture(string $buyOrder, string $commerceCode, string $authorizationCode, int|float $amount)
 *
 * @method static \Laragear\Transbank\Services\Oneclick getFacadeRoot()
 */
class Oneclick extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return \Laragear\Transbank\Services\Oneclick::class;
    }
}
