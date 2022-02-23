<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Support\Fluent;
use JetBrains\PhpStorm\Pure;
use function array_key_exists;

class Transaction extends Fluent
{
    use DynamicallyAccess;

    public const STATUS_AUTHORIZED = 'AUTHORIZED';
    public const STATUS_NULLIFIED = 'NULLIFIED';
    public const STATUS_REVERSED = 'REVERSED';
    public const STATUS_PARTIALLY_NULLIFIED = 'PARTIALLY_NULLIFIED';
    public const STATUS_CAPTURED = 'PARTIALLY_NULLIFIED';
    public const STATUS_FAILED = 'FAILED';

    /**
     * Creates a new Transaction instance.
     *
     * @param  string  $service
     * @param  string  $action
     * @param  array  $attributes
     */
    public function __construct(public string $service, public string $action, array $attributes)
    {
        parent::__construct($attributes);
    }

    /**
     * Checks if the transaction was successful.
     *
     * @return bool
     */
    #[Pure]
    public function isSuccessful(): bool
    {
        // If TBK data has been received, immediately bail out.
        if (isset($this->TBK_ID_SESSION, $this->TBK_ORDEN_COMPRA)) {
            return false;
        }

        // If there is a native response code, return it.
        if (isset($this->response_code)) {
            $success = $this->response_code === 0;

            if (array_key_exists('status', $this->attributes)) {
                $success = $success && $this->status && $this->status !== self::STATUS_FAILED;
            }

            return $success;
        }

        return false;
    }

    /**
     * Check the transaction has failed.
     *
     * @return bool
     */
    #[Pure]
    public function isNotSuccessful(): bool
    {
        return ! $this->isSuccessful();
    }

    /**
     * Check the transaction has failed.
     *
     * @return bool
     */
    #[Pure]
    public function hasFailed(): bool
    {
        return $this->isNotSuccessful();
    }

    /**
     * Returns the Credit Card numbers as an integer, or null if it doesn't exist.
     *
     * @return int|null
     */
    public function getCreditCardNumber(): ?int
    {
        return (int) substr($this->attributes['card_detail']['card_number'] ?? '', -4);
    }
}
