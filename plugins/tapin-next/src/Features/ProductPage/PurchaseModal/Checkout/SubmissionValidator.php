<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

use Tapin\Events\Features\ProductPage\PurchaseModal\Guards\ProductGuards;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Features\ProductPage\PurchaseModal\Users\TransparentUserManager;
use Tapin\Events\Features\ProductPage\PurchaseModal\Validation\AttendeeSanitizer;
use Tapin\Events\Support\Notices\StringifyNoticeTrait;
use Tapin\Events\Support\TicketTypeTracer;

final class SubmissionValidator
{
    use StringifyNoticeTrait;

    private AttendeeSanitizer $sanitizer;
    private TicketTypeCache $ticketTypeCache;
    private ProductGuards $guards;
    private TransparentUserManager $userManager;
    private PendingCheckoutManager $pendingCheckoutManager;
    private FlowState $flowState;

    public function __construct(
        AttendeeSanitizer $sanitizer,
        TicketTypeCache $ticketTypeCache,
        ProductGuards $guards,
        TransparentUserManager $userManager,
        PendingCheckoutManager $pendingCheckoutManager,
        FlowState $flowState
    ) {
        $this->sanitizer = $sanitizer;
        $this->ticketTypeCache = $ticketTypeCache;
        $this->guards = $guards;
        $this->userManager = $userManager;
        $this->pendingCheckoutManager = $pendingCheckoutManager;
        $this->flowState = $flowState;
    }

    /**
     * @param mixed $variationId
     * @param mixed $variations
     */
    public function validateSubmission(bool $passed, int $productId, int $quantity, $variationId = 0, $variations = null): bool
    {
        $this->flowState->setRedirectNextAdd(false);
        if ($this->flowState->isProcessingSplitAdd()) {
            return $passed;
        }

        $this->flowState->resetAttendees();

        if ((is_admin() && !wp_doing_ajax()) || !$this->guards->shouldHandleProduct($productId)) {
            return $passed;
        }

        if (!isset($_POST['tapin_attendees'])) {
            wc_add_notice('יש למלא את פרטי המשתתפים לפני הרכישה.', 'error');
            return false;
        }

        $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
        if (!is_array($decoded)) {
            wc_add_notice('נראה שיש בעיה בנתוני המשתתפים, אנא נסו שוב.', 'error');
            return false;
        }

        $originalQty = (int) $quantity;
        $quantity = max(1, (int) $quantity);
        if (count($decoded) !== $quantity) {
            wc_add_notice('יש להזין פרטים עבור כל משתתף שנבחר לרכישה.', 'error');
            return false;
        }

        $cache = $this->ticketTypeCache->ensureTicketTypeCache($productId);
        $ticketTypeIndex = $cache['index'];

        $sanitized = [];
        $errors = [];

        foreach ($decoded as $index => $attendee) {
            $result = $this->sanitizer->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0, $ticketTypeIndex);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                wc_add_notice($this->stringifyNotice($message), 'error');
            }

            /**
             * Fires when attendee validation fails before checkout submission continues.
             *
             * @param array<int,string> $errors
             */
            do_action('tapin/events/checkout/submission_errors', $errors, $productId, get_current_user_id());
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log('[submission-validator] blocked checkout for product ' . $productId);
            }

            return false;
        }

        if ($sanitized === []) {
            wc_add_notice(__('לא נמצאו משתתפים תקינים לעיבוד. אנא נסו שוב.', 'tapin'), 'error');
            return false;
        }

        $typeCounts = [];
        foreach ($sanitized as $entry) {
            $typeId = isset($entry['ticket_type']) ? (string) $entry['ticket_type'] : '';
            if ($typeId === '' || !isset($ticketTypeIndex[$typeId])) {
                wc_add_notice(__('סוג הכרטיס שנבחר אינו זמין.', 'tapin'), 'error');
                return false;
            }
            $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
        }

        foreach ($typeCounts as $typeId => $count) {
            $context = $ticketTypeIndex[$typeId];
            $capacity = isset($context['capacity']) ? (int) $context['capacity'] : 0;
            $available = isset($context['available']) ? (int) $context['available'] : 0;
            if ($capacity > 0 && $available >= 0 && $count > $available) {
                $name = (string) ($context['name'] ?? $typeId);
                wc_add_notice(sprintf(__('אין מספיק זמינות עבור %s.', 'tapin'), esc_html($name)), 'error');
                return false;
            }
        }

        $payer = $sanitized[0];
        $userId = get_current_user_id();
        $created = false;

        if (!$userId) {
            $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
            if ($email === '') {
                wc_add_notice('כתובת האימייל אינה תקינה.', 'error');
                return false;
            }

            $existing = get_user_by('email', $email);
            if ($existing instanceof \WP_User) {
                $this->pendingCheckoutManager->storePendingCheckout($sanitized, $productId, $quantity);
                $this->flowState->resetAttendees();
                $this->pendingCheckoutManager->redirectToLogin();
                return false;
            }

            $userId = $this->userManager->createTransparentUser($payer);
            if (!$userId) {
                return false;
            }

            $created = true;
        }

        $this->userManager->maybeUpdateUserProfile((int) $userId, $payer, $created);

        $this->flowState->primeAttendees($sanitized);
        $_POST['quantity'] = 1;
        $_REQUEST['quantity'] = 1;

        if (class_exists(TicketTypeTracer::class)) {
            TicketTypeTracer::validate((int) $productId, (int) $originalQty, (int) count($decoded), (int) count($sanitized));
        }

        return $passed;
    }
}
