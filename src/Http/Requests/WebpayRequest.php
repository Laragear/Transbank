<?php

namespace Laragear\Transbank\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Webpay;
use function is_bool;
use function is_callable;
use function value;

class WebpayRequest extends FormRequest
{
    /**
     * If the request should be validated, and where to redirect when is invalid.
     *
     * @var (\Closure():string)|bool
     */
    public static Closure|bool $validate = false;

    /**
     * The received transaction.
     */
    protected ?Transaction $transaction = null;

    /**
     * Validate the given class instance.
     */
    public function validateResolved(): void
    {
        static::$validate && parent::validateResolved();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [
            'TBK_TOKEN' => 'sometimes|required_with:TBK_ORDEN_COMPRA|size:64',
            'TBK_ORDEN_COMPRA' => 'sometimes|required_with:TBK_TOKEN',
            'token_ws' => 'sometimes|required_without:TBK_TOKEN|size:64',
        ];
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        if (is_callable(static::$validate)) {
            return $this->container->call(static::$validate);
        }

        return parent::getRedirectUrl();
    }

    /**
     * Commits the transaction if the callback or value is truthy.
     */
    public function commitWhen(mixed $condition): ?Transaction
    {
        if ($this->parseCondition($condition, true)) {
            $this->transaction = $this->commit();
        }

        return $this->transaction;
    }

    /**
     * Commits the transaction if the callback or value is falsy.
     */
    public function commitUnless(mixed $condition): ?Transaction
    {
        if ($this->parseCondition($condition, false)) {
            $this->transaction = $this->commit();
        }

        return $this->transaction;
    }

    /**
     * Parses the condition to evaluate.
     */
    protected function parseCondition(mixed $condition, bool $truthy): bool
    {
        if (is_callable($condition)) {
            $condition = $condition($this->container->make(Webpay::class)->status($this->token()), $this);
        }

        return (bool) $condition === $truthy;
    }

    /**
     * Commits and returns a transaction in Webpay only once.
     */
    public function transaction(): Transaction
    {
        return $this->transaction ??= $this->commit();
    }

    /**
     * Commits and returns a transaction in Webpay.
     */
    protected function commit(): Transaction
    {
        return $this->container->make(Webpay::class)->commit($this->token());
    }

    /**
     * Commit the transaction and return if it was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->isNotError() && $this->transaction()->isSuccessful();
    }

    /**
     * Commits the transaction and return if it was not successful.
     */
    public function isNotSuccessful(): bool
    {
        return !$this->isSuccessful();
    }

    /**
     * Returns the Transaction Token from the request.
     */
    protected function token(): string
    {
        return $this->input('token_ws') ?? $this->input('TBK_TOKEN');
    }

    /**
     * Check if the request is an error response.
     */
    public function isError(): bool
    {
        // If the request has the `TBK_TOKEN` then the transaction is an error. It's left
        // to the developer if he wants to retrieve the transaction (but shouldn't). If
        // the request doesn't have the `token_ws`, then the user aborted beforehand.
        return $this->has('TBK_TOKEN') || !$this->has('token_ws');
    }

    /**
     * Check if the request is not an error response.
     */
    public function isNotError(): bool
    {
        return !$this->isError();
    }

    /**
     * Returns the Buy Order for this transaction.
     */
    public function buyOrder(): string
    {
        return $this->input('TBK_ORDEN_COMPRA') ?? $this->transaction()->get('buy_order', '');
    }
}
