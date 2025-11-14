<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class ExportCsvBuilder
{
    /**
     * @param array<string,mixed> $event
     * @return array<int,array<int,string>>
     */
    public function build(array $event): array
    {
        $rows = [];
        $eventId    = (int) ($event['id'] ?? 0);
        $eventTitle = (string) ($event['title'] ?? '');
        $eventLink  = (string) ($event['permalink'] ?? '');

        foreach ((array) ($event['orders'] ?? []) as $order) {
            $lineItems = array_map(
                function (array $line): string {
                    $name     = $this->cleanExportValue($line['name'] ?? '');
                    $quantity = (int) ($line['quantity'] ?? 0);
                    $total    = $this->cleanExportValue($line['total'] ?? '');

                    $parts = [];
                    if ($name !== '') {
                        $parts[] = $name;
                    }
                    if ($quantity > 0) {
                        $parts[] = 'x ' . $quantity;
                    }
                    if ($total !== '') {
                        $parts[] = '(' . $total . ')';
                    }

                    $result = trim(implode(' ', $parts));
                    return $result !== '' ? $result : $total;
                },
                (array) ($order['lines'] ?? [])
            );

            $lineSummary = implode(' | ', array_filter($lineItems));

            $orderBase = [
                $eventId,
                $this->cleanExportValue($eventTitle),
                $this->cleanExportValue($eventLink),
                $this->cleanExportValue('#' . (string) ($order['number'] ?? '')),
                $this->cleanExportValue($order['status_label'] ?? ''),
                $this->cleanExportValue($order['date'] ?? ''),
                $this->cleanExportValue($order['total'] ?? ''),
                (string) (int) ($order['quantity'] ?? 0),
                $lineSummary,
                $this->cleanExportValue($order['customer']['name'] ?? ''),
                $this->cleanExportValue($order['customer']['email'] ?? ''),
                $this->cleanExportValue($order['customer']['phone'] ?? ''),
                $this->cleanExportValue($order['primary_id_number'] ?? ''),
            ];

            $attendees = [];
            if (!empty($order['primary_attendee'])) {
                $attendees[] = ['data' => (array) $order['primary_attendee'], 'primary' => true];
            }
            foreach ((array) ($order['attendees'] ?? []) as $attendee) {
                $attendees[] = ['data' => (array) $attendee, 'primary' => false];
            }

            if ($attendees === []) {
                $rows[] = array_merge($orderBase, ['', '', '', '', '', '', '', '', '', '']);
                continue;
            }

            foreach ($attendees as $attendeeEntry) {
                $attendee = (array) ($attendeeEntry['data'] ?? []);
                $rows[] = array_merge(
                    $orderBase,
                    [
                        $attendeeEntry['primary'] ? __('ראשי', 'tapin') : __('משני', 'tapin'),
                        $this->cleanExportValue($attendee['full_name'] ?? ''),
                        $this->cleanExportValue($attendee['email'] ?? ''),
                        $this->cleanExportValue($attendee['phone'] ?? ''),
                        $this->cleanExportValue($attendee['id_number'] ?? ''),
                        $this->cleanExportValue($attendee['birth_date'] ?? ''),
                        $this->cleanExportValue($attendee['gender'] ?? ''),
                        $this->cleanExportValue($attendee['instagram'] ?? ''),
                        $this->cleanExportValue($attendee['facebook'] ?? ''),
                        $this->cleanExportValue($attendee['whatsapp'] ?? ''),
                    ]
                );
            }
        }

        return $rows;
    }

    private function cleanExportValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $text = trim((string) $value);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text !== null ? trim($text) : '';
    }
}

