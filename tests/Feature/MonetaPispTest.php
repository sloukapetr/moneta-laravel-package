<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaPispTest extends TestCase
{
    public function test_domestic_payment_endpoints_are_called(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/pisp/payments/domestic' => Http::response(['paymentId' => 'pay-1'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/payments/domestic/pay-1' => Http::response(['status' => 'ACCP'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/payments/domestic/pay-1/cancellation' => Http::response(['status' => 'CANC'], 200),
        ]);

        $service = Moneta::payments();

        $created = $service->createDomestic(['amount' => 100]);
        $status = $service->getDomesticStatus('pay-1');
        $cancel = $service->cancelDomestic('pay-1');

        $this->assertSame('pay-1', $created['paymentId']);
        $this->assertSame('ACCP', $status['status']);
        $this->assertSame('CANC', $cancel['status']);
    }

    public function test_batch_and_standing_order_endpoints_are_called(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/pisp/payments/batch' => Http::response(['batchId' => 'batch-1'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/payments/batch/batch-1' => Http::response(['status' => 'ACCP'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/payments/batch/batch-1/cancellation' => Http::response(['status' => 'CANC'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/standing-orders' => Http::response(['standingOrderId' => 'so-1'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/standing-orders/so-1' => Http::response(['standingOrderId' => 'so-1', 'status' => 'ACTV'], 200),
            'https://api.moneta.cz/api/v4/vip/pisp/standing-orders/so-1/cancellation' => Http::response(['status' => 'CANC'], 200),
        ]);

        $service = Moneta::payments();

        $batch = $service->createBatch([['amount' => 10]]);
        $batchStatus = $service->getBatchStatus('batch-1');
        $batchCancel = $service->cancelBatch('batch-1');

        $standing = $service->createStandingOrder(['amount' => 50]);
        $standingUpdated = $service->updateStandingOrder('so-1', ['amount' => 60]);
        $standingDetail = $service->getStandingOrder('so-1');
        $standingList = $service->listStandingOrders();
        $standingCancel = $service->cancelStandingOrder('so-1');

        $this->assertSame('batch-1', $batch['batchId']);
        $this->assertSame('ACCP', $batchStatus['status']);
        $this->assertSame('CANC', $batchCancel['status']);
        $this->assertSame('so-1', $standing['standingOrderId']);
        $this->assertSame('so-1', $standingUpdated['standingOrderId']);
        $this->assertSame('so-1', $standingDetail['standingOrderId']);
        $this->assertSame('so-1', $standingList['standingOrderId']);
        $this->assertSame('CANC', $standingCancel['status']);
    }
}
