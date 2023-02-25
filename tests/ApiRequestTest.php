<?php

namespace Tests;

use Error;
use Laragear\Transbank\ApiRequest;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class ApiRequestTest extends PHPUnitTestCase
{
    public function test_serializes_to_json(): void
    {
        $request = new ApiRequest('foo', 'bar', [
            'foo' => 'bar'
        ]);

        static::assertEquals('{"foo":"bar"}', $request->toJson());
    }

    public function test_array_access(): void
    {
        $request = new ApiRequest('foo', 'bar', [
            'foo' => 'bar'
        ]);

        static::assertEquals('bar', $request['foo']);
        static::assertTrue(isset($request['foo']));
        static::assertFalse(isset($request['bar']));

        $request['baz'] = 'cougar';

        static::assertEquals('cougar', $request['baz']);

        unset($request['baz']);

        static::assertFalse(isset($request['baz']));
    }

    public function test_exception_on_non_existent_key(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Undefined array key "bar"');

        $request = new ApiRequest('foo', 'bar', [
            'foo' => 'bar'
        ]);

        $request['bar'];
    }

    public function test_serializes_empty_string_on_json(): void
    {
        $request = new ApiRequest('foo', 'bar', []);

        static::assertSame('', $request->toJson());
    }
}
