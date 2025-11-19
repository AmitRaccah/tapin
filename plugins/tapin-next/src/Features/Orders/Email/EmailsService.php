<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Core\Service;

final class EmailsService implements Service
{
    public function register(): void
    {
        add_filter('woocommerce_email_classes', [$this, 'registerEmails']);
    }

    /**
     * @param array<string,mixed> $emails
     * @return array<string,mixed>
     */
    public function registerEmails(array $emails): array
    {
        $emails['tapin_ticket_to_attendee'] = new Email_TicketToAttendee();

        return $emails;
    }
}

