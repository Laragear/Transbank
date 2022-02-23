<?php

namespace Tests;

use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\TransbankServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TransbankServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Webpay' => Webpay::class];
    }
}
