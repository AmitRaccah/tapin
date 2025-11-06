<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Features\ProductPage\PurchaseModal\Validation\AttendeeSanitizer;
use Tapin\Events\Support\TicketTypeTracer;

final class CartItemHandler
{
    private AttendeeSanitizer $sanitizer;
    private TicketTypeCache $ticketTypeCache;
    private FlowState $flowState;

    public function __construct(
        AttendeeSanitizer $sanitizer,
        TicketTypeCache $ticketTypeCache,
        FlowState $flowState
    ) {
        $this->sanitizer = $sanitizer;
        $this->ticketTypeCache = $ticketTypeCache;
        $this->flowState = $flowState;
    }

    /**
     * @param array<string,mixed> $cartItemData
     * @return array<string,mixed>
     */
    public function attachCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $isGenerated = !empty($cartItemData['tapin_split_generated']);
        $attendees = [];

        if ($isGenerated && isset($cartItemData['tapin_attendees']) && is_array($cartItemData['tapin_attendees'])) {
            $attendees = array_values(array_filter($cartItemData['tapin_attendees'], 'is_array'));
        } else {
            $next = $this->flowState->shiftNextAttendee();
            if (is_array($next)) {
                $attendees[] = $next;
            }

            if ($attendees === [] && isset($_POST['tapin_attendees']) && !$this->flowState->isProcessingSplitAdd()) {
                $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
                if (is_array($decoded)) {
                    $errors = [];
                    $rebuilt = [];
                    $ticketTypeIndex = $this->ticketTypeCache->getTicketTypeIndex($productId);

                    foreach ($decoded as $index => $attendee) {
                        $result = $this->sanitizer->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0, $ticketTypeIndex);
                        if ($result !== null) {
                            $rebuilt[] = $result;
                        }
                    }

                    if ($errors !== []) {
                        foreach ($errors as $message) {
                            wc_add_notice($this->stringifyNotice($message), 'error');
                        }

                        return $cartItemData;
                    }

                    $this->flowState->primeAttendees($rebuilt);
                    $next = $this->flowState->shiftNextAttendee();
                    if (is_array($next)) {
                        $attendees[] = $next;
                    }
                }
            }
        }

        if ($attendees === []) {
            unset($cartItemData['tapin_split_generated']);
            return $cartItemData;
        }

        $attendee = $attendees[0];
        $price = isset($attendee['ticket_price']) ? (float) $attendee['ticket_price'] : null;

        if (class_exists(TicketTypeTracer::class)) {
            $takenTypeId = isset($attendee['ticket_type']) ? (string) $attendee['ticket_type'] : '';
            TicketTypeTracer::attachQueueState(
                $this->flowState->isProcessingSplitAdd(),
                (bool) $isGenerated,
                $takenTypeId,
                (int) $this->flowState->getQueueCount()
            );
        }

        $cartItemData['tapin_attendees'] = [$attendee];
        $cartItemData['tapin_attendees_key'] = md5(wp_json_encode($attendee) . microtime(true));
        $cartItemData['unique_hash'] = md5((string) $cartItemData['tapin_attendees_key']);
        if ($price !== null) {
            $cartItemData['tapin_ticket_price'] = $price;
        }

        if (class_exists(TicketTypeTracer::class)) {
            $typeId = isset($attendee['ticket_type']) ? (string) $attendee['ticket_type'] : '';
            TicketTypeTracer::attach(!empty($cartItemData['tapin_split_generated']), $typeId, $price !== null ? (float) $price : null, (string) $cartItemData['tapin_attendees_key']);
        }

        if (!$isGenerated) {
            $this->flowState->setRedirectNextAdd(true);

            $remaining = $this->flowState->drainQueue();
            $this->flowState->clearPendingAttendees();

            if (!empty($remaining) && function_exists('WC') && WC()->cart instanceof \WC_Cart) {
                $this->flowState->setProcessingSplitAdd(true);
                foreach ($remaining as $extraAttendee) {
                    if (!is_array($extraAttendee)) {
                        continue;
                    }
                    $extraPrice = isset($extraAttendee['ticket_price']) ? (float) $extraAttendee['ticket_price'] : null;
                    $extraData = [
                        'tapin_attendees'        => [$extraAttendee],
                        'tapin_attendees_key'    => md5(wp_json_encode($extraAttendee) . microtime(true)),
                        'unique_hash'            => '',
                        'tapin_split_generated'  => true,
                    ];
                    $extraData['unique_hash'] = md5((string) $extraData['tapin_attendees_key']);
                    if ($extraPrice !== null) {
                        $extraData['tapin_ticket_price'] = $extraPrice;
                    }
                    WC()->cart->add_to_cart($productId, 1, $variationId, [], $extraData);
                }
                $this->flowState->setProcessingSplitAdd(false);
            }
        }

        unset($cartItemData['tapin_split_generated']);

        return $cartItemData;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function restoreCartItemFromSession(array $item, array $values): array
    {
        if (isset($values['tapin_attendees'])) {
            $item['tapin_attendees'] = $values['tapin_attendees'];
        }

        if (isset($values['tapin_ticket_price'])) {
            $item['tapin_ticket_price'] = (float) $values['tapin_ticket_price'];
        }

        return $item;
    }

    /**
     * @param array<int,array<string,string>> $itemData
     * @param array<string,mixed> $cartItem
     * @return array<int,array<string,string>>
     */
    public function displayCartItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['tapin_attendees']) || !is_array($cartItem['tapin_attendees'])) {
            return $itemData;
        }

        $lines = [];
        foreach ($cartItem['tapin_attendees'] as $index => $attendee) {
            $name = isset($attendee['full_name']) ? $attendee['full_name'] : '';
            $email = isset($attendee['email']) ? $attendee['email'] : '';
            $typeLabel = isset($attendee['ticket_type_label']) ? $attendee['ticket_type_label'] : '';
            $summary = sprintf(__('משתתף %d: %s (%s)', 'tapin'), $index + 1, esc_html($name), esc_html($email));
            if ($typeLabel !== '') {
                $summary .= ' - ' . esc_html($typeLabel);
            }
            $lines[] = $summary;
        }

        if ($lines !== []) {
            $itemData[] = [
                'name'  => __('משתתפים', 'tapin'),
                'value' => implode('<br>', array_map('wp_kses_post', $lines)),
            ];
        }

        return $itemData;
    }

    private function stringifyNotice($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }
}
