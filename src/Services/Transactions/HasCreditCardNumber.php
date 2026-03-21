<?php

namespace Laragear\Transbank\Services\Transactions;

use function substr;

trait HasCreditCardNumber
{
    /**
     * Returns the Credit Card numbers as an integer, or null if it doesn't exist.
     */
    public function getCreditCardNumber(): ?int
    {
        if (isset($this->attributes['card_number'])) {
            return (int) substr($this->attributes['card_number'], -4);
        }

        if (isset($this->attributes['card_detail']['card_number'])) {
            return (int) substr($this->attributes['card_detail']['card_number'], -4);
        }

        return null;
    }
}
