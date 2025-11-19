<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\Email\Email_CustomerAwaitingProducer;
use Tapin\Events\Features\Orders\Email\Email_ProducerAwaitingApproval;
use Tapin\Events\Features\Orders\Email\Email_ProducerOrderApproved;
use Tapin\Events\Features\Orders\Email\Email_ProducerTicketCheckin;
use Tapin\Events\Features\Orders\Email\Email_TicketToAttendee;

final class EmailsService implements Service
{
    public function register(): void
    {
        add_filter('woocommerce_email_classes', [$this, 'registerEmails']);

        if (function_exists('WC')) {
            try {
                WC()->mailer();
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * @param array<string,mixed> $emails
     * @return array<string,mixed>
     */
    public function registerEmails(array $emails): array
    {
        $emails['tapin_ticket_to_attendee']         = new Email_TicketToAttendee();
        $emails['tapin_customer_awaiting_producer'] = new Email_CustomerAwaitingProducer();
        $emails['tapin_producer_order_awaiting']    = new Email_ProducerAwaitingApproval();
        $emails['tapin_producer_order_approved']    = new Email_ProducerOrderApproved();
        $emails['tapin_producer_ticket_checkin']    = new Email_ProducerTicketCheckin();

        return $emails;
    }
}
