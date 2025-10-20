<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\Orders;
use WC_Order;
use WC_Order_Item_Product;

final class ProducerApprovalsShortcode implements Service
{
    private const LARGE_ORDER_THRESHOLD    = 10;
    private const CUSTOMER_TOTAL_THRESHOLD = 20;

    public function register(): void
    {
        add_shortcode('producer_order_approvals', [$this, 'render']);
    }

    public function render(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="woocommerce-info" style="direction:rtl;text-align:right">יש להתחבר כדי לצפות בהזמנות.</div>';
        }

        $currentUser = wp_get_current_user();
        if (!array_intersect((array) $currentUser->roles, ['producer', 'owner'])) {
            return '<div class="woocommerce-error" style="direction:rtl;text-align:right">אין לך הרשאה לצפות בעמוד זה.</div>';
        }

        $producerId = (int) get_current_user_id();

        $awaitingIds = wc_get_orders([
            'status' => ['wc-awaiting-producer'],
            'limit'  => 200,
            'return' => 'ids',
        ]);

        foreach ($awaitingIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && !$order->get_meta('_tapin_producer_ids')) {
                $order->update_meta_data('_tapin_producer_ids', Orders::collectProducerIds($order));
                $order->save();
            }
        }

        $pendingIds = wc_get_orders([
            'status' => ['wc-awaiting-producer'],
            'limit'  => 200,
            'return' => 'ids',
        ]);

        $relevantIds = [];
        foreach ($pendingIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }

            $metaIds = (array) $order->get_meta('_tapin_producer_ids');
            if ($metaIds && in_array($producerId, $metaIds, true)) {
                $relevantIds[] = $orderId;
                continue;
            }

            foreach ($order->get_items('line_item') as $item) {
                if ($this->isProducerLineItem($item, $producerId)) {
                    $relevantIds[] = $orderId;
                    break;
                }
            }
        }

        $notice = '';
        if (
            'POST' === $_SERVER['REQUEST_METHOD']
            && !empty($_POST['tapin_pa_bulk_nonce'])
            && wp_verify_nonce($_POST['tapin_pa_bulk_nonce'], 'tapin_pa_bulk')
        ) {
            $approveAll     = !empty($_POST['approve_all']);
            $cancelSelected = isset($_POST['bulk_cancel']);
            $selected       = array_map('absint', (array) ($_POST['order_ids'] ?? []));

            if ($approveAll) {
                $selected = $relevantIds;
            }

            $approved = 0;
            $failed   = 0;

            foreach (array_unique($selected) as $orderId) {
                if (!in_array($orderId, $relevantIds, true)) {
                    $failed++;
                    continue;
                }

                $order = wc_get_order($orderId);
                if (!$order instanceof WC_Order || 'awaiting-producer' !== $order->get_status()) {
                    $failed++;
                    continue;
                }

                if ($cancelSelected) {
                    $order->update_status('cancelled', 'ההזמנה בוטלה לבקשת המפיק.');
                    $approved++;
                } else {
                    AwaitingProducerGate::captureAndApprove($order);
                    $approved++;
                }
            }

            if ($approved || $failed) {
                $notice = sprintf(
                    '<div class="woocommerce-message" style="direction:rtl;text-align:right">אושרו %1$d הזמנות, נכשלו %2$d.</div>',
                    $approved,
                    $failed
                );
            }

            $relevantIds = array_values(array_diff($relevantIds, $selected));
        }

        $orders = [];
        $customerStats = [];

        foreach ($relevantIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }

            $summary = $this->buildOrderSummary($order, $producerId);
            if (!$summary['items']) {
                continue;
            }

            $orders[] = $summary;

            $emailKey = strtolower(trim($summary['customer']['email']));
            if ($emailKey !== '') {
                if (!isset($customerStats[$emailKey])) {
                    $customerStats[$emailKey] = [
                        'name'   => $summary['customer']['name'],
                        'email'  => $summary['customer']['email'],
                        'total'  => 0,
                        'orders' => [],
                    ];
                }
                $customerStats[$emailKey]['total'] += $summary['total_quantity'];
                $customerStats[$emailKey]['orders'][] = [
                    'order_id' => $summary['id'],
                    'quantity' => $summary['total_quantity'],
                ];
            }
        }

        $warnings = $this->buildWarnings($customerStats);

        ob_start(); ?>
        <style>
          .tapin-pa{direction:rtl;text-align:right;font-family:inherit}
          .tapin-pa .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
          .tapin-pa .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:600}
          .tapin-pa .btn-primary{background:#16a34a;color:#fff}
          .tapin-pa .btn-danger{background:#ef4444;color:#fff}
          .tapin-pa .btn-ghost{background:#f1f5f9;color:#111827}
          .tapin-pa table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(2,6,23,.05)}
          .tapin-pa thead th{background:#f8fafc;font-weight:700;border-bottom:1px solid #e5e7eb;padding:10px;font-size:.95rem;white-space:nowrap;cursor:pointer}
          .tapin-pa tbody td{border-bottom:1px solid #f1f5f9;padding:10px;vertical-align:top;font-size:.95rem}
          .tapin-pa tbody tr:last-child td{border-bottom:0}
          .tapin-pa .muted{color:#64748b;font-size:.9rem}
          .tapin-pa__warning{border-radius:12px;padding:12px;margin:10px 0;border:1px solid #facc15;background:#fefce8;color:#92400e}
          .tapin-pa__attendees-row td{background:#f9fafb}
          .tapin-pa__attendees{display:grid;gap:12px;margin:0;padding:0}
          .tapin-pa__attendee{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px}
          .tapin-pa__attendee-header{font-weight:700;color:#0f172a;margin:0 0 8px}
          .tapin-pa__attendee-list{list-style:none;margin:0;padding:0;display:grid;gap:6px}
          .tapin-pa__attendee-list li{display:flex;flex-wrap:wrap;gap:6px;font-size:.9rem;color:#1f2937}
          .tapin-pa__attendee-list strong{min-width:90px;color:#334155;font-weight:600}
          .tapin-pa__attendee-list a{color:#2563eb;text-decoration:none}
          .tapin-pa__attendee-list a:hover{text-decoration:underline}
        </style>
        <div class="tapin-pa">
          <?php echo $notice; ?>
          <h3><?php echo esc_html($this->decodeEntities('הזמנות ממתינות לאישור')); ?></h3>

          <?php foreach ($warnings as $warning): ?>
            <div class="tapin-pa__warning"><?php echo wp_kses_post($warning); ?></div>
          <?php endforeach; ?>

          <form method="post" id="tapinBulkForm">
            <?php wp_nonce_field('tapin_pa_bulk', 'tapin_pa_bulk_nonce'); ?>
            <div class="toolbar">
              <button class="btn btn-primary" type="submit" name="bulk_approve"><?php echo esc_html($this->decodeEntities('אשר נבחרות')); ?></button>
              <button class="btn btn-ghost" type="button" id="tapinApproveAll"><?php echo esc_html($this->decodeEntities('אשר הכול')); ?></button>
              <button class="btn btn-danger" type="submit" name="bulk_cancel" onclick="return confirm('<?php echo esc_js($this->decodeEntities('לבטל את ההזמנות שנבחרו?')); ?>')"><?php echo esc_html($this->decodeEntities('בטל הזמנות שנבחרו')); ?></button>
            </div>

            <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">

            <table id="tapinOrdersTable">
              <thead>
                <tr>
                  <th class="select-col"><input type="checkbox" id="tapinSelectAll" aria-label="<?php echo esc_attr($this->decodeEntities('בחר הכול')); ?>"></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('מספר הזמנה')); ?></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('שם פרטי')); ?></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('שם משפחה')); ?></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('תאריך לידה')); ?></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('מגדר')); ?></th>
                  <th data-sort>Facebook</th>
                  <th data-sort>Instagram</th>
                  <th data-sort>WhatsApp</th>
                  <th><?php echo esc_html($this->decodeEntities('סכום הזמנה')); ?></th>
                  <th><?php echo esc_html($this->decodeEntities('פרטי הזמנה')); ?></th>
                  <th data-sort><?php echo esc_html($this->decodeEntities('תאריך הזמנה')); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if ($orders): ?>
                  <?php foreach ($orders as $order): ?>
                    <tr>
                      <td class="select-col"><input type="checkbox" name="order_ids[]" value="<?php echo (int) $order['id']; ?>"></td>
                      <td>#<?php echo esc_html($order['number']); ?></td>
                      <td><?php echo esc_html($order['profile']['first_name']); ?></td>
                      <td><?php echo esc_html($order['profile']['last_name']); ?></td>
                      <td><?php echo esc_html($order['profile']['birthdate']); ?></td>
                      <td><?php echo esc_html($order['profile']['gender']); ?></td>
                      <td class="muted">
                        <?php echo $order['profile']['facebook']
                            ? '<a href="' . esc_url($order['profile']['facebook']) . '" target="_blank" rel="noopener">צפה</a>'
                            : '<span class="muted">-</span>'; ?>
                      </td>
                      <td class="muted">
                        <?php echo $order['profile']['instagram']
                            ? '<a href="' . esc_url($order['profile']['instagram']) . '" target="_blank" rel="noopener">' . esc_html($this->trimHandle($order['profile']['instagram'])) . '</a>'
                            : '<span class="muted">-</span>'; ?>
                      </td>
                      <td class="muted">
                        <?php if ($order['profile']['whatsapp']): ?>
                          <a href="https://wa.me/<?php echo esc_attr(preg_replace('/\D+/', '', $order['profile']['whatsapp'])); ?>" target="_blank" rel="noopener"><?php echo esc_html($order['profile']['whatsapp']); ?></a>
                        <?php else: ?>
                          <span class="muted">-</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo esc_html($order['total']); ?></td>
                      <td class="muted"><?php echo implode('<br>', array_map('wp_kses_post', $order['items'])); ?></td>
                      <td><?php echo esc_html($order['date']); ?></td>
                    </tr>
                    <?php
                    $attendeeCards = $order['attendees'];
                    if ($attendeeCards):
                    ?>
                      <tr class="tapin-pa__attendees-row">
                        <td></td>
                        <td colspan="11">
                          <div class="tapin-pa__attendees">
                            <?php foreach ($attendeeCards as $offset => $attendee): ?>
                              <?php
                              $displayName = trim((string) ($attendee['full_name'] ?? ''));
                              $header = sprintf(
                                  '%s %d%s',
                                  $this->decodeEntities('משתתף'),
                                  $offset + 1,
                                  $displayName !== '' ? ' - ' . $displayName : ''
                              );
                              ?>
                              <div class="tapin-pa__attendee">
                                <p class="tapin-pa__attendee-header"><?php echo esc_html($header); ?></p>
                                <ul class="tapin-pa__attendee-list">
                                  <?php if (!empty($attendee['email'])): ?>
                                    <li><strong><?php echo esc_html($this->decodeEntities('דוא"ל')); ?>:</strong><a href="mailto:<?php echo esc_attr($attendee['email']); ?>"><?php echo esc_html($attendee['email']); ?></a></li>
                                  <?php endif; ?>
                                  <?php if (!empty($attendee['phone'])): ?>
                                    <li><strong><?php echo esc_html($this->decodeEntities('טלפון')); ?>:</strong><a href="tel:<?php echo esc_attr(preg_replace('/\D+/', '', $attendee['phone'])); ?>"><?php echo esc_html($attendee['phone']); ?></a></li>
                                  <?php endif; ?>
                                  <?php if (!empty($attendee['id_number'])): ?>
                                    <li><strong><?php echo esc_html($this->decodeEntities('תעודת זהות')); ?>:</strong><span><?php echo esc_html($attendee['id_number']); ?></span></li>
                                  <?php endif; ?>
                                  <?php if (!empty($attendee['birth_date'])): ?>
                                    <li><strong><?php echo esc_html($this->decodeEntities('תאריך לידה')); ?>:</strong><span><?php echo esc_html($attendee['birth_date']); ?></span></li>
                                  <?php endif; ?>
                                  <?php if (!empty($attendee['instagram'])): ?>
                                    <li><strong>Instagram:</strong><a href="<?php echo esc_url($attendee['instagram']); ?>" target="_blank" rel="noopener"><?php echo esc_html($this->trimHandle($attendee['instagram'])); ?></a></li>
                                  <?php endif; ?>
                                  <?php if (!empty($attendee['facebook'])): ?>
                                    <li><strong>Facebook:</strong><a href="<?php echo esc_url($attendee['facebook']); ?>" target="_blank" rel="noopener"><?php echo esc_html($attendee['facebook']); ?></a></li>
                                  <?php endif; ?>
                                </ul>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="12" class="muted" style="text-align:center"><?php echo esc_html($this->decodeEntities('אין הזמנות ממתינות לאישור.')); ?></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </form>
        </div>

        <script>
        (function () {
          var form = document.getElementById('tapinBulkForm');
          var selectAll = document.getElementById('tapinSelectAll');
          if (selectAll && form) {
            selectAll.addEventListener('change', function () {
              form.querySelectorAll('tbody input[type="checkbox"][name="order_ids[]"]').forEach(function (cb) {
                cb.checked = selectAll.checked;
              });
            });
          }

          var approveAllButton = document.getElementById('tapinApproveAll');
          var approveAllField  = document.getElementById('tapinApproveAllField');
          if (approveAllButton && approveAllField) {
            approveAllButton.addEventListener('click', function () {
              approveAllField.value = '1';
              var hidden = document.createElement('input');
              hidden.type = 'hidden';
              hidden.name = 'bulk_approve';
              hidden.value = '1';
              form.appendChild(hidden);
              form.submit();
            });
          }

          var table = document.getElementById('tapinOrdersTable');
          if (table) {
            var headers = table.querySelectorAll('thead th[data-sort]');
            headers.forEach(function (header) {
              header.addEventListener('click', function () {
                var tbody = table.tBodies[0];
                var columnIndex = Array.from(header.parentNode.children).indexOf(header);
                var ascending = header.dataset.dir !== 'asc';

                var pairs = [];
                Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
                  if (row.classList.contains('tapin-pa__attendees-row')) {
                    return;
                  }
                  var detail = row.nextElementSibling;
                  if (!(detail && detail.classList.contains('tapin-pa__attendees-row'))) {
                    detail = null;
                  }
                  pairs.push({ master: row, detail: detail });
                });

                pairs.sort(function (a, b) {
                  var textA = ((a.master.children[columnIndex] || {}).textContent || '').trim().toLowerCase();
                  var textB = ((b.master.children[columnIndex] || {}).textContent || '').trim().toLowerCase();
                  if (textA < textB) return ascending ? -1 : 1;
                  if (textA > textB) return ascending ? 1 : -1;
                  return 0;
                });

                tbody.innerHTML = '';
                pairs.forEach(function (pair) {
                  tbody.appendChild(pair.master);
                  if (pair.detail) {
                    tbody.appendChild(pair.detail);
                  }
                });

                headers.forEach(function (th) { delete th.dataset.dir; });
                header.dataset.dir = ascending ? 'asc' : 'desc';
              });
            });
          }
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    private function isProducerLineItem($item, int $producerId): bool
    {
        if (!$item instanceof WC_Order_Item_Product) {
            return false;
        }

        $productId = $item->get_product_id();
        if (!$productId) {
            return false;
        }

        return (int) get_post_field('post_author', $productId) === $producerId;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildOrderSummary(WC_Order $order, int $producerId): array
    {
        $items         = [];
        $attendeesList = [];
        $totalQuantity = 0;

        foreach ($order->get_items('line_item') as $item) {
            if (!$this->isProducerLineItem($item, $producerId)) {
                continue;
            }

            $quantity = (int) $item->get_quantity();
            $items[] = sprintf('%s &#215; %d', esc_html($item->get_name()), $quantity);
            $totalQuantity += $quantity;

            foreach ($this->extractAttendees($item) as $attendee) {
                $attendeesList[] = $attendee;
            }
        }

        $userId = (int) $order->get_user_id();
        $profile = $userId
            ? $this->getUserProfile($userId)
            : [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'birthdate'  => '',
                'gender'     => '',
                'facebook'   => '',
                'instagram'  => '',
                'whatsapp'   => '',
            ];

        return [
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'date'           => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' H:i') : '',
            'total'          => wp_strip_all_tags($order->get_formatted_order_total()),
            'total_quantity' => $totalQuantity,
            'items'          => $items,
            'attendees'      => $attendeesList,
            'customer'       => [
                'name'  => trim($order->get_formatted_billing_full_name()) ?: $order->get_billing_first_name() ?: ($order->get_user() ? $order->get_user()->display_name : $this->decodeEntities('לקוח אנונימי')),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'profile'        => $profile,
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractAttendees(WC_Order_Item_Product $item): array
    {
        $json = (string) $item->get_meta('_tapin_attendees_json', true);
        if ($json === '') {
            $json = (string) $item->get_meta('Tapin Attendees', true);
        }
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_map([$this, 'normalizeAttendee'], $decoded);
            }
        }

        $order = $item->get_order();
        if ($order instanceof WC_Order) {
            $stored = $order->get_meta('_tapin_attendees', true);
            if (is_array($stored)) {
                $candidate = $stored[$item->get_id()] ?? null;
                if ($candidate === null) {
                    $productId = $item->get_product_id();
                    if ($productId && isset($stored[$productId])) {
                        $candidate = $stored[$productId];
                    }
                }
                if ($candidate === null) {
                    foreach ($stored as $entry) {
                        if (is_array($entry)) {
                            $candidate = $entry;
                            break;
                        }
                    }
                }
                if (is_array($candidate)) {
                    return array_map([$this, 'normalizeAttendee'], $candidate);
                }
            }
        }

        $fallback = [];
        $summaryKeys = AttendeeFields::summaryKeys();

        foreach ($item->get_formatted_meta_data('') as $meta) {
            $label = (string) $meta->key;
            if (
                strpos($label, 'המשתתף') === 0
                || strpos($label, 'Participant') === 0
                || strpos($label, '???') === 0
            ) {
                $parts = array_map('trim', explode('|', $meta->value));
                $data  = array_combine($summaryKeys, array_pad($parts, count($summaryKeys), ''));
                if ($data !== false) {
                    $fallback[] = $this->normalizeAttendee($data);
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string,string> $data
     */
    private function normalizeAttendee(array $data): array
    {
        $normalized = [];
        foreach (AttendeeFields::keys() as $key) {
            $raw = (string) ($data[$key] ?? '');
            $sanitized = AttendeeFields::sanitizeValue($key, $raw);
            $normalized[$key] = $sanitized !== '' ? $sanitized : AttendeeFields::displayValue($key, $raw);
        }
        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    private function buildWarnings(array $stats): array
    {
        $warnings = [];

        foreach ($stats as $customer) {
            $largeOrders = array_filter(
                $customer['orders'],
                static fn($entry) => $entry['quantity'] >= self::LARGE_ORDER_THRESHOLD
            );

            if ($customer['total'] >= self::CUSTOMER_TOTAL_THRESHOLD || count($largeOrders) >= 2) {
                $name  = esc_html($customer['name'] ?: $customer['email']);
                $email = esc_html($customer['email']);
                $warnings[] = sprintf(
                    'שים לב: %1$s (%2$s) רכש %3$d כרטיסים בסך הכול.',
                    $name,
                    $email,
                    (int) $customer['total']
                );
            }
        }

        return $warnings;
    }


    private function getUserProfile(int $userId): array
    {
        $profile = [
            'first_name' => $this->getUserMetaMulti($userId, ['first_name', 'um_first_name']),
            'last_name'  => $this->getUserMetaMulti($userId, ['last_name', 'um_last_name']),
            'birthdate'  => $this->getUserMetaMulti($userId, ['birth_date', 'date_of_birth', 'um_birthdate', 'birthdate']),
            'gender'     => $this->getUserMetaMulti($userId, ['gender', 'um_gender', 'sex']),
            'facebook'   => $this->getUserMetaMulti($userId, ['facebook', 'facebook_url']),
            'instagram'  => $this->getUserMetaMulti($userId, ['instagram', 'instagram_url']),
            'whatsapp'   => $this->getUserMetaMulti($userId, ['whatsapp', 'whatsapp_number', 'whatsapp_phone', 'phone_whatsapp']),
        ];

        $profile['facebook']  = AttendeeFields::displayValue('facebook', $profile['facebook']);
        $profile['instagram'] = AttendeeFields::displayValue('instagram', $profile['instagram']);
        $profile['whatsapp']  = AttendeeFields::displayValue('phone', $profile['whatsapp']);

        return $profile;
    }

    private function getUserMetaMulti(int $userId, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->cleanMetaValue(get_user_meta($userId, $key, true));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function cleanMetaValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        return trim(wp_strip_all_tags((string) $value));
    }

    private function trimHandle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $handle = '';
        if (preg_match('#instagram\.com/([^/?#]+)#i', $value, $matches)) {
            $handle = $matches[1];
        } else {
            $handle = ltrim($value, '@/');
        }

        return $handle !== '' ? '@' . $handle : '';
    }

    private function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}




