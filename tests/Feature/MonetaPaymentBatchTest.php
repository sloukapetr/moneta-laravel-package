<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use SloukaPetr\Moneta\Data\PaymentOrderData;
use SloukaPetr\Moneta\Enums\BatchStatus;
use SloukaPetr\Moneta\Exceptions\InvalidPaymentOrderException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaPaymentBatchTest extends TestCase
{
    public function test_invalid_order_and_oversized_batch_throw_before_http(): void
    {
        Http::fake();

        try {
            new PaymentOrderData(150050, 'CZK', 'not-an-account', '123456789/0600');
            $this->fail('An invalid account was accepted.');
        } catch (InvalidPaymentOrderException) {
        }

        try {
            new PaymentOrderData(150050, 'CZK', '123456789/0600', '19-2000145399/0800', vs: '12345678901');
            $this->fail('A symbol longer than 10 digits was accepted.');
        } catch (InvalidPaymentOrderException) {
        }

        $orders = [];

        for ($i = 0; $i < 201; $i++) {
            $orders[] = new PaymentOrderData(100, 'CZK', '123456789/0600', '19-2000145399/0800');
        }

        try {
            Moneta::payments()->createBatch($orders);
            $this->fail('A batch of 201 payments was accepted.');
        } catch (InvalidPaymentOrderException) {
        }

        Http::assertNothingSent();
    }

    public function test_create_batch_sends_the_spec_shape_and_maps_status(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/pisp/my/payments/batch' => Http::response(['batchId' => 'B-1'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/my/payments/batch/B-1/status' => Http::sequence()
                ->push(['instructionStatus' => 'ACTC'])
                ->push(['instructionStatus' => 'ACSP'])
                ->push(['instructionStatus' => 'RJCT']),
        ]);

        $batchId = Moneta::payments()->createBatch([
            new PaymentOrderData(
                amountMinor: 150050,
                currency: 'CZK',
                debtorAccount: '123456789/0600',
                creditorAccount: 'CZ65 0800 0000 1920 0014 5399',
                vs: '2026001',
                ks: '0308',
                message: 'Faktura 1.00',
                creditorName: 'Dodavatel',
                requestedExecutionDate: '2026-11-20',
                instructionId: 'INV-1',
            ),
            new PaymentOrderData(100, 'CZK', '19-2000145399/0800', '123456789/0600'),
        ]);

        $this->assertSame('B-1', $batchId);

        $body = Http::recorded()[0][0]->body();

        $this->assertStringContainsString('"value":1500.50', $body);
        $this->assertStringContainsString('"value":1.00', $body);
        $this->assertStringNotContainsString('"value":"', $body);
        $this->assertStringContainsString('"currency":"CZK"', $body);
        $this->assertStringContainsString('"instructionPriority":"NORM"', $body);
        $this->assertStringNotContainsString('HIGH', $body);
        $this->assertStringNotContainsString('INST', $body);
        $this->assertStringContainsString('"identification":"123456789/0600"', $body);
        $this->assertStringContainsString('"iban":"CZ6508000000192000145399"', $body);
        $this->assertStringContainsString('"reference":"VS:2026001,KS:0308"', $body);
        $this->assertStringContainsString('"unstructured":"Faktura 1.00"', $body);
        $this->assertStringContainsString('"instructionIdentification":"INV-1"', $body);
        $this->assertStringContainsString('"requestedExecutionDate":"2026-11-20"', $body);
        $this->assertStringContainsString('"name":"Dodavatel"', $body);

        $payments = Moneta::payments();

        $this->assertSame(BatchStatus::AwaitingSignature, $payments->batchStatus($batchId));
        $this->assertSame(BatchStatus::Signed, $payments->batchStatus($batchId));
        $this->assertSame(BatchStatus::Rejected, $payments->batchStatus($batchId));
    }

    public function test_one_by_one_returns_transaction_ids_and_status(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/pisp/my/payments/batch/onebyone' => Http::response([
                'totalCount' => 1,
                'items' => [[
                    'instructionIdentification' => 'INV-1',
                    'transactionIdentification' => 'tx-9',
                ]],
            ], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/my/payments/batch/onebyone/tx-9/status' => Http::response([
                'instructionStatus' => 'ACSP',
            ], 200),
        ]);

        $map = Moneta::payments()->createOneByOne([
            new PaymentOrderData(250, 'CZK', '123456789/0600', '19-2000145399/0800', instructionId: 'INV-1'),
        ]);

        $this->assertSame(['INV-1' => 'tx-9'], $map);
        $this->assertSame(BatchStatus::Signed, Moneta::payments()->oneByOneStatus('tx-9'));
    }

    public function test_fake_records_a_batch_and_its_status(): void
    {
        $fake = Moneta::fake();
        $fake->assertNoBatchCreated();

        $batchId = Moneta::payments()->createBatch([
            new PaymentOrderData(150050, 'CZK', '123456789/0600', '19-2000145399/0800', vs: '2026001'),
        ]);

        $this->assertSame('batch-1', $batchId);
        $this->assertCount(0, Http::recorded());

        $fake->assertBatchCreated(function (array $payload): bool {
            return ($payload['payments'][0]['amount']['instructedAmount']['currency'] ?? null) === 'CZK'
                && ($payload['payments'][0]['paymentTypeInformation']['instructionPriority'] ?? null) === 'NORM'
                && ($payload['payments'][0]['remittanceInformation']['structured']['creditorReferenceInformation']['reference'] ?? null) === 'VS:2026001';
        });

        $this->assertSame(BatchStatus::AwaitingSignature, Moneta::payments()->batchStatus($batchId));

        $fake->withBatchStatus($batchId, BatchStatus::Signed);

        $this->assertSame(BatchStatus::Signed, Moneta::payments()->batchStatus($batchId));

        try {
            $fake->assertNoBatchCreated();
            $this->fail('A recorded batch was treated as absent.');
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString('A Moneta payment batch was created', $exception->getMessage());
        }
    }
}
