<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Payments;

use WC_Order;

interface GatewayAdapterInterface
{
    public function supportsPartialCapture(WC_Order $order): bool;

    public function captureFull(WC_Order $order): bool;

    public function capturePartial(WC_Order $order, float $amount): bool;
}
