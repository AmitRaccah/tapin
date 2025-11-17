<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\ProducerApprovals\EventGrouper;
use Tapin\Events\Features\Orders\ProducerApprovals\OrderQuery;
use Tapin\Events\Features\Orders\ProducerApprovals\OrderSummaryBuilder;
use Tapin\Events\Features\Orders\TicketEmails\TicketAttendeesResolver;
use Tapin\Events\Features\Orders\TicketEmails\TicketTokensRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\Capabilities;
use Tapin\Events\Support\Time;
use WC_Order;

final class ProducerTicketDashboard implements Service
{
    private OrderQuery $orderQuery;
    private OrderSummaryBuilder $summaryBuilder;
    private EventGrouper $eventGrouper;
    private TicketAttendeesResolver $attendeesResolver;
    private TicketTokensRepository $tokensRepository;

    public function __construct(
        ?OrderQuery $orderQuery = null,
        ?OrderSummaryBuilder $summaryBuilder = null,
        ?EventGrouper $eventGrouper = null,
        ?TicketAttendeesResolver $attendeesResolver = null,
        ?TicketTokensRepository $tokensRepository = null
    ) {
        $this->orderQuery        = $orderQuery ?: new OrderQuery();
        $this->summaryBuilder    = $summaryBuilder ?: new OrderSummaryBuilder();
        $this->eventGrouper      = $eventGrouper ?: new EventGrouper();
        $this->attendeesResolver = $attendeesResolver ?: new TicketAttendeesResolver();
        $this->tokensRepository  = $tokensRepository ?: new TicketTokensRepository();
    }

    public function register(): void
    {
        add_shortcode('tapin_ticket_dashboard', [$this, 'render']);
    }

    public function render($atts = []): string
    {
        if (!function_exists('wc_get_order')) {
            return $this->message(esc_html__('WooCommerce אינו זמין באתר זה.', 'tapin'));
        }

        if (!is_user_logged_in()) {
            status_header(403);
            return $this->message(esc_html__('אנא התחבר כדי לצפות בלוח הכרטיסים.', 'tapin'));
        }

        $isAdmin = current_user_can('manage_options');
        $hasCap  = current_user_can(Capabilities::attendeeCapability());

        if (!$isAdmin && !$hasCap) {
            status_header(403);
            return $this->message(esc_html__('אין לך הרשאה לצפות בלוח הכרטיסים.', 'tapin'));
        }

        $producerId = $this->resolveProducerId((array) $atts, $isAdmin);

        if ($producerId <= 0) {
            return $this->message(esc_html__('לא נמצא מפיק עבור תצוגה זו.', 'tapin'));
        }

        if (!$isAdmin && get_current_user_id() !== $producerId) {
            status_header(403);
            return $this->message(esc_html__('אין לך הרשאה לצפות בלוח הכרטיסים.', 'tapin'));
        }

        $orderIds = $this->orderQuery->resolveProducerOrderIds($producerId);
        $displayIds = array_map('intval', (array) ($orderIds['display'] ?? []));

        if ($displayIds === []) {
            return $this->message(esc_html__('אין הזמנות להצגה עבור מפיק זה.', 'tapin'));
        }

        $orders = [];
        foreach ($displayIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order) {
                $orders[] = $order;
            }
        }

        if ($orders === []) {
            return $this->message(esc_html__('אין הזמנות להצגה עבור מפיק זה.', 'tapin'));
        }

        $summary      = $this->summaryBuilder->summarizeOrders($displayIds, $producerId);
        $grouped      = $this->eventGrouper->group((array) ($summary['orders'] ?? []));
        $eventMetaMap = [];
        foreach ($grouped as $event) {
            $eventId = isset($event['id']) ? (int) $event['id'] : 0;
            if ($eventId > 0) {
                $eventMetaMap[$eventId] = $event;
            }
        }

        $events = $this->buildEvents($orders, $producerId, $eventMetaMap);
        if ($events === []) {
            return $this->message(esc_html__('אין כרטיסים להצגה כרגע.', 'tapin'));
        }

