<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

class TransactionDetailed extends Fluent
{
    use DynamicallyAccess;
    use HasCreditCardNumber;

    /**
     * Create a new Oneclick Transaction instance.
     */
    public function __construct(public string $service, public string $action, array $attributes)
    {
        parent::__construct($attributes);
    }

    /**
     * Check if all details were successful.
     */
    public function isAllSuccessful(): bool
    {
        foreach ($this->array('details') as $detail) {
            if ($detail['response_code'] !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any detail failed.
     */
    public function isNotAllSuccessful(): bool
    {
        return !$this->isAllSuccessful();
    }

    /**
     * Returns each all the details of the transaction.
     *
     * @return \Illuminate\Support\Collection<int, \Laragear\Transbank\Services\Transactions\Transaction>
     */
    public function details(): Collection
    {
        $number = $this->get('card_detail.card_number');

        return Collection::make($this->array('details'))->map(function (array $details) use ($number): Transaction {
            return new Transaction($this->service, $this->action, array_merge([
                'card_detail' => ['card_number' => $number],
            ], $details));
        });
    }
}
