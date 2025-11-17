<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\TicketEmails;

final class TicketUrlBuilder
{
    /**
     * @param array<string,mixed> $ticket
     */
    public function build(array $ticket): string
    {
        $token = isset($ticket['token']) ? (string) $ticket['token'] : '';
        $token = trim($token);

        if ($token === '') {
            return '';
        }

        $base = $this->determineBaseUrl();
        if ($base === '') {
            return '';
        }

        return add_query_arg(['tapin_ticket' => $token], $base);
    }

    private function determineBaseUrl(): string
    {
        $pageId = (int) get_option('_tapin_ticket_checkin_page_id');
        if ($pageId > 0) {
            $permalink = get_permalink($pageId);
            if ($permalink) {
                return $permalink;
            }
        }

        $customUrl = (string) get_option('_tapin_ticket_checkin_page_url');
        if ($customUrl !== '') {
            $normalized = esc_url_raw($customUrl);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return home_url('/tapin-ticket/');
    }
}

