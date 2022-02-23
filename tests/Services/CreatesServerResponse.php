<?php

namespace Tests\Services;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Response as HttpResponse;
use function json_encode;

trait CreatesServerResponse
{
    protected function serverResponse(array $data, int $status = 200): HttpResponse
    {
        return new HttpResponse(
            new Response($status, ['content-type' => 'application/json',], json_encode($data)),
        );
    }
}
