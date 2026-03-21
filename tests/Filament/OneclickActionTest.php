<?php

namespace Tests\Filament;

use Exception;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Support\Exceptions\Halt;
use Filament\Support\SupportServiceProvider;
use Laragear\Transbank\Facades\Oneclick;
use Laragear\Transbank\Filament\OneclickAction;
use Laragear\Transbank\Services\Transactions\Response;
use Livewire\LivewireServiceProvider;
use Tests\TestCase;
use Throwable;
use function array_merge;
use function class_exists;

class OneclickActionTest extends TestCase
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
        Oneclick::expects('register')
            ->with('test-username', 'test@email.com', '/test-return')
            ->andReturn(new Response('test-token', 'https://test.com/pay'));

        $action = OneclickAction::make('pay')
            ->username('test-username')
            ->email('test@email.com')
            ->returnUrl('/test-return');

        try {
            $action->call();
        } catch (Throwable $halt) {
            static::assertInstanceOf(Halt::class, $halt);
        }
    }

    public function test_creates_with_form_array(): void
    {
        Oneclick::expects('register')
            ->with('test-username', 'test@email.com', '/test-return')
            ->andReturn(new Response('test-token', 'https://test.com/pay'));

        $action = OneclickAction::make('pay')->data([
            'username' => 'test-username',
            'email' => 'test@email.com',
            'returnUrl' => '/test-return',
        ]);

        try {
            $action->call();
        } catch (Throwable $halt) {
            static::assertInstanceOf(Halt::class, $halt);
        }
    }

    public function test_catches_transaction_exception(): void
    {
        Oneclick::expects('register')
            ->with('test-username', 'test@email.com', '/test-return')
            ->andThrow(new Exception('test exception'));

        $action = OneclickAction::make('pay')
            ->username('test-username')
            ->email('test@email.com')
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
        Oneclick::expects('register')
            ->with('test-username', 'test@email.com', '/test-return')
            ->andReturn($webpayResponse = new Response('test-token', 'https://test.com/pay'));

        $calledBefore = false;
        $calledAfter = false;

        $action = OneclickAction::make('pay')
            ->username('test-username')
            ->email('test@email.com')
            ->returnUrl('/test-return')
            ->beforeResponse(function () use (&$calledBefore) {
                $calledBefore = true;
            })
            ->afterResponse(function ($response) use (&$calledAfter, $webpayResponse) {
                static::assertSame($webpayResponse, $response);
                $calledAfter = true;
            });

        try {
            $action->call();
        } catch (Throwable $halt) {
            static::assertInstanceOf(Halt::class, $halt);
        }

        static::assertTrue($calledBefore);
        static::assertTrue($calledAfter);
    }
}
