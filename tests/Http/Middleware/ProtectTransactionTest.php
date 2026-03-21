<?php

namespace Tests\Http\Middleware;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProtectTransactionTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('confirm', function () {
            return 'ok';
        })->middleware('transbank.protect');

        $router->post('confirm', function () {
            return 'ok';
        })->middleware('transbank.protect');
    }

    public function test_aborts_if_token_absent(): void
    {
        $this->get('confirm')->assertNotFound();
        $this->post('confirm')->assertNotFound();
    }

    public static function providesToken(): array
    {
        return [
            ['token_ws', 'foo'],
            ['TBK_TOKEN', 'foo'],
            ['token', 'foo'],
        ];
    }

    #[DataProvider('providesToken')]
    public function test_accepts_if_token_present(string $name, string $token): void
    {
        $this->get("confirm?$name=$token")->assertOk();
        $this->post('confirm', [$name => $token])->assertOk();
    }

    #[DataProvider('providesToken')]
    public function test_aborts_if_token_not_saved_previously(string $name, string $token): void
    {
        $this->app->make('config')->set('transbank.protect.enabled', true);

        $this->get("confirm?$name=$token")->assertNotFound();
        $this->post('confirm', [$name => $token])->assertNotFound();
    }

    #[DataProvider('providesToken')]
    public function test_accepts_once_if_webpay_token_saved_previously(string $name, string $token): void
    {
        $this->app->make('config')->set('transbank.protect.enabled', true);

        $this->app->make('cache')->put('transbank|token|foo', true);
        $this->app->make('cache')->put('transbank|token|bar', true);

        $this->get("confirm?$name=$token")->assertOk();
        $this->get("confirm?$name=$token")->assertNotFound();

        $this->post('confirm', [$name => 'bar'])->assertOk();
        $this->post('confirm', [$name => 'bar'])->assertNotFound();
    }

    #[DataProvider('providesToken')]
    public function test_uses_custom_cache_store(string $name, string $token): void
    {
        $mock = Mockery::mock(Repository::class);

        $mock->expects('pull')
            ->with('transbank|token|foo')
            ->times(4)
            ->andReturn(true, false, true, false);

        $this->swap('cache', Mockery::mock(Factory::class))
            ->expects('store')
            ->with('foo')
            ->times(4)
            ->andReturn($mock);

        $this->app->make('config')->set([
            'transbank.protect.enabled' => true,
            'transbank.protect.store' => 'foo',
        ]);

        $this->get("confirm?$name=$token")->assertOk();
        $this->get("confirm?$name=$token")->assertNotFound();

        $this->post('confirm', [$name => $token])->assertOk();
        $this->post('confirm', [$name => $token])->assertNotFound();
    }

    #[DataProvider('providesToken')]
    public function test_uses_custom_cache_prefix(string $name, string $token): void
    {
        $mock = Mockery::mock(Repository::class);

        $mock->expects('pull')
            ->with('test_prefix|foo')
            ->times(4)
            ->andReturn(true, false, true, false);

        $this->swap('cache', Mockery::mock(Factory::class))
            ->expects('store')
            ->with(null)
            ->times(4)
            ->andReturn($mock);

        $this->app->make('config')->set([
            'transbank.protect.enabled' => true,
            'transbank.protect.prefix' => 'test_prefix',
        ]);

        $this->get("confirm?$name=$token")->assertOk();
        $this->get("confirm?$name=$token")->assertNotFound();

        $this->post('confirm', [$name => $token])->assertOk();
        $this->post('confirm', [$name => $token])->assertNotFound();
    }
}
