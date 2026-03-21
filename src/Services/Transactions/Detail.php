<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Contracts\Support\Arrayable;

/** @phpstan-consistent-constructor */
class Detail implements Arrayable
{
    /**
     * Create a new Detail instance.
     */
    public function __construct(
        public string $commerceCode,
        public string $buyOrder,
        public float $amount,
        public ?int $installments = null,
    ) {
        //
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $array = [
            'commerce_code' => $this->commerceCode,
            'buy_order' => $this->buyOrder,
            'amount' => $this->amount,
        ];

        if ($this->installments) {
            $array['installments'] = $this->installments;
        }

        return $array;
    }

    /**
     * Create a new Detail from the given attributes.
     */
    public static function make(
        string $commerceCode,
        string $buyOrder,
        float $amount,
        ?int $installments = null,
    ): static {
        return new static($commerceCode, $buyOrder, $amount, $installments);
    }
}
