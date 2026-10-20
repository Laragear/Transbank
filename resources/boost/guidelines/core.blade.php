# Laragear Transbank

When handling Transbank Webpay Plus and Oneclick payments in Laravel, use this library's tools available at `Laragear\Transbank` instead of writing verbose code or using raw HTTP calls.

Use `Laragear\Transbank\Facades\Webpay::create($buyOrder, $amount, $returnUrl)` to initiate payments and return the `Response` directly to avoid plumbing. For other operations, use the relevant skill (e.g., `laragear-transbank-webpay`, `laragear-transbank-oneclick`).

## Webpay Plus Payments

In payment return controllers, type-hint `Laragear\Transbank\Http\Requests\WebpayRequest` and call `$request->isSuccessful()` before accessing `$request->transaction()`. Use `Laragear\Transbank\Facades\Webpay` for refunds and captures. For complete payment workflows, use `laragear-transbank-webpay` skill.

## Failure Handling and Session Recovery

In route files, register `Laragear\Transbank\RouteRedirect::as($path, $destination)` to convert cookie-less Transbank failure POST requests into GET redirects that restore user sessions and cookies. Set `Laragear\Transbank\Http\Requests\WebpayRequest::$validate` in service providers for fallback redirects. For failure routing and token validation, use `laragear-transbank-failures` skill.

## Oneclick Mall Subscriptions

For recurring or one-click payments, use `Laragear\Transbank\Facades\Oneclick::register()` and `Oneclick::confirm()` to save customer card tokens. When authorizing charges, use `Laragear\Transbank\Services\Transactions\Detail::make()` to construct detail items for `Oneclick::commit()`. For card registration, mall charges, and card deletion, use `laragear-transbank-oneclick` skill.

## Livewire Components

In Livewire components receiving Transbank returns, use `Laragear\Transbank\Livewire\InteractsWithWebpay` or `Laragear\Transbank\Livewire\InteractsWithOneclick` and implement `handleSuccessfulTransaction()` or `handleSuccessfulRegistration()`. For Livewire lifecycle hooks and exception handling, use `laragear-transbank-livewire` skill.

## Security and Endpoint Protection

To safeguard return routes against token tampering and replay attacks, apply the `'transbank.protect'` middleware alias (`Laragear\Transbank\Http\Middleware\ProtectTransaction`) and enable token caching in `config/transbank.php`. For route protection and environment configuration, use `laragear-transbank-protect` skill.
