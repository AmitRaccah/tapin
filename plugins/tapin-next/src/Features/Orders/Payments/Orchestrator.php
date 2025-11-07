<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Payments;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use WC_Order;

final class Orchestrator
{
    /**
     * @var array<string,GatewayAdapterInterface>
     */
    private array $adapters;

    /**
     * @param array<string,GatewayAdapterInterface> $adapters
     */
    public function __construct(array $adapters = [])
    {
        $this->adapters = $adapters;
    }

    /**
     * @param array<int,array<string,mixed>> $approvedMap
     */
    public function captureFinalized(WC_Order $order, array $approvedMap, float $amount): bool
    {
        $adapter = $this->resolveAdapter($order);
        if ($adapter instanceof GatewayAdapterInterface) {
            if ($adapter->supportsPartialCapture($order)) {
                return $adapter->capturePartial($order, $amount);
            }

            return $adapter->captureFull($order);
        }

        return AwaitingProducerGate::captureAndApprove($order);
    }

    private function resolveAdapter(WC_Order $order): ?GatewayAdapterInterface
    {
        $method = (string) $order->get_payment_method();
        if ($method === '' || !isset($this->adapters[$method])) {
            return null;
        }

        return $this->adapters[$method];
    }
}
