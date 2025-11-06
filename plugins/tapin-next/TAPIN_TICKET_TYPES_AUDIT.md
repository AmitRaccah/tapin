Tapin Events Next — Ticket Types Flow Audit and Fixes

Summary

- Fixed loss/misuse of dynamic ticket type ids by enforcing id-first resolution end-to-end and adding a robust pricing fallback during totals calculation. Added an optional debug tracer behind TAPIN_TICKET_DEBUG.

Flow Map (key points)

- Repository (persist + retrieve)
  - Ticket type get/save/sanitize: plugins/tapin-next/src/Domain/TicketTypesRepository.php:15, 50, 134
  - Stable id generation: plugins/tapin-next/src/Domain/TicketTypesRepository.php:183
  - Sale windows aligned by type id: plugins/tapin-next/src/Domain/SaleWindowsRepository.php:11, 164, 207

- Frontend (build list → modal → POST)
  - Modal data (ticketTypes list): plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php:1411
  - JS selection by id (cards): plugins/tapin-next/src/Features/ProductPage/assets/js/tapin-purchase/tapin.tickets.js
  - Plan (per-attendee typeId/label): plugins/tapin-next/src/Features/ProductPage/assets/js/tapin-purchase/tapin.plan.js
  - Finalize POST (tapin_attendees JSON with ticket_type + ticket_type_label): plugins/tapin-next/src/Features/ProductPage/assets/js/tapin-purchase/tapin.form.js
  - Hidden field renderer: plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php (search for renderHiddenField)

- Server validate/sanitize → cart split → pricing
  - Validate submission + set current type index: plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php:402
  - Sanitize attendee (id-first, label→id fallback, set price): plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php (see sanitizeAttendee)
  - Split add into separate cart items: plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php:515
  - Late, authoritative pricing (priority 999, id fallback via cache): plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php:53, 904
  - Order item meta (encrypted, per line): plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php (search for storeOrderItemMeta)

Root Cause

- Pricing/identification sometimes relied on label or earlier base price and could be overridden by other filters due to a lower priority hook. In cases where id was omitted from POST (or transformed), there was no reliable label→id resolution.

Changes

- Add debug tracer
  - New: plugins/tapin-next/src/Support/TicketTypeTracer.php
  - Enabled via: define('TAPIN_TICKET_DEBUG', true)
  - Logs ensureTicketTypeCache, sanitizeAttendee, attachCartItemData, applyAttendeePricing

- Enforce id-first resolution with label→id fallback
  - sanitizeAttendee now:
    - Resolves by ticket_type id when present
    - If missing, attempts label→id match (case/trim normalized)
    - If unresolved, returns error: "סוג הכרטיס שנבחר אינו זמין"
    - Sets authoritative ticket_price from ensureTicketTypeCache index

- Strengthen late pricing
  - Hook priority raised: woocommerce_before_calculate_totals at 999 (PurchaseDetailsModal.php:53)
  - applyAttendeePricing now resolves in order:
    1) tapin_ticket_price
    2) attendee[0].ticket_price
    3) Resolve by attendee[0].ticket_type id via ensureTicketTypeCache
  - Sets $item['data']->set_price($price) and persists tapin_ticket_price in cart contents

- Cart split and de-dup safeguards
  - Each line item includes a unique tapin_attendees_key using md5 + microtime
  - Each line carries a single attendee array under tapin_attendees

QA Scenarios (expected to pass)

1) VIP ×2 → two lines, both VIP price
2) Regular ×1 + VIP ×1 → two lines, correct distinct prices, labels for display
3) VIP under active discount window → discounted VIP price at checkout
4) Rename label (e.g., "Gold Lounge") with same id → price remains correct (id authoritative)
5) Non-simple/non-purchasable product → modal not rendered; logic not applied
6) TAPIN_TICKET_DEBUG false/undefined → no logs

