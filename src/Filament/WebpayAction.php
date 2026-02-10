<?php

namespace Laragear\Transbank\Filament;

use Closure;
use Filament\Actions\Action;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Facades\Webpay;
use Livewire\Features\SupportRedirects\Redirector;
use Throwable;
use function request;

class WebpayAction extends Action
{
    protected Closure | null | string $buyOrder = null;

    protected float | Closure | int | null $amount = null;

    protected Closure | null | string $returnUrl = null;

    protected ?Closure $beforeResponse = null;

    protected ?Closure $afterResponse = null;

    protected ?Closure $rescue = null;

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

    public function buyOrder(Closure|string $buyOrder): static
    {
        $this->buyOrder = $buyOrder;

        return $this;
    }

    public function amount(Closure|int|float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function returnUrl(Closure|string $url): static
    {
        $this->returnUrl = $url;

        return $this;
    }

    public function rescue(Closure $callback): static
    {
        $this->rescue = $callback;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Pay with Webpay')->action($this->pay(...));
    }

    /**
     * Execute the payment.
     */
    protected function pay(array $data, $record = null): Redirector|RedirectResponse
    {
        $this->evaluate($this->beforeResponse, ['record' => $record]);

        try {
            $response = Webpay::create(
                $data['buyOrder'] ?? $this->evaluate($this->buyOrder, ['record' => $record]),
                $data['amount'] ?? $this->evaluate($this->amount, ['record' => $record]),
                $data['returnUrl'] ?? $this->evaluate($this->returnUrl, ['record' => $record]) ?? request()->fullUrl(),
            );
        } catch (Throwable $exception) {
            $result = $exception;

            if (isset($this->rescue)) {
                $result = $this->evaluate($this->rescue, ['exception' => $exception, 'record' => $record]);
            }

            throw ($result instanceof Throwable ? $result : $exception);
        }

        $this->evaluate($this->afterResponse, ['response' => $response, 'record' => $record]);

        return redirect()->away((string) $response);
    }
}
