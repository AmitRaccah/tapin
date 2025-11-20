<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Tickets;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Features\ProductPage\PurchaseModal\Messaging\MessagesProvider;
use Tapin\Events\Support\TicketFee;
use Tapin\Events\Support\TicketTypeTracer;

final class TicketTypeCache
{
    private MessagesProvider $messages;

    /** @var array<int,array{list: array<int,array<string,mixed>>, index: array<string,array<string,mixed>>}> */
    private array $cache = [];

    public function __construct(MessagesProvider $messages)
    {
        $this->messages = $messages;
    }

    /**
     * @return array{list: array<int,array<string,mixed>>, index: array<string,array<string,mixed>>}
     */
    public function ensureTicketTypeCache(int $productId): array
    {
        if (!isset($this->cache[$productId])) {
            $rawTypes = TicketTypesRepository::get($productId);
            $activeWindow = SaleWindowsRepository::findActive($productId, $rawTypes);
            $list = [];
            $index = [];

            foreach ($rawTypes as $type) {
                if (!is_array($type)) {
                    continue;
                }

                $id = isset($type['id']) ? (string) $type['id'] : '';
                if ($id === '') {
                    continue;
                }

                $basePrice = isset($type['base_price']) ? (float) $type['base_price'] : 0.0;
                $rawPrice  = $basePrice;
                if (is_array($activeWindow) && isset($activeWindow['prices'][$id]) && (float) $activeWindow['prices'][$id] > 0.0) {
                    $rawPrice = (float) $activeWindow['prices'][$id];
                }

                $finalPrice = TicketFee::applyToPrice($rawPrice, $productId);
                $feeAmount  = TicketFee::getFeeAmount($rawPrice, $productId);
                $feePercent = TicketFee::getPercent($productId);

                $available = isset($type['available']) ? (int) $type['available'] : 0;
                $capacity = isset($type['capacity']) ? (int) $type['capacity'] : 0;

                if ($capacity > 0 && $available > $capacity) {
                    $available = $capacity;
                }

                $available = max(0, $available);
                $capacity = max(0, $capacity);
                $isSoldOut = $capacity > 0 && $available <= 0;

                $entry = [
                    'id'                 => $id,
                    'name'               => (string) ($type['name'] ?? $id),
                    'description'        => (string) ($type['description'] ?? ''),
                    'price'              => $finalPrice,
                    'base_price'         => $basePrice,
                    'fee_amount'         => $feeAmount,
                    'fee_percent'        => $feePercent,
                    'available'          => $available,
                    'capacity'           => $capacity,
                    'price_html'         => $this->formatTicketPrice($finalPrice),
                    'availability_label' => $this->formatAvailability($capacity, $available),
                    'sold_out'           => $isSoldOut,
                ];

                $list[] = $entry;
                $index[$id] = $entry;
            }

            $this->cache[$productId] = [
                'list'  => $list,
                'index' => $index,
            ];

            if (class_exists(TicketTypeTracer::class)) {
                TicketTypeTracer::ensure($list);
            }
        }

        return $this->cache[$productId];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getTicketTypeIndex(int $productId): array
    {
        $cache = $this->ensureTicketTypeCache($productId);
        return $cache['index'];
    }

    private function formatTicketPrice(float $price): string
    {
        if ($price <= 0.0) {
            $messages = $this->messages->getModalMessages();
            return esc_html($messages['ticketStepIncluded'] ?? __('כלול', 'tapin'));
        }

        if (function_exists('wc_price')) {
            return wc_price($price);
        }

        return number_format_i18n($price, 2);
    }

    private function formatAvailability(int $capacity, int $available): string
    {
        $messages = $this->messages->getModalMessages();

        if ($capacity <= 0) {
            return esc_html($messages['ticketStepNoLimit'] ?? __('ללא הגבלה', 'tapin'));
        }

        $template = (string) ($messages['ticketStepAvailability'] ?? __('זמין: %s', 'tapin'));
        return sprintf($template, max(0, $available));
    }
}
