<?php

namespace Tests\Services\Transactions;

use Illuminate\Http\Request;
use Laragear\Transbank\Services\Transactions\Response;
use Tests\TestCase;

class ResponseTest extends TestCase
{
    public function test_response_transforms_into_webpay_url(): void
    {
        static::assertEquals(
            'https://api.tbk.cl/transaction?token_ws=foo',
            (string)(new Response('foo', 'https://api.tbk.cl/transaction'))
        );
    }

    public function test_response_transforms_into_redirect(): void
    {
        $response = (new Response('foo', 'https://api.tbk.cl/transaction'))->toResponse(new Request());

        static::assertTrue($response->isRedirection());
        static::assertSame('https://api.tbk.cl/transaction?token_ws=foo', $response->getTargetUrl());
    }

    public function test_response_changes_token_key_name(): void
    {
        $response = new Response('foo', 'https://api.tbk.cl/', 'bar');

        static::assertSame('https://api.tbk.cl/?bar=foo', $response->toResponse(new Request())->getTargetUrl());
        static::assertSame('https://api.tbk.cl/?bar=foo', (string) $response);
    }
}
