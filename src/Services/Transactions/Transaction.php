<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Support\Fluent;
use function array_key_exists;
use function in_array;

class Transaction extends Fluent
{
    use DynamicallyAccess;
    use HasCreditCardNumber;

    public const string STATUS_AUTHORIZED = 'AUTHORIZED';
    public const string STATUS_NULLIFIED = 'NULLIFIED';
    public const string STATUS_REVERSED = 'REVERSED';
    public const string STATUS_PARTIALLY_NULLIFIED = 'PARTIALLY_NULLIFIED';
    public const string STATUS_CAPTURED = 'CAPTURED';
    public const string STATUS_FAILED = 'FAILED';

    /**
     * Creates a new Transaction instance.
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
    public function isSuccessful(): bool
    {
        // If TBK data has been received, immediately bail out. Transbank sends
        // the session id as TBK_ID_SESION, but older code used TBK_ID_SESSION.
        if ((isset($this->TBK_ID_SESSION) || isset($this->TBK_ID_SESION)) && isset($this->TBK_ORDEN_COMPRA)) {
            return false;
        }

        // If there is a native response code, return it.
        if (isset($this->response_code)) {
            $success = $this->response_code === 0;

            if (array_key_exists('status', $this->attributes)) {
                $success = $success && $this->attributes['status'] && in_array($this->attributes['status'], [
                    static::STATUS_AUTHORIZED,
                    static::STATUS_NULLIFIED,
                    static::STATUS_REVERSED,
                    static::STATUS_PARTIALLY_NULLIFIED,
                    static::STATUS_CAPTURED
                ]);
            }

            return $success;
        }

        // When Transbank reverses a charge refunded on the same day, the answer is a bare
        // body with no `response_code`: {"type": "REVERSED"}. Treat it as successful so
        // consumers don't mistake an applied refund for a rejection. Scope this to the
        // refund action only, and only when there is no `error_message`.
        if ($this->action === 'refund' && ! isset($this->error_message)) {
            $type = $this->get('type');

            if (in_array($type, [static::STATUS_REVERSED, static::STATUS_NULLIFIED], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check the transaction has failed.
     */
    public function isNotSuccessful(): bool
    {
        return ! $this->isSuccessful();
    }

    /**
     * Check the transaction has failed.
     */
    public function hasFailed(): bool
    {
        return $this->isNotSuccessful();
    }
}
