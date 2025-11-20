<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\TicketFee;
use Tapin\Events\Support\Time;

final class EventRowFactory
{
    /**
     * @param array<int,string> $thumbCache
     * @param array<int,int> $eventTsCache
     * @param array<int,array<string,mixed>> $commissionCache
     */
    public function __construct(
        private array &$thumbCache,
        private array &$eventTsCache,
        private array &$commissionCache,
        private ?WindowsBuckets $windows = null
    ) {
        $this->windows = $this->windows ?? new WindowsBuckets();
    }

    public function create(int $productId, int $authorId): array
    {
        $ticketTypes = TicketTypesRepository::get($productId);
        $ticketIndex = $this->indexTicketTypes($ticketTypes);
        $regular = $this->resolveRegularTicket($ticketTypes);
        $eventTs   = $this->resolveEventTimestamp($productId);
        $createdTs = (int) get_post_time('U', true, $productId);
        $commissionMeta = $this->resolveCommissionMeta($productId);
        $feePercent = TicketFee::getPercent($productId);
        $format = get_option('date_format') . ' H:i';
        $eventLabel = $eventTs > 0 ? Time::fmtLocal($eventTs, $format) : '';

        return [
            'name'               => get_the_title($productId),
            'qty'                => 0,
            'sum'                => 0.0,
            'view'               => get_permalink($productId),
            'thumb'              => $this->resolveThumb($productId),
            'author_id'          => $authorId,
            'ref_qty'            => 0,
            'ref_sum'            => 0.0,
            'ref_commission'     => 0.0,
            'event_ts'           => $eventTs,
            'created_ts'         => $createdTs,
            'event_date_label'   => $eventLabel,
            'ticket_index'       => $ticketIndex,
            'regular_type_id'    => $regular['id'],
            'regular_type_label' => $regular['label'],
            'commission_meta'    => $commissionMeta,
            'fee_percent'        => $feePercent,
            'fee_total'          => 0.0,
            'stats'              => [
                'regular_total'     => 0,
                'regular_affiliate' => 0,
                'regular_direct'    => 0,
                'special_types'     => [],
                'windows'           => $this->windows->build($productId, $ticketTypes),
            ],
        ];
    }

    public function ensureCommissionMeta(array &$row, int $productId): void
    {
        if (empty($row['commission_meta'])) {
            $row['commission_meta'] = $this->resolveCommissionMeta($productId);
        }
    }

    private function resolveThumb(int $productId): string
    {
        if (!array_key_exists($productId, $this->thumbCache)) {
            $url = get_the_post_thumbnail_url($productId, 'woocommerce_thumbnail');
            if (!$url && function_exists('wc_placeholder_img_src')) {
                $url = wc_placeholder_img_src();
            }
            if (!$url) {
                $url = includes_url('images/media/default.png');
            }
            $this->thumbCache[$productId] = (string) $url;
        }

        return $this->thumbCache[$productId];
    }

    private function resolveEventTimestamp(int $productId): int
    {
        if (!array_key_exists($productId, $this->eventTsCache)) {
            $this->eventTsCache[$productId] = Time::productEventTs($productId);
        }

        return (int) $this->eventTsCache[$productId];
    }

    /**
     * @return array{type:string,amount:float}
     */
    private function resolveCommissionMeta(int $productId): array
    {
        if (!array_key_exists($productId, $this->commissionCache)) {
            $type = get_post_meta($productId, MetaKeys::PRODUCER_AFF_TYPE, true);
            $amount = get_post_meta($productId, MetaKeys::PRODUCER_AFF_AMOUNT, true);

            $this->commissionCache[$productId] = [
                'type'   => in_array($type, ['percent', 'flat'], true) ? (string) $type : '',
                'amount' => is_numeric($amount) ? (float) $amount : 0.0,
            ];
        }

        return $this->commissionCache[$productId];
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    private function indexTicketTypes(array $ticketTypes): array
    {
        $index = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type)) {
                continue;
            }
            $id = isset($type['id']) ? (string) $type['id'] : '';
            if ($id === '') {
                continue;
            }
            $index[$id] = [
                'name' => isset($type['name']) ? (string) $type['name'] : $id,
            ];
        }

        return $index;
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array{id:string,label:string}
     */
    private function resolveRegularTicket(array $ticketTypes): array
    {
        foreach ($ticketTypes as $type) {
            if (isset($type['id']) && (string) $type['id'] === 'general') {
                return [
                    'id'    => 'general',
                    'label' => isset($type['name']) ? (string) $type['name'] : 'general',
                ];
            }
        }

        if ($ticketTypes !== []) {
            $first = $ticketTypes[0];
            return [
                'id'    => isset($first['id']) ? (string) $first['id'] : '',
                'label' => isset($first['name']) ? (string) $first['name'] : '',
            ];
        }

        return ['id' => '', 'label' => ''];
    }
}
