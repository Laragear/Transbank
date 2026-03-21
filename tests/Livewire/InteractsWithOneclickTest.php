<?php

namespace Tests\Livewire;

use Exception;
use Illuminate\View\ViewException;
use Laragear\Transbank\Facades\Oneclick;
use Laragear\Transbank\Livewire\InteractsWithOneclick;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Mockery;
use Tests\TestCase;
use Throwable;
use function array_merge;

class InteractsWithOneclickTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
        ]);
    }

    public function test_ignores_normal_requests(): void
    {
        Livewire::test(DummyInteractsWithOneclick::class)
            ->assertSet('beforeHandle', false)
            ->assertSet('afterReceived', false)
            ->assertSet('status', false)
            ->assertSet('exception', false)
            ->assertSet('successful', null)
            ->assertSet('transaction', null)
            ->assertSet('afterHandle', false)
            ->assertSet('nonOneclickResponse', true);
    }

    public function test_handles_oneclick_exception(): void
    {
        Oneclick::expects('confirm')->with('invalid_token')->andThrow(new Exception('test exception'));

        $livewire = Livewire::withQueryParams(['TBK_TOKEN' => 'invalid_token']);

        try {
            $livewire->test(DummyInteractsWithOneclick::class)->assertSet('exception', true);
        } catch (ViewException $exception) {
            static::assertStringStartsWith('test exception', $exception->getMessage());
        }
    }

    public function test_commits_successful_transaction(): void
    {
        $transaction = Mockery::mock(Transaction::class);
        $transaction->expects('isSuccessful')->andReturnTrue();

        Oneclick::expects('confirm')->with('valid_token')->andReturn($transaction);

        Livewire::withQueryParams(['TBK_TOKEN' => 'valid_token'])
            ->test(DummyInteractsWithOneclick::class)
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

        Oneclick::expects('confirm')->with('valid_token')->andReturn($transaction);

        Livewire::withQueryParams(['TBK_TOKEN' => 'valid_token'])
            ->test(DummyInteractsWithOneclick::class)
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

        Oneclick::expects('confirm')->with('aborted_token')->andReturn($transaction);

        Livewire::withQueryParams(['TBK_TOKEN' => 'aborted_token'])
            ->test(DummyInteractsWithOneclick::class)
            ->assertSet('beforeHandle', true)
            ->assertSet('afterReceived', true)
            ->assertSet('successful', false)
            ->assertSet('status', true)
            ->assertSet('afterHandle', true)
            ->assertSet('nonWebpayResponse', false);
    }
}

class DummyInteractsWithOneclick extends Component
{
    use InteractsWithOneclick;

    public ?bool $successful = null;
    public bool $beforeHandle = false;
    public bool $exception = false;
    public bool $afterReceived = false;
    public bool $status = false;
    public bool $afterHandle = false;
    public bool $nonOneclickResponse = false;

    protected function beforeHandledRegistration(): void
    {
        $this->beforeHandle = true;
    }

    protected function afterRegistrationReceived(Transaction $transaction): void
    {
        $this->afterReceived = true;
    }

    protected function handleRegistrationStatus(Transaction $transaction): bool
    {
        $this->status = true;

        return $transaction->isSuccessful();
    }

    protected function handleSuccessfulRegistration(Transaction $transaction): void
    {
        $this->successful = true;
    }

    protected function handleFailedRegistration(Transaction $transaction): void
    {
        $this->successful = false;
    }

    protected function handleOneclickException(Throwable $exception): mixed
    {
        $this->exception = true;

        return $exception;
    }

    protected function afterHandledRegistration(Transaction $transaction): void
    {
        $this->afterHandle = true;
    }

    protected function handleNonOneclickResponse(): void
    {
        $this->nonOneclickResponse = true;
    }

    public function render()
    {
        return '<div>Checkout</div>';
    }
}
