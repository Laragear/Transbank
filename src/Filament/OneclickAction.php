<?php

namespace Laragear\Transbank\Filament;

use Closure;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Laragear\Transbank\Facades\Oneclick;
use Laragear\Transbank\Services\Transactions\Response;
use Throwable;
use function request;
use function view;

class OneclickAction extends Action
{
    protected Closure | null | string $username = 'name';

    protected Closure | null | string $email = 'email';

    protected Closure | null | string $responseUrl = null;

    protected ?Closure $beforeResponse = null;

    protected ?Closure $afterResponse = null;

    protected ?Closure $rescue = null;

    protected string | Htmlable | Closure | null $label = 'Register with Oneclick';

    protected function setUp(): void
    {
        parent::setUp();

        $this->action($this->openModal(...));
    }

    /**
     * Opens the modal and redirects the user using a POST redirection.
     *
     * @throws \Throwable
     */
    protected function openModal(): void
    {
        $this->modalContent(view('transbank::filament.post-redirect', [
            'url' => $this->evaluate($this->register(...))->toString() // @phpstan-ignore-line
        ]));

        $this->halt();
    }

    public function beforeResponse(Closure $callback): static
    {
        $this->beforeResponse = $callback;

        return $this;
    }

    public function afterResponse(Closure $callback): static
    {
        $this->afterResponse = $callback;

        return $this;
    }

    public function username(Closure|string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function email(Closure|string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function responseUrl(Closure|string $url): static
    {
        $this->responseUrl = $url;

        return $this;
    }

    public function returnUrl(Closure|string $url): static
    {
        return $this->responseUrl($url);
    }

    public function rescue(Closure $callback): static
    {
        $this->rescue = $callback;

        return $this;
    }

    /**
     * Execute the registration.
     */
    protected function register(array $data, ?Model $record = null): Response
    {
        $this->evaluate($this->beforeResponse, ['record' => $record]);

        try {
            $response = Oneclick::register(
                $data['username'] ?? $this->evaluate($this->username, ['record' => $record]),
                $data['email'] ?? $this->evaluate($this->email, ['record' => $record]),
                $data['responseUrl'] ?? $data['returnUrl'] ?? $this->evaluate($this->responseUrl, ['record' => $record]) ?? request()->fullUrl(),
            );
        } catch (Throwable $exception) {
            $result = $exception;

            if (isset($this->rescue)) {
                $result = $this->evaluate($this->rescue, ['exception' => $exception, 'record' => $record]);
            }

            throw ($result instanceof Throwable ? $result : $exception);
        }

        $this->evaluate($this->afterResponse, ['response' => $response, 'record' => $record]);

        return $response;
    }
}
