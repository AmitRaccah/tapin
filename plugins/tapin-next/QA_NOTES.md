Tapin Events Next — QA Notes for Ticket Types Flow

Scenarios

- A) Two VIP tickets
  - Select VIP ×2 in modal
  - Expected: Exactly 2 lines in cart, both priced at VIP price

- B) One Regular + One VIP
  - Select Regular ×1 and VIP ×1
  - Expected: 2 lines, correct distinct prices; labels only for display

- C) Rename label without changing id
  - Change VIP label to "Gold Lounge" keeping the same id
  - Expected: pricing remains correct (id authoritative)

- D) Active discount window for VIP
  - Configure Sale Window with VIP-specific price
  - Expected: Checkout shows discounted VIP price

- E) Refresh/Back
  - After adding via modal, refresh product page or navigate back/forward
  - Expected: No extra "ghost" line is added

- F) Login redirect (pending checkout resume)
  - Submit as email that belongs to an existing user to trigger login redirect
  - After login, resume pending checkout
  - Expected: Items added once; count matches attendees

Debugging

- Enable logs by defining: TAPIN_TICKET_DEBUG = true
- PHP logs:
  - ensureTicketTypeCache snapshots
  - validateSubmission: decoded, sanitized, original qty
  - attachCartItemData: processing state, chosen attendee type, remaining queue
  - applyAttendeePricing: product_id, type_id, price source (item/attendee/cache)
  - maybeResumePendingCheckout: attendees count, add_to_cart result
- JS logs (console.debug):
  - Plan.buildFromSelection totals
  - Form.finalize serialized attendees (type/label/price)

