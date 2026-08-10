<?php

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\MonetaManager;

class PaymentService
{
    private const DOMESTIC_PAYMENTS_PATH = 'api/v4/vip/pisp/payments/domestic';
    private const BATCH_PAYMENTS_PATH = 'api/v4/vip/pisp/payments/batch';
    private const STANDING_ORDERS_PATH = 'api/v4/vip/pisp/standing-orders';

    public function __construct(protected MonetaManager $moneta)
    {
    }

    public function createDomestic(array $data): array
    {
        return $this->moneta->post(self::DOMESTIC_PAYMENTS_PATH, $data);
    }

    public function getDomesticStatus(string $paymentId): array
    {
        return $this->moneta->get(self::DOMESTIC_PAYMENTS_PATH.'/'.$paymentId);
    }

    public function cancelDomestic(string $paymentId, array $data = []): array
    {
        return $this->moneta->post(self::DOMESTIC_PAYMENTS_PATH.'/'.$paymentId.'/cancellation', $data);
    }

    public function createBatch(array $items): array
    {
        return $this->moneta->post(self::BATCH_PAYMENTS_PATH, ['items' => $items]);
    }

    public function getBatchStatus(string $batchId): array
    {
        return $this->moneta->get(self::BATCH_PAYMENTS_PATH.'/'.$batchId);
    }

    public function cancelBatch(string $batchId, array $data = []): array
    {
        return $this->moneta->post(self::BATCH_PAYMENTS_PATH.'/'.$batchId.'/cancellation', $data);
    }

    public function createStandingOrder(array $data): array
    {
        return $this->moneta->post(self::STANDING_ORDERS_PATH, $data);
    }

    public function updateStandingOrder(string $standingOrderId, array $data): array
    {
        return $this->moneta->put(self::STANDING_ORDERS_PATH.'/'.$standingOrderId, $data);
    }

    public function getStandingOrder(string $standingOrderId): array
    {
        return $this->moneta->get(self::STANDING_ORDERS_PATH.'/'.$standingOrderId);
    }

    public function listStandingOrders(array $filters = []): array
    {
        return $this->moneta->get(self::STANDING_ORDERS_PATH, $filters);
    }

    public function cancelStandingOrder(string $standingOrderId, array $data = []): array
    {
        return $this->moneta->post(self::STANDING_ORDERS_PATH.'/'.$standingOrderId.'/cancellation', $data);
    }
}
