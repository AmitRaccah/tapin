# Tapin Next Notes

### Shared notices
- `Tapin\Events\Support\Notices\StringifyNoticeTrait` keeps WooCommerce notices consistent across `SubmissionValidator` and `CartItemHandler`.  
- Use `$this->stringifyNotice($value)` instead of ad-hoc casting whenever passing data into `wc_add_notice`.

### Sandbox toggle
- The sandbox switch now lives behind `admin-post.php?action=tapin_toggle_sandbox`. Generate links with `tapin_next_sandbox_toggle_url( bool $enable, ?string $redirect = null )`.  
- Nonce: `tapin_toggle_sandbox`. Capability: `manage_options`. Requests without both are rejected with HTTP 403.  
- Cookie is set via `tapin_next_toggle_sandbox_cookie()` using secure/httponly/Lax flags and emits `do_action( 'tapin/sandbox/toggled', $enabled, $user_id )`.  
- Legacy `?tapin_sandbox=1&_wpnonce=...` still works temporarily but is flagged as deprecated and will be removed once callers migrate.

### Admin center actions
- `AdminCenterActions::handle()` now enforces `Security::manager()` before touching bulk actions and dies on nonce failures.  
- Every action funnels through `tapin/admin_center/action_processed` and also logs when `TAPIN_NEXT_DEBUG` or `WP_DEBUG` are enabled.

### Instrumentation hooks
- `tapin/events/cart_item/errors` and `tapin/events/cart_item/attached` fire during add-to-cart flows.  
- `tapin/events/checkout/submission_errors` and `tapin/events/checkout/pending_resumed` cover checkout validation and resume attempts.  
- Hooks (and the shared `tapin_next_debug_log()`) exist to anchor future integration or smoke tests without changing business logic.
