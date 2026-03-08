<?php

namespace Tests\Filament;

use Exception;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Filament\WebpayAction;
use Laragear\Transbank\Services\Transactions\Response;
use Livewire\LivewireServiceProvider;
use Tests\TestCase;
use Throwable;
use function array_merge;
use function class_exists;

class WebpayActionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedUnless(class_exists(ActionsServiceProvider::class), 'Filament was not installed');

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
            FormsServiceProvider::class,
            ActionsServiceProvider::class,
            SupportServiceProvider::class,
        ]);
    }

    public function test_creates_transaction_and_redirects(): void
    {
        Webpay::expects('create')
            ->with('test_order', 1000, '/test-return')
            ->andReturn(new Response('test-token', 'https://test.com/pay'));

        $action = WebpayAction::make('pay')
            ->amount(1000)
            ->buyOrder('test_order')
            ->returnUrl('/test-return');

        $result = $action->call();

        static::assertInstanceOf(RedirectResponse::class, $result);
        static::assertSame('https://test.com/pay?token_ws=test-token', $result->getTargetUrl());
    }

    public function test_creates_with_form_array(): void
    {
        Webpay::expects('create')
            ->with('test_order', 1000, '/test-return')
            ->andReturn(new Response('test-token', 'https://test.com/pay'));

        $action = WebpayAction::make('pay')->data([
            'amount' => 1000,
            'buyOrder' => 'test_order',
            'returnUrl' => '/test-return',
        ]);

        $result = $action->call();

        static::assertInstanceOf(RedirectResponse::class, $result);
        static::assertSame('https://test.com/pay?token_ws=test-token', $result->getTargetUrl());
    }

    public function test_catches_transaction_exception(): void
    {
        Webpay::expects('create')
            ->with('test_order', 1000, '/test-return')
            ->andThrow(new Exception('test exception'));

        $action = WebpayAction::make('pay')
            ->amount(1000)
            ->buyOrder('test_order')
            ->returnUrl('/test-return')
            ->rescue(function (Exception $exception) {
                static::assertSame('test exception', $exception->getMessage());
            });

        try {
            $action->call();
        } catch (Throwable $thrown) {
            static::assertSame('test exception', $thrown->getMessage());
        }
    }

    public function test_runs_before_and_after(): void
    {
        Webpay::expects('create')
            ->with('test_order', 1000, '/test-return')
            ->andReturn($webpayResponse = new Response('test-token', 'https://test.com/pay'));

        $calledBefore = false;
        $calledAfter = false;

        $action = WebpayAction::make('pay')
            ->amount(1000)
            ->buyOrder('test_order')
            ->returnUrl('/test-return')
            ->beforeResponse(function () use (&$calledBefore) {
                $calledBefore = true;
            })
            ->afterResponse(function ($response) use (&$calledAfter, $webpayResponse) {
                static::assertSame($webpayResponse, $response);
                $calledAfter = true;
            });

        $result = $action->call();

        static::assertInstanceOf(RedirectResponse::class, $result);
        static::assertSame('https://test.com/pay?token_ws=test-token', $result->getTargetUrl());

        static::assertTrue($calledBefore);
        static::assertTrue($calledAfter);
    }
}
