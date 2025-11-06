<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class SearchIndexBuilder
{
    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $eventData
     */
    public static function buildOrderSearchBlob(array $order, array $eventData): string
    {
        $segments = [
            '#' . (string) ($order['number'] ?? ''),
            (string) ($order['customer']['name'] ?? ''),
            (string) ($order['customer']['email'] ?? ''),
            (string) ($order['customer']['phone'] ?? ''),
            (string) ($order['primary_id_number'] ?? ''),
            (string) ($order['date'] ?? ''),
            (string) ($order['total'] ?? ''),
            (string) ($eventData['title'] ?? ''),
            (string) ($eventData['event_date_label'] ?? ''),
        ];

        $profileUsername = (string) ($order['customer_profile']['username'] ?? '');
        if ($profileUsername !== '') {
            $segments[] = $profileUsername;
            $segments[] = '@' . ltrim($profileUsername, '@');
        }

        foreach ((array) ($eventData['lines'] ?? []) as $line) {
            $segments[] = (string) ($line['name'] ?? '');
        }

        foreach ((array) ($eventData['attendees'] ?? []) as $attendee) {
            foreach (['full_name', 'email', 'phone', 'id_number', 'gender'] as $field) {
                if (!empty($attendee[$field])) {
                    $segments[] = (string) $attendee[$field];
                }
            }
        }

        return strtolower(wp_strip_all_tags(implode(' ', array_filter($segments))));
    }
}

