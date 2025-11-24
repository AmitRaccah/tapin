<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

/**
 * Central list of order-related meta keys and status slugs used across
 * gating, approvals, and ticketing flows.
 */
final class OrderMeta
{
    public const PRODUCER_IDS                = '_tapin_producer_ids';
    public const PARTIAL_APPROVED_MAP        = '_tapin_partial_approved_map';
    public const PARTIAL_APPROVED_TOTAL      = '_tapin_partial_approved_total';
    public const PARTIAL_CAPTURED_TOTAL      = '_tapin_partial_captured_total';
    public const PRODUCER_APPROVED_ATTENDEES = '_tapin_producer_approved_attendees';
    public const ATTENDEES_JSON              = '_tapin_attendees_json';
    public const TICKET_SALES_RECORDED       = '_tapin_ticket_sales_recorded';
    public const TICKET_EMAILS_SENT          = '_tapin_ticket_emails_sent';

    public const STATUS_AWAITING_PRODUCER    = 'awaiting-producer';
    public const STATUS_PARTIALLY_APPROVED   = 'partially-appr';

    private function __construct()
    {
    }
}