        return $this->renderDashboard($events, $producerId);
    }

    private function resolveProducerId(array $atts, bool $isAdmin): int
    {
        if (!$isAdmin) {
            return get_current_user_id();
        }

        $atts = shortcode_atts(['producer' => ''], $atts, 'tapin_ticket_dashboard');
        $producer = (string) $atts['producer'];
        if ($producer !== '' && ctype_digit($producer)) {
            return (int) $producer;
        }

        if (isset($_GET['tapin_dashboard_producer'])) {
            $param = sanitize_text_field(wp_unslash((string) $_GET['tapin_dashboard_producer']));
            if ($param !== '' && ctype_digit($param)) {
                return (int) $param;
            }
        }

        return get_current_user_id();
    }

    /**
     * @param array<int,WC_Order> $orders
     * @param array<int,array<string,mixed>> $eventMetaMap
     * @return array<int,array<string,mixed>>
     */
    private function buildEvents(array $orders, int $producerId, array $eventMetaMap): array
    {
        $events = [];

        foreach ($orders as $order) {
            $tokens    = $this->tokensRepository->getTokensForOrder($order);
            $statusMap = $this->buildTicketStatusMap($tokens);
            $tickets   = $this->attendeesResolver->resolve($order, $producerId);

            foreach ($tickets as $ticket) {
                $eventId   = isset($ticket['event_id']) ? (int) $ticket['event_id'] : 0;
                $productId = isset($ticket['product_id']) ? (int) $ticket['product_id'] : 0;
                $key       = $eventId > 0 ? 'event-' . $eventId : ($productId ? 'product-' . $productId : 'order-' . $order->get_id());

                if (!isset($events[$key])) {
                    $meta               = $eventId > 0 && isset($eventMetaMap[$eventId]) ? $eventMetaMap[$eventId] : null;
                    $events[$key] = [
                        'id'            => $eventId ?: $productId,
                        'title'         => $this->resolveEventTitle($meta, $ticket),
                        'event_date_ts' => $this->resolveEventTimestamp($meta, $eventId ?: $productId),
                        'latest_ts'     => 0,
                        'attendees'     => [],
                    ];
                }

                $events[$key]['attendees'][] = [
                    'full_name'    => sanitize_text_field((string) ($ticket['full_name'] ?? '')),
                    'phone'        => sanitize_text_field((string) ($ticket['phone'] ?? '')),
                    'email'        => sanitize_email((string) ($ticket['email'] ?? '')),
                    'status'       => $this->resolveTicketStatus($ticket, $statusMap),
                    'ticket'       => $ticket,
                    'details'      => isset($ticket['attendee']) && is_array($ticket['attendee']) ? $ticket['attendee'] : [],
                    'order_number' => $order->get_order_number(),
                ];

                $orderTs = $order->get_date_created() ? (int) $order->get_date_created()->getTimestamp() : 0;
                if ($orderTs > $events[$key]['latest_ts']) {
                    $events[$key]['latest_ts'] = $orderTs;
                }
            }
        }

        if ($events === []) {
            return [];
        }

        foreach ($events as &$event) {
            usort($event['attendees'], static function (array $a, array $b): int {
                return strcmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
            });
        }
        unset($event);

        uasort($events, static function (array $a, array $b): int {
            $dateDiff = ($b['event_date_ts'] ?? 0) <=> ($a['event_date_ts'] ?? 0);
            if ($dateDiff !== 0) {
                return $dateDiff;
            }

            $latestDiff = ($b['latest_ts'] ?? 0) <=> ($a['latest_ts'] ?? 0);
            if ($latestDiff !== 0) {
                return $latestDiff;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return array_values($events);
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $ticket
     */
    private function resolveEventTitle(?array $meta, array $ticket): string
    {
        if (is_array($meta) && !empty($meta['title'])) {
            return (string) $meta['title'];
        }

        if (!empty($ticket['product_name'])) {
            return (string) $ticket['product_name'];
        }

        if (!empty($ticket['ticket_label'])) {
            return (string) $ticket['ticket_label'];
        }

        return esc_html__('אירוע ללא שם', 'tapin');
    }

    private function resolveEventTimestamp(?array $meta, int $productId): int
    {
        if (is_array($meta) && isset($meta['event_date_ts']) && (int) $meta['event_date_ts'] > 0) {
            return (int) $meta['event_date_ts'];
        }

        return $productId > 0 ? Time::productEventTs($productId) : 0;
    }

    /**
     * @param array<string,array<string,mixed>> $tokens
     * @return array<string,string>
     */
    private function buildTicketStatusMap(array $tokens): array
    {
        $map = [];

        foreach ($tokens as $tokenData) {
            $itemId        = isset($tokenData['item_id']) ? (int) $tokenData['item_id'] : 0;
            $attendeeIndex = isset($tokenData['attendee_index']) ? (int) $tokenData['attendee_index'] : -1;
            if ($itemId <= 0 || $attendeeIndex < 0) {
                continue;
            }

            $key = $itemId . ':' . $attendeeIndex;
            $map[$key] = isset($tokenData['status']) ? (string) $tokenData['status'] : 'pending';
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $ticket
     * @param array<string,string> $statusMap
     */
    private function resolveTicketStatus(array $ticket, array $statusMap): string
    {
        $itemId        = isset($ticket['line_item_id']) ? (int) $ticket['line_item_id'] : (int) ($ticket['item_id'] ?? 0);
        $attendeeIndex = isset($ticket['attendee_index']) ? (int) $ticket['attendee_index'] : -1;
        $key           = $itemId . ':' . $attendeeIndex;
        $status        = isset($statusMap[$key]) ? (string) $statusMap[$key] : (string) ($ticket['status'] ?? 'pending');

        return $status === 'approved' ? 'approved' : 'pending';
    }

    /**
     * @param array<int,array<string,mixed>> $events
     */
    private function renderDashboard(array $events, int $producerId): string
    {
        $producer = get_userdata($producerId);
        $producerLabel = $producer instanceof \WP_User
            ? $producer->display_name
            : ('#' . $producerId);

        ob_start();
        ?>
        <style>
            .tapin-ticket-dashboard{direction:rtl;text-align:right;font-family:inherit;}
            .tapin-ticket-dashboard summary{cursor:pointer;list-style:none;}
            .tapin-ticket-dashboard summary::-webkit-details-marker{display:none;}
            .tapin-ticket-event{border:1px solid #e5e5e5;border-radius:16px;margin-bottom:18px;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,0.05);}
            .tapin-ticket-event>summary{padding:18px;font-size:18px;font-weight:600;display:flex;justify-content:space-between;align-items:center;gap:12px;}
            .tapin-ticket-event__count{font-size:14px;color:#666;}
            .tapin-ticket-event__body{padding:0 18px 18px;}
            .tapin-ticket-attendee{border:1px solid #f0f0f0;border-radius:12px;margin:12px 0;background:#fafafa;}
            .tapin-ticket-attendee>summary{padding:14px;font-size:16px;font-weight:500;display:flex;justify-content:space-between;align-items:center;gap:8px;}
            .tapin-ticket-status{padding:4px 10px;border-radius:999px;font-size:13px;font-weight:600;}
            .tapin-ticket-status--approved{background:#e6f7ed;color:#18794e;}
            .tapin-ticket-status--pending{background:#fff4e5;color:#a15c00;}
            .tapin-ticket-attendee__body{padding:0 16px 16px;font-size:15px;}
            .tapin-ticket-attendee__details{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;}
            .tapin-ticket-attendee__details li{background:#fff;border:1px solid #f0f0f0;border-radius:10px;padding:10px;}
            .tapin-ticket-attendee__details strong{display:block;margin-bottom:4px;font-size:13px;color:#666;}
            .tapin-ticket-message{padding:16px;border-radius:10px;background:#fff4f4;border:1px solid #f3c2c2;margin-bottom:20px;}
        </style>
        <div class="tapin-ticket-dashboard">
            <div style="margin-bottom:20px;">
                <h2 style="margin:0 0 6px;font-size:26px;font-weight:700;"><?php echo esc_html__('לוח הכרטיסים', 'tapin'); ?></h2>
                <div style="color:#666;"><?php echo esc_html(sprintf(
                    /* translators: %s: producer display name */
                    __('מציג כרטיסים עבור %s', 'tapin'),
                    $producerLabel
                )); ?></div>
            </div>
            <?php
            $first = true;
            foreach ($events as $event):
                $attendees = (array) ($event['attendees'] ?? []);
                $count     = count($attendees);
                ?>
                <details class="tapin-ticket-event" <?php echo $first ? 'open' : ''; ?>>
                    <summary>
                        <span>
                            <?php echo esc_html((string) ($event['title'] ?? '')); ?>
                            <?php if (!empty($event['event_date_ts'])): ?>
                                <small style="display:block;font-size:13px;color:#777;"><?php echo Time::fmtLocal((int) $event['event_date_ts']); ?></small>
                            <?php endif; ?>
                        </span>
                        <span class="tapin-ticket-event__count"><?php echo esc_html(sprintf(
                            /* translators: %d: attendee count */
                            _n('%d כרטיס', '%d כרטיסים', $count, 'tapin'),
                            $count
                        )); ?></span>
                    </summary>
                    <div class="tapin-ticket-event__body">
                        <?php foreach ($attendees as $attendee): ?>
                            <?php $status = (string) ($attendee['status'] ?? 'pending'); ?>
                            <details class="tapin-ticket-attendee">
                                <summary>
                                    <span>
                                        <?php echo esc_html($attendee['full_name'] ?? ''); ?>
                                        <?php if (!empty($attendee['phone'])): ?>
                                            <small style="color:#777;font-size:13px;margin-right:8px;"><?php echo esc_html($attendee['phone']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="tapin-ticket-status tapin-ticket-status--<?php echo $status === 'approved' ? 'approved' : 'pending'; ?>">
                                        <?php echo esc_html($status === 'approved' ? __('מאושר', 'tapin') : __('ממתין', 'tapin')); ?>
                                    </span>
                                </summary>
                                <div class="tapin-ticket-attendee__body">
                                    <ul class="tapin-ticket-attendee__details">
                                        <?php foreach ($this->attendeeDetailRows($attendee) as $label => $value): ?>
                                            <?php if ($value === '') { continue; } ?>
                                            <li>
                                                <strong><?php echo esc_html($label); ?></strong>
                                                <span><?php echo esc_html($value); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php
                $first = false;
            endforeach;
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function attendeeDetailRows(array $attendee): array
    {
        $ticket  = isset($attendee['ticket']) && is_array($attendee['ticket']) ? $attendee['ticket'] : [];
        $details = isset($attendee['details']) && is_array($attendee['details']) ? $attendee['details'] : [];
        $orderNumber = isset($attendee['order_number']) ? (string) $attendee['order_number'] : '';

        $rows = [
            esc_html__('מספר הזמנה', 'tapin') => $orderNumber !== '' ? '#' . $orderNumber : '',
            esc_html__('אימייל', 'tapin')     => $this->formatField('email', $attendee['email'] ?? ($details['email'] ?? '')),
            esc_html__('טלפון', 'tapin')      => $this->formatField('phone', $attendee['phone'] ?? ($details['phone'] ?? '')),
            esc_html__('תאריך לידה', 'tapin') => $this->formatField('birth_date', $details['birth_date'] ?? ''),
            esc_html__('תעודת זהות', 'tapin') => $this->formatField('id_number', $details['id_number'] ?? ''),
            esc_html__('מגדר', 'tapin')       => $this->formatField('gender', $details['gender'] ?? ''),
            esc_html__('אינסטגרם', 'tapin')   => $this->formatField('instagram', $details['instagram'] ?? ''),
            esc_html__('טיקטוק', 'tapin')     => $this->formatField('tiktok', $details['tiktok'] ?? ''),
            esc_html__('פייסבוק', 'tapin')    => $this->formatField('facebook', $details['facebook'] ?? ''),
            esc_html__('סוג כרטיס', 'tapin')  => sanitize_text_field((string) ($ticket['ticket_type'] ?? '')),
            esc_html__('כיתוב כרטיס', 'tapin') => sanitize_text_field((string) ($ticket['ticket_label'] ?? '')),
        ];

        return $rows;
    }

    private function formatField(string $key, $value): string
    {
        $value = is_string($value) ? $value : '';
        if ($value === '') {
            return '';
        }

        if ($key === 'email') {
            return sanitize_email($value);
        }

        $display = AttendeeFields::displayValue($key, $value);
        if ($display !== '') {
            return $display;
        }

        return sanitize_text_field($value);
    }

    private function message(string $text): string
    {
        return '<div class="tapin-ticket-message" style="direction:rtl;text-align:right;padding:16px;border-radius:10px;border:1px solid #e5e5e5;background:#fff;">' . esc_html($text) . '</div>';
    }
}
