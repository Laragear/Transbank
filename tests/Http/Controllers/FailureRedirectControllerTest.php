<?php

namespace Tests\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laragear\Transbank\Http\Controllers\FailureRedirectController;
use Laragear\Transbank\RouteRedirect;
use Tests\TestCase;

class FailureRedirectControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        RouteRedirect::$csrfMiddleware = VerifyCsrfToken::class;

        parent::tearDown();
    }

    protected function defineWebRoutes($router)
    {
        $router->get('confirm', function (Request $request) {
            return $request->getUri();
        });
    }

    protected function useTestbenchMiddleware($app)
    {
        RouteRedirect::$csrfMiddleware = VerifyCsrfToken::class;
    }

    protected function routes(): RouteCollection
    {
        return $this->app->make('router')->getRoutes();
    }

    public function test_generates_redirection_route(): void
    {
        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm')->name('confirm.redirection');
        });

        $route = $this->routes()->getByAction(FailureRedirectController::class);

        static::assertSame('confirm', $route->uri());
        static::assertSame(['POST'], $route->methods());
        static::assertSame([
            ShareErrorsFromSession::class,
            StartSession::class,
            VerifyCsrfToken::class,
        ], $route->excludedMiddleware());
        static::assertSame(['destination' => 'confirm', 'status' => 303], $route->defaults);
    }

    /**
     * @define-env useTestbenchMiddleware
     */
    public function test_accepts_alternative_destination(): void
    {
        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm', 'test');

            Route::get('test', static function (Request $request): string {
                return 'test:' . $request->getUri();
            });
        });

        $route = $this->routes()->getByAction(FailureRedirectController::class);

        static::assertSame(['destination' => 'test', 'status' => 303], $route->defaults);

        $this->followingRedirects()
            ->post('confirm', ['token_ws' => 'foo'])
            ->assertOk()
            ->assertSee('test:http://localhost/test?token_ws=foo');
    }

    /**
     * @define-env useTestbenchMiddleware
     */
    public function test_redirect_route_to_get_method_of_same_name(): void
    {
        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm');
        });

        $this->post('confirm', ['token_ws' => 'test'])
            ->assertRedirect('http://localhost/confirm?token_ws=test');

        $this->followingRedirects()
            ->post('confirm', ['token_ws' => 'test'])
            ->assertOk()
            ->assertSee('http://localhost/confirm?token_ws=test');
    }

    /**
     * @define-env useTestbenchMiddleware
     */
    public function test_redirect_route_pushes_only_transbank_keys(): void
    {
        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm');
        });

        $this->post('confirm', [
            'token_ws' => 'foo',
            'TBK_TOKEN' => 'bar',
            'TBK_ID_SESSION' => 'baz',
            'TBK_ORDEN_COMPRA' => 'quz',
        ])
            ->assertRedirect(
                'http://localhost/confirm?token_ws=foo&TBK_TOKEN=bar&TBK_ID_SESSION=baz&TBK_ORDEN_COMPRA=quz'
            );

        $this->post('confirm', [
            'token_ws' => 'foo',
            'bar' => 'bar',
            'TBK_ID_SESSION' => 'baz',
            'quz' => 'quz',
        ])
            ->assertRedirect('http://localhost/confirm?token_ws=foo&TBK_ID_SESSION=baz');
    }

    /**
     * @define-env useTestbenchMiddleware
     */
    public function test_aborts_if_no_transbank_key_is_present(): void
    {
        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm');
        });

        $this->post('confirm')->assertNotFound();
    }

    public function test_accepts_different_csrf_middleware(): void
    {
        RouteRedirect::$csrfMiddleware = TestCsrfMiddleware::class;

        Route::group(['middleware' => 'web'], static function (): void {
            RouteRedirect::as('confirm');
        });

        $route = $this->routes()->getByAction(FailureRedirectController::class);

        static::assertSame('confirm', $route->uri());
        static::assertSame(['POST'], $route->methods());
        static::assertSame([
            ShareErrorsFromSession::class,
            StartSession::class,
            TestCsrfMiddleware::class,
        ], $route->excludedMiddleware());
        static::assertSame(['destination' => 'confirm', 'status' => 303], $route->defaults);
    }
}

class TestCsrfMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
