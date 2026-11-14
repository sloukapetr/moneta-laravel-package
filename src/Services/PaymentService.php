<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\Data\PaymentOrderData;
use SloukaPetr\Moneta\Enums\BatchStatus;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\Exceptions\InvalidPaymentOrderException;
use SloukaPetr\Moneta\MonetaManager;

final class PaymentService
{
    public function __construct(protected MonetaManager $moneta)
    {
    }

    /**
     * Prepare a batch for signature in Internet Banking. The API does not execute it.
     *
     * Endpoint: `POST /api/v4/vip/pisp/my/payments/batch`.
     *
     * @param  array<int, PaymentOrderData>  $orders
     */
    public function createBatch(array $orders): string
    {
        $response = $this->moneta->request('post', 'api/v4/vip/pisp/my/payments/batch', [
            'body' => $this->encode($orders),
        ]);

        $batchId = $response['batchId'] ?? null;

        if (! is_string($batchId) || $batchId === '') {
            throw new ApiException('Moneta batch response does not contain batchId.');
        }

        return $batchId;
    }

    /**
     * Endpoint: `GET /api/v4/vip/pisp/my/payments/batch/{batchId}/status`.
     */
    public function batchStatus(string $batchId): BatchStatus
    {
        return $this->status($this->moneta->get(
            'api/v4/vip/pisp/my/payments/batch/'.rawurlencode($batchId).'/status',
        ));
    }

    /**
     * Prepare payments for one-by-one signature in Internet Banking.
     *
     * Endpoint: `POST /api/v4/vip/pisp/my/payments/batch/onebyone`.
     *
     * @param  array<int, PaymentOrderData>  $orders
     * @return array<string, string>
     */
    public function createOneByOne(array $orders): array
    {
        $response = $this->moneta->request('post', 'api/v4/vip/pisp/my/payments/batch/onebyone', [
            'body' => $this->encode($orders),
        ]);

        $map = [];

        foreach ($response['items'] ?? [] as $item) {
            if (! is_array($item)) {
                throw new ApiException('Moneta one-by-one response is missing a transaction identifier.');
            }

            $instruction = (string) ($item['instructionIdentification'] ?? '');
            $transaction = (string) ($item['transactionIdentification'] ?? '');

            if ($instruction === '' || $transaction === '') {
                throw new ApiException('Moneta one-by-one response is missing a transaction identifier.');
            }

            $map[$instruction] = $transaction;
        }

        return $map;
    }

    /**
     * Endpoint: `GET /api/v4/vip/pisp/my/payments/batch/onebyone/{transactionId}/status`.
     */
    public function oneByOneStatus(string $transactionId): BatchStatus
    {
        return $this->status($this->moneta->get(
            'api/v4/vip/pisp/my/payments/batch/onebyone/'.rawurlencode($transactionId).'/status',
        ));
    }

    /**
     * @param  array<int, mixed>  $orders
     */
    private function encode(array $orders): string
    {
        if (count($orders) > 200) {
            throw new InvalidPaymentOrderException('A Moneta payment batch accepts at most 200 payments.');
        }

        $payments = [];

        foreach ($orders as $order) {
            if (! $order instanceof PaymentOrderData) {
                throw new InvalidPaymentOrderException('Each payment must be a PaymentOrderData.');
            }

            $payments[] = $order->toArray();
        }

        $json = json_encode(
            ['payments' => $payments],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $encoded = preg_replace('/"value":"(-?\d+\.\d{2})"/', '"value":$1', $json);

        if (! is_string($encoded)) {
            throw new ApiException('Unable to encode the Moneta payment batch.');
        }

        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function status(array $response): BatchStatus
    {
        $status = BatchStatus::tryFrom((string) ($response['instructionStatus'] ?? ''));

        if ($status === null) {
            throw new ApiException('Moneta batch status is missing or unknown.');
        }

        return $status;
    }
}
