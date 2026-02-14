<?php

namespace Tests\Livewire;

use Exception;
use Illuminate\View\ViewException;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Livewire\InteractsWithWebpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Mockery;
use Tests\TestCase;
use Throwable;
use function array_merge;

class InteractsWithWebpayTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
        ]);
    }

    public function test_ignores_normal_requests(): void
    {
        Livewire::test(DummyInteractsWithWebPay::class)
            ->assertSet('beforeHandle', false)
            ->assertSet('afterReceived', false)
            ->assertSet('status', false)
            ->assertSet('exception', false)
            ->assertSet('successful', null)
            ->assertSet('transaction', null)
            ->assertSet('afterHandle', false)
            ->assertSet('nonWebpayResponse', true);
    }

    public function test_handles_webpay_exception(): void
    {
        Webpay::expects('commit')->with('invalid_token')->andThrow(new Exception('test exception'));

        $livewire = Livewire::withQueryParams(['token_ws' => 'invalid_token']);

        try {
            $livewire->test(DummyInteractsWithWebPay::class)->assertSet('exception', true);
        } catch (ViewException $exception) {
            static::assertStringStartsWith('test exception', $exception->getMessage());
        }
    }

    public function test_commits_successful_transaction(): void
    {
        $transaction = Mockery::mock(Transaction::class);
        $transaction->expects('isSuccessful')->andReturnTrue();

        Webpay::expects('commit')->with('valid_token')->andReturn($transaction);

        Livewire::withQueryParams(['token_ws' => 'valid_token'])
            ->test(DummyInteractsWithWebPay::class)
            ->assertSet('beforeHandle', true)
            ->assertSet('afterReceived', true)
            ->assertSet('successful', true)
            ->assertSet('status', true)
            ->assertSet('afterHandle', true)
            ->assertSet('nonWebpayResponse', false);
    }

    public function test_commits_failed_transaction(): void
    {
        $transaction = Mockery::mock(Transaction::class);
        $transaction->expects('isSuccessful')->andReturnFalse();

        Webpay::expects('commit')->with('valid_token')->andReturn($transaction);

        Livewire::withQueryParams(['token_ws' => 'valid_token'])
            ->test(DummyInteractsWithWebPay::class)
            ->assertSet('beforeHandle', true)
            ->assertSet('afterReceived', true)
            ->assertSet('successful', false)
            ->assertSet('status', true)
            ->assertSet('afterHandle', true)
            ->assertSet('nonWebpayResponse', false);
    }

    public function test_commits_aborted_transaction(): void
    {
        $transaction = Mockery::mock(Transaction::class);
        $transaction->expects('isSuccessful')->andReturnFalse();

        Webpay::expects('commit')->with('aborted_token')->andReturn($transaction);

        Livewire::withQueryParams(['TBK_TOKEN' => 'aborted_token'])
            ->test(DummyInteractsWithWebPay::class)
            ->assertSet('beforeHandle', true)
            ->assertSet('afterReceived', true)
            ->assertSet('successful', false)
            ->assertSet('status', true)
            ->assertSet('afterHandle', true)
            ->assertSet('nonWebpayResponse', false);
    }
}

class DummyInteractsWithWebPay extends Component
{
    use InteractsWithWebpay;

    public ?bool $successful = null;
    public bool $beforeHandle = false;
    public bool $exception = false;
    public bool $afterReceived = false;
    public bool $status = false;
    public bool $afterHandle = false;
    public bool $nonWebpayResponse = false;

    protected function beforeHandledTransaction(): void
    {
        $this->beforeHandle = true;
    }

    protected function afterTransactionReceived(Transaction $transaction): void
    {
        $this->afterReceived = true;
    }

    protected function handleTransactionStatus(Transaction $transaction): bool
    {
        $this->status = true;

        return $transaction->isSuccessful();
    }

    protected function handleSuccessfulTransaction(Transaction $transaction): void
    {
        $this->successful = true;
    }

    protected function handleFailedTransaction(Transaction $transaction): void
    {
        $this->successful = false;
    }

    protected function handleWebpayException(Throwable $exception): mixed
    {
        $this->exception = true;

        return $exception;
    }

    protected function afterHandledTransaction(Transaction $transaction): void
    {
        $this->afterHandle = true;
    }

    protected function handleNonWebpayResponse(): void
    {
        $this->nonWebpayResponse = true;
    }

    public function render()
    {
        return '<div>Checkout</div>';
    }
}
