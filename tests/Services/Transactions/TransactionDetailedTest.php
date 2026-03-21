<?php

namespace Tests\Services\Transactions;

use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Transactions\TransactionDetailed;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TransactionDetailedTest extends PHPUnitTestCase
{
    public function test_is_all_successful(): void
    {
        $transaction = new TransactionDetailed('foo', 'bar', [
            'details' => [
                [ 'response_code' => 0 ],
                [ 'response_code' => 0 ],
            ]
        ]);

        static::assertTrue($transaction->isAllSuccessful());
        static::assertFalse($transaction->isNotAllSuccessful());
    }

    public function test_is_not_all_successful(): void
    {
        $transaction = new TransactionDetailed('foo', 'bar', [
            'details' => [
                [ 'response_code' => 0 ],
                [ 'response_code' => 1 ],
            ]
        ]);

        static::assertFalse($transaction->isAllSuccessful());
        static::assertTrue($transaction->isNotAllSuccessful());
    }

    public function test_card_number(): void
    {
        $transaction = new TransactionDetailed('foo', 'bar', [
            'card_detail' => [
                'card_number' => '1234'
            ]
        ]);

        static::assertSame(1234, $transaction->getCreditCardNumber());

        $transaction = new TransactionDetailed('foo', 'bar', [
            'invalid' => [
                'card_number' => '1234'
            ]
        ]);

        static::assertNull($transaction->getCreditCardNumber());
    }

    public function test_details(): void
    {
        $transaction = new TransactionDetailed('foo', 'bar', [
            'card_detail' => [
                'card_number' => '1234'
            ],
            'details' => [
                [ 'response_code' => 1 ],
                [ 'response_code' => 2 ],
            ]
        ]);

        $details = $transaction->details();

        static::assertContainsOnlyInstancesOf(Transaction::class, $details);
        static::assertCount(2, $details);
        static::assertSame(1234, $transaction->getCreditCardNumber());
        static::assertSame(1234, $details->first()->getCreditCardNumber());
    }
}
