<?php

namespace Tests\Services\Transactions;

use Laragear\Transbank\Services\Transactions\Transaction;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TransactionTest extends PHPUnitTestCase
{
    public function test_is_not_successful_when_response_code_zero_and_status_false(): void
    {
        $response = new Transaction('foo', 'bar', [
            'response_code' => 0,
            'status' => ''
        ]);

        static::assertFalse($response->isSuccessful());
        static::assertTrue($response->isNotSuccessful());
        static::assertTrue($response->hasFailed());
    }

    public function test_dynamically_gets_property(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
            'baz_quz' => 'quuz'
        ]);

        static::assertFalse(isset($transaction->bar));

        static::assertEquals('bar', $transaction->foo);
        static::assertTrue(isset($transaction->foo));

        static::assertNull($transaction->bazQuz);
        static::assertFalse(isset($transaction->bazQuz));
    }

    public function test_property_is_immutable(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
        ]);

        $transaction->foo = 'quz';

        static::assertEquals('bar', $transaction->foo);

        unset($transaction->foo);

        static::assertEquals('bar', $transaction->foo);

        $transaction['foo'] = 'quz';

        static::assertEquals('bar', $transaction->foo);

        unset($transaction['foo']);

        static::assertEquals('bar', $transaction->foo);
    }

    public function test_dynamically_gets_value_with_method(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
            'baz_quz' => 'qux_quuz',
            'foo_bar_quz' => 'foo_bar_quz',
        ]);

        static::assertEquals('bar', $transaction->getFoo());
        static::assertEquals('qux_quuz', $transaction->getBazQuz());
        static::assertEquals('foo_bar_quz', $transaction->getFooBarQuz());
    }

    public function test_non_existent_method_returns_null(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
        ]);

        static::assertNull($transaction->getfoo());
    }

    public function test_set_method_doesnt_do_anything(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
        ]);

        $transaction->setFoo('quz');

        static::assertSame('bar', $transaction->foo);
    }

    public function test_accessible_as_array(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'foo' => 'bar',
            'baz_quz' => 'qux_quuz',
            'FooBarQuz' => 'foo_bar_quz',
        ]);

        static::assertEquals('bar', $transaction['foo']);
        static::assertEquals('qux_quuz', $transaction['baz_quz']);
        static::assertEquals('foo_bar_quz', $transaction['FooBarQuz']);

        static::assertTrue(isset($transaction['foo']));
        static::assertFalse(isset($transaction['cougar']));
    }

    public function test_transaction_successful(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'response_code' => 0
        ]);

        static::assertTrue($transaction->isSuccessful());

        $transaction = new Transaction('foo', 'bar', [
            'response_code' => 1
        ]);

        static::assertFalse($transaction->isSuccessful());

        $transaction = new Transaction('foo', 'bar', []);

        static::assertFalse($transaction->isSuccessful());

        $transaction = new Transaction('foo', 'bar', [
            'TBK_ID_SESSION' => 'test',
            'TBK_ORDEN_COMPRA' => 'test'
        ]);

        static::assertFalse($transaction->isSuccessful());
    }

    public function test_get_credit_card_number(): void
    {
        $transaction = new Transaction('foo', 'bar', [
            'card_detail' => [
                'card_number' => 'XXXXXXXXXXXX6623'
            ]
        ]);

        static::assertEquals(6623, $transaction->getCreditCardNumber());

        $transaction = new Transaction('foo', 'bar', [
            'card_detail' => [
                'card_number' => '6623'
            ]
        ]);

        static::assertEquals(6623, $transaction->getCreditCardNumber());

        $transaction = new Transaction('foo', 'bar', [
            'card_detail' => [
                'card_number' => 6623
            ]
        ]);

        static::assertEquals(6623, $transaction->getCreditCardNumber());
    }

    public function test_serializes_to_json(): void
    {
        $jsonString = '{"foo":{"bar":"baz"}}';

        $transaction = new Transaction('foo', 'bar', [
            'foo' => [
                'bar' => 'baz'
            ]
        ]);

        static::assertJson($transaction->toJson());
        static::assertJsonStringEqualsJsonString($jsonString, $transaction->toJson());
        static::assertEquals($jsonString, json_encode($transaction));
    }
}
