<?php

declare(strict_types=1);

use App\DTO\Payment\PaymentInitiationRequest;
use App\Services\Payment\Gateways\FakePaymentGateway;
use Tests\TestCase;

uses(TestCase::class);

test('fake gateway supports ngn and usd only', function (): void {
    $gateway = new FakePaymentGateway;

    expect($gateway->name())->toBe('fake')
        ->and($gateway->supportedCurrencies())->toContain('NGN', 'USD')
        ->and($gateway->supportsCurrency('EUR'))->toBeFalse()
        ->and($gateway->supportedMethods())->not->toBeEmpty();
});

test('fake gateway verify succeeds for FAKE-OK references', function (): void {
    $gateway = new FakePaymentGateway;

    $ok = $gateway->verifyPayment('FAKE-OK-ORDER-1');
    $fail = $gateway->getPaymentStatus('RANDOM-REF');

    expect($ok->successful)->toBeTrue()
        ->and($ok->providerTransactionId)->toStartWith('fake_txn_')
        ->and($fail->successful)->toBeFalse();
});

test('fake gateway initialize returns auth url', function (): void {
    config(['payment.drivers.fake.authorization_url' => 'https://payments.test/fake/authorize']);

    $gateway = new FakePaymentGateway;
    $result = $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '25.00',
        currency: 'USD',
        email: 'a@b.com',
        reference: 'FAKE-OK-REF',
    ));

    expect($result->authorizationUrl)->toBe('https://payments.test/fake/authorize?reference=FAKE-OK-REF')
        ->and($result->provider)->toBe('fake');
});
