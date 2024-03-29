<?php

namespace Laragear\Transbank\Services\Transactions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Stringable;
use function redirect;

class Response implements Stringable, Responsable
{
    /**
     * The name of the key holding the token.
     */
    public const WEBPAY_TOKEN = 'token_ws';

    /**
     * Response constructor.
     *
     * @param  string  $token
     * @param  string  $url
     * @param  string  $tokenName
     */
    public function __construct(
        protected string $token,
        protected string $url,
        protected string $tokenName = self::WEBPAY_TOKEN)
    {
        //
    }

    /**
     * Returns the transaction token that identifies it on Transbank.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Returns the transaction URL where the transaction can be retrieved.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Transforms the Response into a String for Webpay GET redirects.
     */
    public function __toString(): string
    {
        return $this->url . '?' . http_build_query([$this->tokenName => $this->token]);
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect()->away($this);
    }
}
