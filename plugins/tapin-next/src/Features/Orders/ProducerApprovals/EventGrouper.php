<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class EventGrouper
{
    /**
     * @param array<int,array<string,mixed>> $orders
     * @param array<string,array<int,array<int,string>>> $customerWarnings
     * @return array<int,array<string,mixed>>
     */
    public function group(array $orders, array $customerWarnings = []): array
    {
        $events = [];

        foreach ($orders as $order) {
            if (empty($order['events'])) {
                continue;
            }

            foreach ((array) $order['events'] as $eventData) {
                $eventId   = (int) ($eventData['event_id'] ?? 0);
                $productId = (int) ($eventData['product_id'] ?? 0);
                $eventKey  = $eventId ?: $productId ?: (int) ($order['id'] ?? 0);
                $key       = (string) $eventKey;

                if (!isset($events[$key])) {
                    $events[$key] = [
                        'id'        => $eventKey,
                        'title'     => (string) ($eventData['title'] ?? ''),
                        'image'     => (string) ($eventData['image'] ?? ''),
                        'permalink' => (string) ($eventData['permalink'] ?? ''),
                        'event_date_ts'    => isset($eventData['event_date_ts']) ? (int) $eventData['event_date_ts'] : 0,
                        'event_date_label' => (string) ($eventData['event_date_label'] ?? ''),
                        'latest_order_ts'  => 0,
                        'counts'    => ['pending' => 0, 'partial' => 0, 'approved' => 0, 'cancelled' => 0],
                        'orders'    => [],
                        'search'    => '',
                    ];

                    if ($events[$key]['title'] === '') {
                        $events[$key]['title'] = html_entity_decode('&#1488;&#1497;&#1512;&#1493;&#1506; &#1489;&#1500;&#1514;&#1497; &#1505;&#1493;&#1498;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }

                $statusSlug = (string) ($order['status'] ?? '');
                $statusType = $this->classifyStatus($statusSlug);
                $isPartial  = $statusSlug === \Tapin\Events\Features\Orders\PartiallyApprovedStatus::STATUS_SLUG;
                if ($isPartial) {
                    $events[$key]['counts']['partial'] = ($events[$key]['counts']['partial'] ?? 0) + 1;
                } elseif (isset($events[$key]['counts'][$statusType])) {
                    $events[$key]['counts'][$statusType]++;
                }

                $orderSearch = SearchIndexBuilder::buildOrderSearchBlob($order, (array) $eventData);

                $emailKey      = strtolower(trim((string) ($order['customer']['email'] ?? '')));
                $orderWarnings = [];
                if ($emailKey !== '' && isset($customerWarnings[$emailKey]) && is_array($customerWarnings[$emailKey])) {
                    $eventSpecific = $customerWarnings[$emailKey];
                    $eventKeyInt   = (int) $eventKey;
                    if (isset($eventSpecific[$eventKeyInt]) && is_array($eventSpecific[$eventKeyInt])) {
                        $orderWarnings = (array) $eventSpecific[$eventKeyInt];
                    } elseif ($this->isFlatWarningList($eventSpecific)) {
                        $orderWarnings = $eventSpecific;
                    }
                }

                $events[$key]['orders'][] = [
                    'id'                => (int) ($order['id'] ?? 0),
                    'number'            => (string) ($order['number'] ?? ''),
                    'timestamp'         => (int) ($order['timestamp'] ?? 0),
                    'date'              => (string) ($order['date'] ?? ''),
                    'status'            => $statusSlug,
                    'status_label'      => (string) ($order['status_label'] ?? ''),
                    'status_type'       => $statusType,
                    'is_partial'        => $isPartial,
                    'total'             => (string) ($order['total'] ?? ''),
                    'quantity'          => (int) ($eventData['quantity'] ?? 0),
                    'lines'             => (array) ($eventData['lines'] ?? []),
                    'attendees'         => (array) ($eventData['attendees'] ?? []),
                    'customer'          => (array) ($order['customer'] ?? []),
                    'customer_profile'  => (array) ($order['customer_profile'] ?? []),
                    'profile'           => (array) ($order['profile'] ?? []),
                    'primary_attendee'  => (array) ($order['primary_attendee'] ?? []),
                    'primary_id_number' => (string) ($order['primary_id_number'] ?? ''),
                    'sale_type'         => (string) ($order['sale_type'] ?? ''),
                    'is_pending'        => $statusType === 'pending',
                    'approved_attendee_map' => (array) ($order['approved_attendee_map'] ?? []),
                    'warnings'          => $orderWarnings,
                    'search_blob'       => $orderSearch,
                ];

                $events[$key]['search'] .= ' ' . $orderSearch;
            }
        }

        foreach ($events as &$event) {
            $event['search'] = strtolower(trim((string) $event['title'] . ' ' . (string) $event['search']));
            usort($event['orders'], static function (array $a, array $b): int {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            });
            $event['latest_order_ts'] = !empty($event['orders'])
                ? (int) ($event['orders'][0]['timestamp'] ?? 0)
                : (int) ($event['latest_order_ts'] ?? 0);
        }
        unset($event);

        uasort($events, static function (array $a, array $b): int {
            $dateDiff = ($b['event_date_ts'] ?? 0) <=> ($a['event_date_ts'] ?? 0);
            if ($dateDiff !== 0) {
                return $dateDiff;
            }

            $orderDiff = ($b['latest_order_ts'] ?? 0) <=> ($a['latest_order_ts'] ?? 0);
            if ($orderDiff !== 0) {
                return $orderDiff;
            }

            $pendingDiff = ($b['counts']['pending'] ?? 0) <=> ($a['counts']['pending'] ?? 0);
            if ($pendingDiff !== 0) {
                return $pendingDiff;
            }

            $approvedDiff = ($b['counts']['approved'] ?? 0) <=> ($a['counts']['approved'] ?? 0);
            if ($approvedDiff !== 0) {
                return $approvedDiff;
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        return array_values($events);
    }

    public function classifyStatus(string $status): string
    {
        $normalized = strtolower($status);

        if (in_array($normalized, [\Tapin\Events\Features\Orders\AwaitingProducerStatus::STATUS_SLUG, \Tapin\Events\Features\Orders\PartiallyApprovedStatus::STATUS_SLUG, 'pending', 'on-hold'], true)) {
            return 'pending';
        }

        if (in_array($normalized, ['cancelled', 'refunded', 'failed'], true)) {
            return 'cancelled';
        }

        return 'approved';
    }

    /**
     * @param array<int,mixed> $warnings
     */
    private function isFlatWarningList(array $warnings): bool
    {
        foreach ($warnings as $warning) {
            if (!is_string($warning)) {
                return false;
            }
        }

        return true;
    }
}

