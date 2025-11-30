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
        return $this->buildViewUrl($ticket);
    }

    /**
     * @param array<string,mixed> $ticket
     */
    public function buildCheckinUrl(array $ticket): string
    {
        return $this->buildTicketUrl($ticket, 'checkin');
    }

    /**
     * @param array<string,mixed> $ticket
     */
    public function buildViewUrl(array $ticket): string
    {
        return $this->buildTicketUrl($ticket, 'view');
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function buildTicketUrl(array $ticket, string $mode): string
    {
        $token = isset($ticket['token']) ? (string) $ticket['token'] : '';
        $token = trim($token);

        if ($token === '') {
            return '';
        }

        $base = $mode === 'checkin'
            ? $this->determineCheckinBaseUrl()
            : $this->determineViewBaseUrl();
        if ($base === '') {
            return '';
        }

        return add_query_arg(['tapin_ticket' => $token], $base);
    }

    private function determineCheckinBaseUrl(): string
    {
        $pageId = (int) get_option('_tapin_ticket_checkin_page_id');
        $permalink = $this->normalizeBaseFromId($pageId);
        if ($permalink !== '') {
            return $permalink;
        }

        $customUrl = (string) get_option('_tapin_ticket_checkin_page_url');
        $normalized = $this->normalizeBaseUrl($customUrl);
        if ($normalized !== '') {
            return $normalized;
        }

        return $this->normalizeBaseUrl('/tapin-ticket/');
    }

    private function determineViewBaseUrl(): string
    {
        $pageId = (int) get_option('_tapin_ticket_view_page_id');
        $permalink = $this->normalizeBaseFromId($pageId);
        if ($permalink !== '') {
            return $permalink;
        }

        $customUrl = (string) get_option('_tapin_ticket_view_page_url');
        $normalized = $this->normalizeBaseUrl($customUrl);
        if ($normalized !== '') {
            return $normalized;
        }

        return $this->normalizeBaseUrl('/tapin-ticket-view/');
    }

    private function normalizeBaseFromId(int $pageId): string
    {
        if ($pageId <= 0) {
            return '';
        }

        $permalink = get_permalink($pageId);
        if (!$permalink) {
            return '';
        }

        return $this->normalizeBaseUrl((string) $permalink);
    }

    private function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $path = (string) wp_parse_url($value, PHP_URL_PATH);
        if ($path === '') {
            $path = '/' . ltrim($value, '/');
        }

        $base = $this->canonicalSiteUrl();
        if ($base === '') {
            return '';
        }

        $path = '/' . ltrim($path, '/');

        return trailingslashit(trailingslashit($base) . ltrim($path, '/'));
    }

    private function canonicalSiteUrl(): string
    {
        if (function_exists('tapin_next_canonical_site_url')) {
            $base = tapin_next_canonical_site_url();
            if ($base !== '') {
                return $base;
            }
        }

        $home = home_url('/');
        return is_string($home) ? $home : '';
    }
}
