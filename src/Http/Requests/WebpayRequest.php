<?php

namespace Laragear\Transbank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Webpay;
use function is_callable;

class WebpayRequest extends FormRequest
{
    /**
     * The received transaction.
     *
     * @var \Laragear\Transbank\Services\Transactions\Transaction|null
     */
    protected ?Transaction $transaction = null;

    /**
     * Validate the given class instance.
     *
     * @return void
     */
    public function validateResolved(): void
    {
        // Don't validate this as there is nothing to validate.
    }

    /**
     * Commits the transaction if the callback or value is truthy.
     *
     * @param  mixed  $condition
     * @return \Laragear\Transbank\Services\Transactions\Transaction|null
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
     *
     * @param  mixed  $condition
     * @return \Laragear\Transbank\Services\Transactions\Transaction|null
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
     *
     * @param  mixed  $condition
     * @param  bool  $truthy
     * @return bool
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
     *
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     */
    public function transaction(): Transaction
    {
        return $this->transaction ??= $this->commit();
    }

    /**
     * Commits and returns a transaction in Webpay.
     *
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     */
    protected function commit(): Transaction
    {
        return $this->container->make(Webpay::class)->commit($this->token());
    }

    /**
     * Commit the transaction and return if it was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->transaction()->isSuccessful();
    }

    /**
     * Commits the transaction and return if it was not successful.
     *
     * @return bool
     */
    public function isNotSuccessful(): bool
    {
        return !$this->isSuccessful();
    }

    /**
     * Returns the Transaction Token from the request.
     *
     * @return string
     */
    protected function token(): string
    {
        return $this->query('token_ws') ?? $this->input('TBK_TOKEN');
    }
}
