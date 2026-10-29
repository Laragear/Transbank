<?php

return [
    'title' => 'Webpay — Local emulator',
    'merchant' => 'EMULATOR',
    'paying_at' => 'You are paying at:',
    'amount' => 'Amount to pay:',
    'buy_order' => 'Buy order',
    'select_result' => 'Select the result of the transaction:',
    'results' => [
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'abort' => 'Aborted by the user',
        'fail' => 'Failed (form error)',
    ],
    'return_options' => 'Return parameters (optional)',
    'method' => 'Return method',
    'method_auto' => 'As Transbank (GET)',
    'token_key' => 'Token parameter name',
    'token_key_auto' => 'As Transbank',
    'tbk' => 'TBK_* variables',
    'tbk_auto' => 'Depends on the result',
    'tbk_all' => 'Include all',
    'tbk_none' => 'Do not include',
    'pay' => 'Pay',
    'redirecting' => 'Please wait while we redirect you to the commerce...',
    'notice' => 'This is a local emulator, not the real Transbank. No money was charged.',
];
