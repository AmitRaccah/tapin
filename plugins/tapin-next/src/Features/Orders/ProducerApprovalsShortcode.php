<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\Orders;
use Tapin\Events\Support\Security;
use Tapin\Events\Support\Time;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_User;

final class ProducerApprovalsShortcode implements Service
{
    private const LARGE_ORDER_THRESHOLD    = 10;
    private const CUSTOMER_TOTAL_THRESHOLD = 20;

    /** @var array<int,bool> */
    private array $auditedOrders = [];

    public function register(): void
    {
        add_shortcode('producer_order_approvals', [$this, 'render']);
        add_action('admin_post_tapin_pa_export_event', [$this, 'exportEvent']);
    }

    public function render(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="woocommerce-info" style="direction:rtl;text-align:right">&#1497;&#1513;&#32;&#1500;&#1492;&#1510;&#1495;&#1489;&#1512;&#32;&#1499;&#1491;&#1497;&#32;&#1500;&#1510;&#1508;&#1493;&#1514;&#32;&#1489;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;.</div>';
        }

        $guard = Security::producer();
        if (!$guard->allowed) {
            return $guard->message !== ''
                ? $guard->message
                : '<div class="woocommerce-error" style="direction:rtl;text-align:right">&#1488;&#1497;&#1503;&#32;&#1500;&#1495;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#32;&#1500;&#1510;&#1508;&#1493;&#1514;&#32;&#1489;&#1506;&#1502;&#1493;&#1491;&#32;&#1494;&#1492;.</div>';
        }

        $viewer     = $guard->user instanceof WP_User ? $guard->user : wp_get_current_user();
        $producerId = $viewer instanceof WP_User ? (int) $viewer->ID : (int) get_current_user_id();

        $canDownloadExport = $this->canDownloadOrders($viewer instanceof WP_User ? $viewer : null);

        $orderSets   = $this->resolveProducerOrderIds($producerId);
        $relevantIds = $orderSets['relevant'];
        $displayIds  = $orderSets['display'];

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
                if (!$order instanceof WC_Order || AwaitingProducerStatus::STATUS_SLUG !== $order->get_status()) {
                    $failed++;
                    continue;
                }

                if ($cancelSelected) {
                    $order->update_status('cancelled', '&#1492;&#1492;&#1494;&#1502;&#1504;&#1492;&#32;&#1489;&#1493;&#1496;&#1500;&#1488;&#32;&#1500;&#1489;&#1511;&#1513;&#1514;&#32;&#1492;&#1502;&#1508;&#1497;&#1511;.');
                    $approved++;
                } else {
                    AwaitingProducerGate::captureAndApprove($order);
                    $approved++;
                }
            }

            if ($approved || $failed) {
                $notice = sprintf(
                    '<div class="woocommerce-message" style="direction:rtl;text-align:right">&#1488;&#1493;&#1513;&#1512;&#1493;&#32;%1$d&#32;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;,&#32;&#1504;&#1499;&#1513;&#1500;&#1493;&#32;%2$d.</div>',
                    $approved,
                    $failed
                );
            }

            $orderSets   = $this->resolveProducerOrderIds($producerId);
            $relevantIds = $orderSets['relevant'];
            $displayIds  = $orderSets['display'];
        }

        $orderCollections = $this->summarizeOrders($displayIds, $producerId);
        $orders           = $orderCollections['orders'];
        $customerStats    = $orderCollections['customer_stats'];

        $customerWarnings = $this->buildWarnings($customerStats);

        $events = $this->groupOrdersByEvent($orders, $customerWarnings);

        ob_start(); ?>
        <style>
          .tapin-pa{direction:rtl;text-align:right;font-family:inherit;color:#0f172a}
          .tapin-pa a{color:inherit}
          .tapin-pa .btn{padding:10px 16px;border-radius:12px;border:0;cursor:pointer;font-weight:600;font-size:.95rem}
          .tapin-pa .btn-primary{background:#16a34a;color:#fff}
          .tapin-pa .btn-danger{background:#ef4444;color:#fff}
          .tapin-pa .btn-ghost{background:#e2e8f0;color:#1f2937}
          .tapin-pa__form{margin:0}
          .tapin-pa__controls{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}
          .tapin-pa__search{flex:1 1 260px;min-width:220px}
          .tapin-pa__search-input{width:100%;padding:10px 14px;border-radius:12px;border:1px solid #cbd5f5;background:#fff;font-size:.95rem}
          .tapin-pa__buttons{display:flex;gap:8px;flex-wrap:wrap}
          .tapin-pa__events{display:grid;gap:16px}
          .tapin-pa__warning{border-radius:12px;padding:12px;margin:10px 0;border:1px solid #facc15;background:#fefce8;color:#92400e}
          .tapin-pa-order__warnings{display:grid;gap:10px}
          .tapin-pa-event{border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 6px 18px rgba(15,23,42,.08);overflow:hidden;background:#fff}
          .tapin-pa-event__header{display:flex;justify-content:space-between;align-items:center;width:100%;background:transparent;border:0;padding:18px;cursor:pointer;text-align:right}
          .tapin-pa-event__header:hover{background:#f8fafc}
          .tapin-pa-event__summary{display:flex;gap:16px;align-items:center}
          .tapin-pa-event__image{width:72px;height:72px;border-radius:14px;object-fit:cover;background:#f1f5f9;flex-shrink:0}
          .tapin-pa-event__text h4{margin:0;font-size:1.1rem;font-weight:700;color:#0f172a}
          .tapin-pa-event__stats{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;font-size:.9rem}
          .tapin-pa-event__panel-heading{margin:18px 0 12px;font-size:1rem;font-weight:700;color:#1f2937}
          .tapin-pa-event__badge{padding:4px 10px;border-radius:999px;font-weight:600;font-size:.85rem;display:inline-flex;align-items:center}
          .tapin-pa-event__badge--pending{background:rgba(234,179,8,.18);color:#92400e}
          .tapin-pa-event__badge--approved{background:rgba(22,163,74,.18);color:#065f46}
          .tapin-pa-event__badge--cancelled{background:rgba(248,113,113,.18);color:#991b1b}
          .tapin-pa-event__chevron{transition:transform .25s ease;color:#334155;font-size:1.2rem}
          .tapin-pa-event.is-open .tapin-pa-event__chevron{transform:rotate(180deg)}
          .tapin-pa-event__panel{padding:0 18px 18px;display:none}
          .tapin-pa-event.is-open .tapin-pa-event__panel{display:block}
          .tapin-pa-event__actions{display:flex;justify-content:flex-end;gap:8px;margin:14px 0}
          .tapin-pa-event__actions .btn{font-size:.85rem;padding:8px 14px}
          .tapin-pa-order{position:relative;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-top:18px;background:#ffffff;transition:background-color .25s ease,border-color .25s ease,box-shadow .25s ease;box-shadow:0 4px 14px rgba(15,23,42,.08)}
          .tapin-pa-order--alt{background:#eef2ff;border-color:#c7d2fe;box-shadow:0 10px 26px rgba(59,130,246,.12)}
          .tapin-pa-order::after{content:'';position:absolute;inset:-1px auto -1px -1px;width:5px;border-radius:14px 0 0 14px;background:#e2e8f0;transition:background-color .25s ease}
          .tapin-pa-order.tapin-pa-order--pending::after{background:#fbbf24}
          .tapin-pa-order.tapin-pa-order--approved::after{background:#22c55e}
          .tapin-pa-order.tapin-pa-order--cancelled::after{background:#ef4444}
          .tapin-pa-order + .tapin-pa-order{margin-top:24px}
          .tapin-pa-order__header{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
          .tapin-pa-order__left{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
          .tapin-pa-order__checkbox{width:18px;height:18px}
          .tapin-pa-order__meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.9rem;color:#475569}
          .tapin-pa-order__status{padding:4px 10px;border-radius:999px;font-weight:600;font-size:.85rem}
          .tapin-pa-order--pending .tapin-pa-order__status{background:rgba(234,179,8,.18);color:#92400e}
          .tapin-pa-order--approved .tapin-pa-order__status{background:rgba(22,163,74,.18);color:#065f46}
          .tapin-pa-order--cancelled .tapin-pa-order__status{background:rgba(248,113,113,.18);color:#991b1b}
          .tapin-pa-order__body{margin-top:18px;display:grid;gap:20px;font-size:.94rem;color:#0f172a}
          .tapin-pa-order__section{display:grid;gap:12px}
          .tapin-pa-order__section + .tapin-pa-order__section{margin-top:6px}
          .tapin-pa-order__section-title{margin:0;font-size:.95rem;font-weight:700;color:#1f2937}
          .tapin-pa-order__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px 18px}
          .tapin-pa-order__card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:4px}
          .tapin-pa-order--alt .tapin-pa-order__card{background:#ffffff;border-color:#c7d2fe;box-shadow:0 6px 16px rgba(59,130,246,.12)}
          .tapin-pa-order__label{font-size:.82rem;color:#64748b;font-weight:600}
          .tapin-pa-order__value{word-break:break-word;color:#0f172a;font-weight:500}
          .tapin-pa-order__value a{color:#2563eb;text-decoration:none}
          .tapin-pa-order__value a:hover{text-decoration:underline}
          .tapin-pa-order__lines{list-style:none;margin:0;padding:0;display:grid;gap:6px;font-size:.88rem;color:#475569}
          .tapin-pa-attendees{display:grid;gap:16px;border-radius:14px}
          .tapin-pa-attendees__grid{display:grid;gap:14px}
          .tapin-pa-order--alt .tapin-pa-attendees{background:#eef2ff;border:1px solid #c7d2fe;padding:18px}
          .tapin-pa-order:not(.tapin-pa-order--alt) .tapin-pa-attendees{background:#f8fafc;border:1px solid #e2e8f0;padding:18px}
          .tapin-pa-attendee{background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 6px 14px rgba(15,23,42,.06);display:grid;gap:12px}
          .tapin-pa-order--alt .tapin-pa-attendee{background:#f5f3ff;border-color:#c7d2fe}
          .tapin-pa-attendee__header{display:flex;justify-content:space-between;align-items:center;gap:8px}
          .tapin-pa-attendee__title{margin:0;font-size:1rem;font-weight:700;color:#111827}
          .tapin-pa-attendee__badge{background:#fde68a;color:#92400e;padding:4px 8px;border-radius:999px;font-size:.75rem;font-weight:600}
          .tapin-pa-attendee__list{list-style:none;margin:0;padding:0;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
          .tapin-pa-attendee__label{font-size:.8rem;color:#64748b;font-weight:600;margin-bottom:2px}
          .tapin-pa-attendee__value{font-weight:500;color:#0f172a}
          .tapin-pa-attendee__list li{display:flex;flex-direction:column;gap:2px;word-break:break-word}
          .tapin-pa-attendee__list a{color:#2563eb;text-decoration:none}
          .tapin-pa-attendee__list a:hover{text-decoration:underline}
          .tapin-pa-empty{padding:48px 12px;border:2px dashed #cbd5f5;border-radius:16px;text-align:center;color:#64748b;font-size:1rem;background:#f8fafc}
          @media (max-width:640px){
            .tapin-pa-event__header{flex-direction:column;align-items:flex-start}
            .tapin-pa-order__header{flex-direction:column;align-items:flex-start}
            .tapin-pa-order__meta{flex-direction:column;align-items:flex-start}
            .tapin-pa-order__grid{grid-template-columns:1fr}
            .tapin-pa-attendee__list{grid-template-columns:1fr}
            .tapin-pa__buttons{width:100%}
            .tapin-pa__buttons .btn{flex:1 1 auto;text-align:center}
          }
        </style>
        <div class="tapin-pa">
          <?php echo $notice; ?>
          <h3><?php echo esc_html($this->decodeEntities('&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1502;&#1502;&#1514;&#1497;&#1504;&#1493;&#1514;&#32;&#1500;&#1488;&#1497;&#1513;&#1493;&#1512;')); ?></h3>

          <form method="post" id="tapinBulkForm" class="tapin-pa__form">
            <?php wp_nonce_field('tapin_pa_bulk', 'tapin_pa_bulk_nonce'); ?>
            <div class="tapin-pa__controls">
              <div class="tapin-pa__search">
                <input type="search" id="tapinPaSearch" class="tapin-pa__search-input" placeholder="<?php echo esc_attr($this->decodeEntities('&#1495;&#1497;&#1508;&#1493;&#1513;&#32;&#1500;&#1508;&#1497;&#32;&#1500;&#1511;&#1493;&#1495;&#32;&#1488;&#1493;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#46;&#46;&#46;')); ?>">
              </div>
              <div class="tapin-pa__buttons">
                <button class="btn btn-ghost" type="button" id="tapinPaSelectAll"><?php echo esc_html($this->decodeEntities('&#1489;&#1495;&#1512;&#32;&#1492;&#1499;&#1500;')); ?></button>
                <button class="btn btn-primary" type="submit" name="bulk_approve"><?php echo esc_html($this->decodeEntities('&#1488;&#1513;&#1512;&#32;&#1504;&#1489;&#1495;&#1512;&#1493;&#1514;')); ?></button>
                <button class="btn btn-ghost" type="button" id="tapinApproveAll"><?php echo esc_html($this->decodeEntities('&#1488;&#1513;&#1512;&#32;&#1492;&#1499;&#1500;')); ?></button>
                <button class="btn btn-danger" type="submit" name="bulk_cancel" onclick="return confirm('<?php echo esc_js($this->decodeEntities('&#1500;&#1489;&#1496;&#1500;&#32;&#1488;&#1514;&#32;&#1492;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1513;&#1504;&#1489;&#1495;&#1512;&#1493;&#63;')); ?>')"><?php echo esc_html($this->decodeEntities('&#1489;&#1496;&#1500;&#32;&#1492;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1513;&#1504;&#1489;&#1495;&#1512;&#1493;')); ?></button>
              </div>
            </div>

            <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">

            <?php if ($events): ?>
              <div class="tapin-pa__events" id="tapinPaEvents">
                <?php foreach ($events as $index => $event): ?>
                  <?php $isOpen = (($event['counts']['pending'] ?? 0) > 0) || $index === 0; ?>
                  <div class="tapin-pa-event<?php echo $isOpen ? ' is-open' : ''; ?>" data-search="<?php echo esc_attr($event['search']); ?>">
                    <button class="tapin-pa-event__header" type="button" data-event-toggle aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                      <div class="tapin-pa-event__summary">
                        <?php if (!empty($event['image'])): ?>
                          <img class="tapin-pa-event__image" src="<?php echo esc_url($event['image']); ?>" alt="">
                        <?php else: ?>
                          <div class="tapin-pa-event__image" aria-hidden="true"></div>
                        <?php endif; ?>
                        <div class="tapin-pa-event__text">
                          <h4>
                            <?php if (!empty($event['permalink'])): ?>
                              <a href="<?php echo esc_url($event['permalink']); ?>" target="_blank" rel="noopener"><?php echo esc_html($event['title']); ?></a>
                            <?php else: ?>
                              <?php echo esc_html($event['title']); ?>
                            <?php endif; ?>
                          </h4>
                          <div class="tapin-pa-event__stats">
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--pending"><?php echo esc_html($this->decodeEntities('&#1502;&#1502;&#1514;&#1497;&#1504;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['pending'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--approved"><?php echo esc_html($this->decodeEntities('&#1502;&#1488;&#1493;&#1513;&#1512;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['approved'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--cancelled"><?php echo esc_html($this->decodeEntities('&#1502;&#1489;&#1493;&#1496;&#1500;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['cancelled'] ?? 0); ?></span>
                          </div>
                        </div>
                      </div>
                      <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="tapin-pa-event__panel"<?php echo $isOpen ? '' : ' hidden'; ?>>
                      <?php if (!empty($event['event_date_label'])): ?>
                        <?php $eventDateHeading = $this->decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1492;&#1488;&#1497;&#1512;&#1493;&#1506;'); ?>
                        <h4 class="tapin-pa-event__panel-heading">
                          <?php echo esc_html($eventDateHeading . ': ' . $event['event_date_label']); ?>
                        </h4>
                      <?php endif; ?>
                      <?php if ($canDownloadExport && !empty($event['orders'])): ?>
                        <?php
                        $downloadUrl = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action'   => 'tapin_pa_export_event',
                                    'event_id' => (int) ($event['id'] ?? 0),
                                ],
                                admin_url('admin-post.php')
                            ),
                            'tapin_pa_export_event_' . (int) ($event['id'] ?? 0),
                            'tapin_pa_export_nonce'
                        );
                        ?>
                        <div class="tapin-pa-event__actions">
                          <a class="btn btn-ghost tapin-pa-event__download" href="<?php echo esc_url($downloadUrl); ?>">
                            <?php echo esc_html($this->decodeEntities('&#1492;&#1493;&#1512;&#1491;&#1514;&#32;&#1489;&#1511;&#1513;&#1493;&#1514;')); ?>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($event['orders'])): ?>
                        <?php foreach ($event['orders'] as $orderIndex => $orderData): ?>
                          <?php
                          $statusLabel = $orderData['status_type'] === 'pending'
                              ? $this->decodeEntities('&#1502;&#1502;&#1514;&#1497;&#1504;&#1497;&#1501;')
                              : ($orderData['status_type'] === 'approved'
                                  ? $this->decodeEntities('&#1488;&#1493;&#1513;&#1512;')
                                  : $this->decodeEntities('&#1502;&#1489;&#1493;&#1496;&#1500;'));
                          $statusClass = 'tapin-pa-order--' . sanitize_html_class($orderData['status_type']);
                          $altClass = ($orderIndex % 2 === 1) ? ' tapin-pa-order--alt' : '';
                          ?>
                          <article class="tapin-pa-order <?php echo esc_attr($statusClass); ?><?php echo $altClass; ?>" data-search="<?php echo esc_attr($orderData['search_blob']); ?>">
                            <header class="tapin-pa-order__header">
                              <div class="tapin-pa-order__left">
                                <?php if ($orderData['is_pending']): ?>
                                  <input class="tapin-pa-order__checkbox" type="checkbox" name="order_ids[]" value="<?php echo (int) $orderData['id']; ?>" data-pending="1">
                                <?php else: ?>
                                  <input class="tapin-pa-order__checkbox" type="checkbox" disabled title="Already processed">
                                <?php endif; ?>
                                <div>
                                  <div><strong><?php echo esc_html('#' . $orderData['number']); ?></strong></div>
                                  <div class="tapin-pa-order__meta">
                                    <?php if ($orderData['date']): ?><span><?php echo esc_html($orderData['date']); ?></span><?php endif; ?>
                                    <?php if ($orderData['total']): ?><span><?php echo esc_html($orderData['total']); ?></span><?php endif; ?>
                                    <?php if ($orderData['quantity']): ?><span><?php echo esc_html($orderData['quantity'] . 'x'); ?></span><?php endif; ?>
                                  </div>
                                </div>
                              </div>
                              <span class="tapin-pa-order__status"><?php echo esc_html($statusLabel); ?></span>
                            </header>
                            <div class="tapin-pa-order__body">
                              <?php if (!empty($orderData['warnings'])): ?>
                                <div class="tapin-pa-order__warnings">
                                  <?php foreach ($orderData['warnings'] as $warning): ?>
                                    <div class="tapin-pa__warning"><?php echo wp_kses_post($warning); ?></div>
                                  <?php endforeach; ?>
                                </div>
                              <?php endif; ?>
                              <?php
                              $contactRows = [];
                              $nameValue = trim((string) ($orderData['customer']['name'] ?? ''));
                              if ($nameValue !== '') {
                                  $contactRows[] = [
                                      'label' => $this->decodeEntities('&#1513;&#1502;&#32;&#1492;&#1500;&#1511;&#1493;&#1495;'),
                                      'value' => $nameValue,
                                      'type'  => 'text',
                                      'href'  => '',
                                  ];
                              }

                              $customerEmail = trim((string) ($orderData['customer']['email'] ?? ''));
                              if ($customerEmail !== '') {
                                  $contactRows[] = [
                                      'label' => 'Email',
                                      'value' => $customerEmail,
                                      'type'  => 'email',
                                      'href'  => $customerEmail,
                                  ];
                              }

                              $customerPhone = trim((string) ($orderData['customer']['phone'] ?? ''));
                              if ($customerPhone !== '') {
                                  $digitsOnly = preg_replace('/\D+/', '', $customerPhone);
                                  $telHref = preg_replace('/[^0-9+]/', '', $customerPhone);
                                  $contactRows[] = [
                                      'label' => $this->decodeEntities('&#1496;&#1500;&#1508;&#1493;&#1503;'),
                                      'value' => $customerPhone,
                                      'type'  => $digitsOnly !== '' ? 'phone' : 'text',
                                      'href'  => $telHref !== '' ? $telHref : $digitsOnly,
                                  ];
                              }

                              if (!empty($orderData['primary_id_number'])) {
                                  $contactRows[] = [
                                      'label' => $this->decodeEntities('&#1514;&#1506;&#1493;&#1491;&#1514;&#32;&#1494;&#1492;&#1493;&#1514;'),
                                      'value' => (string) $orderData['primary_id_number'],
                                      'type'  => 'text',
                                      'href'  => '',
                                  ];
                              }

                              $customerProfileMeta = (array) ($orderData['customer_profile'] ?? []);
                              $profileUsernameMeta = trim((string) ($customerProfileMeta['username'] ?? ''));
                              $profileUrlMeta = trim((string) ($customerProfileMeta['url'] ?? ''));
                              if ($profileUsernameMeta !== '') {
                                  $contactRows[] = [
                                      'label' => $this->decodeEntities('&#1508;&#1512;&#1493;&#1508;&#1497;&#1500;&#32;&#1489;&#84;&#97;&#112;&#105;&#110;'),
                                      'value' => '@' . ltrim($profileUsernameMeta, '@'),
                                      'type'  => $profileUrlMeta !== '' ? 'link' : 'text',
                                      'href'  => $profileUrlMeta,
                                  ];
                              }

                              $profileFieldMap = [
                                  $this->decodeEntities('&#1513;&#1501;&#32;&#1508;&#1512;&#1496;&#1497;') => $orderData['profile']['first_name'] ?? '',
                                  $this->decodeEntities('&#1513;&#1501;&#32;&#1502;&#1513;&#1508;&#1495;&#1492;') => $orderData['profile']['last_name'] ?? '',
                                  $this->decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1500;&#1497;&#1491;&#1492;') => $orderData['profile']['birthdate'] ?? '',
                                  $this->decodeEntities('&#1502;&#1490;&#1491;&#1512;') => $orderData['profile']['gender'] ?? '',
                                  'Facebook' => $orderData['profile']['facebook'] ?? '',
                                  'Instagram' => $orderData['profile']['instagram'] ?? '',
                                  'WhatsApp' => $orderData['profile']['whatsapp'] ?? '',
                              ];

                              $profileRows = [];
                              foreach ($profileFieldMap as $label => $rawValue) {
                                  $value = trim((string) $rawValue);
                                  if ($value === '') {
                                      continue;
                                  }

                                  $type = 'text';
                                  $href = '';
                                  $displayValue = $value;

                                  if ($label === 'Facebook' || $label === 'Instagram') {
                                      $candidate = $value;
                                      if ($label === 'Instagram') {
                                          $handle = $this->trimHandle($value);
                                          if ($handle !== '') {
                                              $candidate = 'https://instagram.com/' . ltrim($handle, '@');
                                              $displayValue = $handle;
                                          } else {
                                              $altHandle = $this->trimHandle('@' . ltrim($value, '@/'));
                                              if ($altHandle !== '') {
                                                  $displayValue = $altHandle;
                                              }
                                          }
                                      }

                                      if (!preg_match('#^https?://#i', $candidate)) {
                                          $candidate = 'https://' . ltrim($candidate, '/');
                                      }

                                      $isValidUrl = filter_var($candidate, FILTER_VALIDATE_URL);
                                      if ($isValidUrl) {
                                          $type = 'link';
                                          $href = $candidate;
                                      }
                                  } elseif ($label === 'WhatsApp') {
                                      $digits = preg_replace('/\D+/', '', $value);
                                      if ($digits !== '') {
                                          $type = 'link';
                                          $href = 'https://wa.me/' . $digits;
                                      }
                                  }

                                  $profileRows[] = [
                                      'label' => $label,
                                      'value' => $displayValue,
                                      'type'  => $type,
                                      'href'  => $href,
                                  ];
                              }
                              ?>

                              <?php if ($contactRows): ?>
                                <div class="tapin-pa-order__section">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html($this->decodeEntities('&#1508;&#1512;&#1496;&#1497;&#32;&#1500;&#1511;&#1493;&#1495;')); ?></h5>
                                  <div class="tapin-pa-order__grid">
                                    <?php foreach ($contactRows as $row): ?>
                                      <div class="tapin-pa-order__card">
                                        <span class="tapin-pa-order__label"><?php echo esc_html($row['label']); ?></span>
                                        <span class="tapin-pa-order__value">
                                          <?php if ($row['type'] === 'email'): ?>
                                            <a href="mailto:<?php echo esc_attr($row['href']); ?>"><?php echo esc_html($row['value']); ?></a>
                                          <?php elseif ($row['type'] === 'phone' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="tel:<?php echo esc_attr($row['href']); ?>"><?php echo esc_html($row['value']); ?></a>
                                          <?php elseif ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="<?php echo esc_url($row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['value']); ?></a>
                                          <?php else: ?>
                                            <?php echo esc_html($row['value']); ?>
                                          <?php endif; ?>
                                        </span>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <?php if ($profileRows): ?>
                                <div class="tapin-pa-order__section">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html($this->decodeEntities('&#1508;&#1512;&#1496;&#1497;&#32;&#1508;&#1512;&#1493;&#1508;&#1497;&#1500;')); ?></h5>
                                  <div class="tapin-pa-order__grid">
                                    <?php foreach ($profileRows as $row): ?>
                                      <div class="tapin-pa-order__card">
                                        <span class="tapin-pa-order__label"><?php echo esc_html($row['label']); ?></span>
                                        <span class="tapin-pa-order__value">
                                          <?php if ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="<?php echo esc_url($row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['value']); ?></a>
                                          <?php else: ?>
                                            <?php echo esc_html($row['value']); ?>
                                          <?php endif; ?>
                                        </span>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>
                              <?php if (!empty($orderData['lines'])): ?>
                                <ul class="tapin-pa-order__lines">
                                  <?php foreach ($orderData['lines'] as $line): ?>
                                    <?php
                                    $lineQuantity = (int) ($line['quantity'] ?? 0);
                                    $lineParts = [];
                                    $lineName = trim((string) ($line['name'] ?? ''));
                                    if ($lineName !== '') {
                                        $lineParts[] = $lineName;
                                    }
                                    if ($lineQuantity > 0) {
                                        $lineParts[] = 'x ' . $lineQuantity;
                                    }
                                    $lineTotal = !empty($line['total']) ? trim(wp_strip_all_tags((string) $line['total'])) : '';
                                    if ($lineTotal !== '') {
                                        $lineParts[] = '(' . $lineTotal . ')';
                                    }
                                    $lineText = trim(implode(' ', $lineParts));
                                    ?>
                                    <li><?php echo esc_html($lineText); ?></li>
                                  <?php endforeach; ?>
                                </ul>
                              <?php endif; ?>
                              <?php if (!empty($orderData['attendees'])): ?>
                                <div class="tapin-pa-order__section tapin-pa-attendees">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html($this->decodeEntities('&#1502;&#1493;&#1494;&#1502;&#1504;&#1497;&#1501;')); ?></h5>
                                  <div class="tapin-pa-attendees__grid">
                                    <?php foreach ($orderData['attendees'] as $attendee): ?>
                                      <?php
                                      $attendeeRows = [];

                                      if (!empty($attendee['email'])) {
                                          $email = trim((string) $attendee['email']);
                                          if ($email !== '') {
                                              $attendeeRows[] = [
                                                  'label' => 'Email',
                                                  'value' => $email,
                                                  'type'  => 'email',
                                                  'href'  => $email,
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['phone'])) {
                                          $phone = trim((string) $attendee['phone']);
                                          if ($phone !== '') {
                                              $digits = preg_replace('/\D+/', '', $phone);
                                              $href = preg_replace('/[^0-9+]/', '', $phone);
                                              $attendeeRows[] = [
                                                  'label' => $this->decodeEntities('&#1496;&#1500;&#1508;&#1493;&#1503;'),
                                                  'value' => $phone,
                                                  'type'  => $digits !== '' ? 'phone' : 'text',
                                                  'href'  => $href !== '' ? $href : $digits,
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['id_number'])) {
                                          $idNumber = trim((string) $attendee['id_number']);
                                          if ($idNumber !== '') {
                                              $attendeeRows[] = [
                                                  'label' => $this->decodeEntities('&#1514;&#1506;&#1493;&#1491;&#1514;&#32;&#1494;&#1492;&#1493;&#1514;'),
                                                  'value' => $idNumber,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['birth_date'])) {
                                          $birthDate = trim((string) $attendee['birth_date']);
                                          if ($birthDate !== '') {
                                              $attendeeRows[] = [
                                                  'label' => $this->decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1500;&#1497;&#1491;&#1492;'),
                                                  'value' => $birthDate,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['gender'])) {
                                          $genderRaw = trim((string) $attendee['gender']);
                                          $genderValue = AttendeeFields::displayValue('gender', $genderRaw);
                                          if ($genderValue !== '') {
                                              $attendeeRows[] = [
                                                  'label' => $this->decodeEntities('&#1502;&#1490;&#1491;&#1512;'),
                                                  'value' => $genderValue,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['instagram'])) {
                                          $instagramRaw = trim((string) $attendee['instagram']);
                                          if ($instagramRaw !== '') {
                                              $candidate = $instagramRaw;
                                              $display = $this->trimHandle($instagramRaw);
                                              if ($display === '') {
                                                  $display = $instagramRaw;
                                              }
                                      if (!preg_match('#^https?://#i', $candidate)) {
                                          $candidate = 'https://' . ltrim($candidate, '/');
                                      }
                                      $isValidUrl = filter_var($candidate, FILTER_VALIDATE_URL);
                                      $attendeeRows[] = [
                                          'label' => 'Instagram',
                                          'value' => $display,
                                          'type'  => $isValidUrl ? 'link' : 'text',
                                          'href'  => $isValidUrl ? $candidate : '',
                                      ];
                                          }
                                      }

                                      if (!empty($attendee['facebook'])) {
                                          $facebookRaw = trim((string) $attendee['facebook']);
                                          if ($facebookRaw !== '') {
                                              $candidate = $facebookRaw;
                                      if (!preg_match('#^https?://#i', $candidate)) {
                                          $candidate = 'https://' . ltrim($candidate, '/');
                                      }
                                      $isValidUrl = filter_var($candidate, FILTER_VALIDATE_URL);
                                      $attendeeRows[] = [
                                          'label' => 'Facebook',
                                          'value' => $facebookRaw,
                                          'type'  => $isValidUrl ? 'link' : 'text',
                                          'href'  => $isValidUrl ? $candidate : '',
                                      ];
                                          }
                                      }

                                      if (!empty($attendee['whatsapp'])) {
                                          $whatsappRaw = trim((string) $attendee['whatsapp']);
                                          if ($whatsappRaw !== '') {
                                              $digits = preg_replace('/\D+/', '', $whatsappRaw);
                                              if ($digits !== '') {
                                                  $attendeeRows[] = [
                                                      'label' => 'WhatsApp',
                                                      'value' => $whatsappRaw,
                                                      'type'  => 'link',
                                                      'href'  => 'https://wa.me/' . $digits,
                                                  ];
                                              }
                                          }
                                      }
                                      ?>

                                      <div class="tapin-pa-attendee">
                                        <div class="tapin-pa-attendee__header">
                                          <h6 class="tapin-pa-attendee__title">
                                            <?php
                                            $displayName = trim((string) ($attendee['full_name'] ?? ''));
                                            echo esc_html($displayName !== '' ? $displayName : $this->decodeEntities('&#1488;&#1493;&#1512;&#1495;'));
                                            ?>
                                          </h6>
                                          <span class="tapin-pa-attendee__badge"><?php echo esc_html($this->decodeEntities('&#1502;&#1493;&#1494;&#1502;&#1503;&#47;&#1514;')); ?></span>
                                        </div>
                                        <?php if ($attendeeRows): ?>
                                          <ul class="tapin-pa-attendee__list">
                                            <?php foreach ($attendeeRows as $row): ?>
                                              <li>
                                                <span class="tapin-pa-attendee__label"><?php echo esc_html($row['label']); ?></span>
                                                <span class="tapin-pa-attendee__value">
                                                  <?php if ($row['type'] === 'email'): ?>
                                                    <a href="mailto:<?php echo esc_attr($row['href']); ?>"><?php echo esc_html($row['value']); ?></a>
                                                  <?php elseif ($row['type'] === 'phone' && ($row['href'] ?? '') !== ''): ?>
                                                    <a href="tel:<?php echo esc_attr($row['href']); ?>"><?php echo esc_html($row['value']); ?></a>
                                                  <?php elseif ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                                    <a href="<?php echo esc_url($row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['value']); ?></a>
                                                  <?php else: ?>
                                                    <?php echo esc_html($row['value']); ?>
                                                  <?php endif; ?>
                                                </span>
                                              </li>
                                            <?php endforeach; ?>
                                          </ul>
                                        <?php endif; ?>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="tapin-pa-empty"><?php echo esc_html($this->decodeEntities('&#1488;&#1497;&#1503;&#32;&#1489;&#1511;&#1513;&#1493;&#1514;&#32;&#1506;&#1489;&#1493;&#1512;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1494;&#1492;&#46;')); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="tapin-pa-empty"><?php echo esc_html($this->decodeEntities('&#1488;&#1497;&#1503;&#32;&#1488;&#1497;&#1513;&#1493;&#1512;&#1497;&#1501;&#32;&#1500;&#1492;&#1510;&#1490;&#1492;&#32;&#1499;&#1512;&#1490;&#1506;&#46;')); ?></div>
            <?php endif; ?>
          </form>
        </div>

        <script>
        (function () {
          var form = document.getElementById('tapinBulkForm');
          var approveAllButton = document.getElementById('tapinApproveAll');
          var approveAllField = document.getElementById('tapinApproveAllField');
          if (approveAllButton && approveAllField && form) {
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

          var selectAllButton = document.getElementById('tapinPaSelectAll');
          if (selectAllButton && form) {
            selectAllButton.addEventListener('click', function () {
              var checkboxes = Array.prototype.slice.call(form.querySelectorAll('.tapin-pa-order__checkbox[data-pending="1"]:not(:disabled)'));
              if (!checkboxes.length) {
                return;
              }
              var hasUnchecked = checkboxes.some(function (cb) { return !cb.checked; });
              checkboxes.forEach(function (cb) { cb.checked = hasUnchecked; });
            });
          }

          Array.prototype.slice.call(document.querySelectorAll('[data-event-toggle]')).forEach(function (toggle) {
            toggle.addEventListener('click', function () {
              var wrapper = toggle.closest('.tapin-pa-event');
              if (!wrapper) {
                return;
              }
              var panel = wrapper.querySelector('.tapin-pa-event__panel');
              var expanded = toggle.getAttribute('aria-expanded') === 'true';
              if (expanded) {
                toggle.setAttribute('aria-expanded', 'false');
                wrapper.classList.remove('is-open');
                if (panel) {
                  panel.hidden = true;
                }
              } else {
                toggle.setAttribute('aria-expanded', 'true');
                wrapper.classList.add('is-open');
                if (panel) {
                  panel.hidden = false;
                }
              }
            });
          });

          var searchInput = document.getElementById('tapinPaSearch');
          if (searchInput) {
            searchInput.addEventListener('input', function () {
              var term = searchInput.value.trim().toLowerCase();
              Array.prototype.slice.call(document.querySelectorAll('.tapin-pa-event')).forEach(function (eventEl) {
                var eventMatch = term === '' || (eventEl.getAttribute('data-search') || '').indexOf(term) !== -1;
                var orders = Array.prototype.slice.call(eventEl.querySelectorAll('.tapin-pa-order'));
                var orderMatch = false;
                orders.forEach(function (orderEl) {
                  var matches = term === '' || (orderEl.getAttribute('data-search') || '').indexOf(term) !== -1;
                  orderEl.style.display = matches ? '' : 'none';
                  if (matches) {
                    orderMatch = true;
                  }
                });
                var visible = term === '' ? true : (eventMatch || orderMatch);
                eventEl.style.display = visible ? '' : 'none';
                if (visible && term !== '') {
                  eventEl.classList.add('is-open');
                  var toggle = eventEl.querySelector('[data-event-toggle]');
                  var panel = eventEl.querySelector('.tapin-pa-event__panel');
                  if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                  }
                  if (panel) {
                    panel.hidden = false;
                  }
                }
              });
            });
          }
        })();
        </script>

        <?php

        return ob_get_clean();
    }

    public function exportEvent(): void
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            return;
        }

        $guard = Security::producer();
        if (!$guard->allowed) {
            $message = $guard->message !== '' ? $guard->message : $this->decodeEntities('&#1488;&#1497;&#1503;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#46;');
            status_header(403);
            wp_die(wp_kses_post($message));
        }

        $viewer = $guard->user instanceof WP_User ? $guard->user : wp_get_current_user();
        if (!$this->canDownloadOrders($viewer instanceof WP_User ? $viewer : null)) {
            status_header(403);
            wp_die($this->decodeEntities('&#1488;&#1497;&#1503;&#32;&#1500;&#1495;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#32;&#1489;&#1511;&#1513;&#1493;&#1514;.'));
        }

        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $nonce   = isset($_GET['tapin_pa_export_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['tapin_pa_export_nonce'])) : '';

        if ($eventId <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'tapin_pa_export_event_' . $eventId)) {
            status_header(400);
            wp_die($this->decodeEntities('&#1488;&#1497;&#1490;&#1512;&#32;&#1488;&#1508;&#1512;&#1493;&#1497;&#32;&#1500;&#1488;&#1513;&#1512;.'));
        }

        $producerId = $viewer instanceof WP_User ? (int) $viewer->ID : (int) get_current_user_id();

        $orderSets = $this->resolveProducerOrderIds($producerId);
        $displayIds = $orderSets['display'];

        if ($displayIds === []) {
            status_header(404);
            wp_die($this->decodeEntities('&#1488;&#1497;&#1513;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#46;'));
        }

        $collections   = $this->summarizeOrders($displayIds, $producerId);
        $events        = $this->groupOrdersByEvent($collections['orders']);
        $targetEvent   = null;

        foreach ($events as $event) {
            if ((int) ($event['id'] ?? 0) === $eventId) {
                $targetEvent = $event;
                break;
            }
        }

        if ($targetEvent === null) {
            status_header(404);
            wp_die($this->decodeEntities('&#1488;&#1497;&#1513;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#46;'));
        }

        $rows = $this->buildEventExportRows($targetEvent);
        $this->streamEventExport($targetEvent, $rows);
    }

    /**
     * @return array{relevant: array<int,int>, display: array<int,int>}
     */
    private function resolveProducerOrderIds(int $producerId): array
    {
        $awaitingIds = wc_get_orders([
            'status' => [AwaitingProducerStatus::STATUS_KEY],
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
            'status' => [AwaitingProducerStatus::STATUS_KEY],
            'limit'  => 200,
            'return' => 'ids',
        ]);

        $relevantIds = [];
        foreach ($pendingIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && $this->orderBelongsToProducer($order, $producerId)) {
                $relevantIds[] = (int) $orderId;
            }
        }

        $relevantIds = array_values(array_unique(array_map('intval', $relevantIds)));

        $displayIds = $relevantIds;

        $historyStatuses = [
            'wc-processing',
            'wc-completed',
            'wc-cancelled',
            'wc-refunded',
            'wc-failed',
        ];

        $historyIds = wc_get_orders([
            'status' => $historyStatuses,
            'limit'  => 200,
            'return' => 'ids',
        ]);

        foreach ($historyIds as $orderId) {
            if (in_array($orderId, $displayIds, true)) {
                continue;
            }

            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && $this->orderBelongsToProducer($order, $producerId)) {
                $displayIds[] = (int) $orderId;
            }
        }

        $displayIds = array_values(array_unique(array_map('intval', $displayIds)));

        return [
            'relevant' => $relevantIds,
            'display'  => $displayIds,
        ];
    }

    /**
     * @param array<int,int> $orderIds
     * @return array{orders: array<int,array<string,mixed>>, customer_stats: array<string,array<string,mixed>>}
     */
    private function summarizeOrders(array $orderIds, int $producerId): array
    {
        $orders = [];
        $customerStats = [];

        foreach ($orderIds as $orderId) {
            $order = wc_get_order((int) $orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }

            $summary = $this->buildOrderSummary($order, $producerId);
            if (empty($summary['items'])) {
                continue;
            }

            $orders[] = $summary;

            $email = isset($summary['customer']['email']) ? (string) $summary['customer']['email'] : '';
            $emailKey = strtolower(trim($email));
            if ($emailKey === '') {
                continue;
            }

            if (!isset($customerStats[$emailKey])) {
                $customerStats[$emailKey] = [
                    'name'   => (string) ($summary['customer']['name'] ?? ''),
                    'email'  => $email,
                    'total'  => 0,
                    'orders' => [],
                ];
            }

            $customerStats[$emailKey]['total'] += (int) ($summary['total_quantity'] ?? 0);
            $customerStats[$emailKey]['orders'][] = [
                'order_id' => (int) ($summary['id'] ?? 0),
                'quantity' => (int) ($summary['total_quantity'] ?? 0),
            ];
        }

        return [
            'orders'         => $orders,
            'customer_stats' => $customerStats,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $orders
     * @param array<string,array<int,string>> $customerWarnings
     * @return array<int,array<string,mixed>>
     */
    private function groupOrdersByEvent(array $orders, array $customerWarnings = []): array
    {
        $events = [];

        foreach ($orders as $order) {
            if (empty($order['events'])) {
                continue;
            }

            foreach ((array) $order['events'] as $eventData) {
                $eventId   = (int) ($eventData['event_id'] ?? 0);
                $productId = (int) ($eventData['product_id'] ?? 0);
                $eventKey  = $eventId ?: $productId ?: (int) ($order['id'] ?? 0);
                $key       = (string) $eventKey;

                if (!isset($events[$key])) {
                    $events[$key] = [
                        'id'        => $eventKey,
                        'title'     => (string) ($eventData['title'] ?? ''),
                        'image'     => (string) ($eventData['image'] ?? ''),
                        'permalink' => (string) ($eventData['permalink'] ?? ''),
                        'event_date_ts'    => isset($eventData['event_date_ts']) ? (int) $eventData['event_date_ts'] : 0,
                        'event_date_label' => (string) ($eventData['event_date_label'] ?? ''),
                        'latest_order_ts'  => 0,
                        'counts'    => ['pending' => 0, 'approved' => 0, 'cancelled' => 0],
                        'orders'    => [],
                        'search'    => '',
                    ];

                    if ($events[$key]['title'] === '') {
                        $events[$key]['title'] = $this->decodeEntities('&#1488;&#1497;&#1512;&#1493;&#1506; &#1489;&#1500;&#1514;&#1497; &#1505;&#1493;&#1498;');
                    }
                }

                $statusType = $this->classifyOrderStatus((string) ($order['status'] ?? ''));
                if (isset($events[$key]['counts'][$statusType])) {
                    $events[$key]['counts'][$statusType]++;
                }

                $searchSegments = [
                    '#' . (string) ($order['number'] ?? ''),
                    (string) ($order['customer']['name'] ?? ''),
                    (string) ($order['customer']['email'] ?? ''),
                    (string) ($order['customer']['phone'] ?? ''),
                    (string) ($order['primary_id_number'] ?? ''),
                    (string) ($order['date'] ?? ''),
                    (string) ($order['total'] ?? ''),
                    (string) ($eventData['title'] ?? ''),
                    (string) ($eventData['event_date_label'] ?? ''),
                ];

                $profileUsername = (string) ($order['customer_profile']['username'] ?? '');
                if ($profileUsername !== '') {
                    $searchSegments[] = $profileUsername;
                    $searchSegments[] = '@' . ltrim($profileUsername, '@');
                }

                foreach ((array) ($eventData['lines'] ?? []) as $line) {
                    $searchSegments[] = (string) ($line['name'] ?? '');
                }

                foreach ((array) ($eventData['attendees'] ?? []) as $attendee) {
                    foreach (['full_name', 'email', 'phone', 'id_number', 'gender'] as $field) {
                        if (!empty($attendee[$field])) {
                            $searchSegments[] = (string) $attendee[$field];
                        }
                    }
                }

                $orderSearch = strtolower(wp_strip_all_tags(implode(' ', array_filter($searchSegments))));

                $emailKey = strtolower(trim((string) ($order['customer']['email'] ?? '')));
                $orderWarnings = $emailKey !== '' ? (array) ($customerWarnings[$emailKey] ?? []) : [];

                $events[$key]['orders'][] = [
                    'id'                => (int) ($order['id'] ?? 0),
                    'number'            => (string) ($order['number'] ?? ''),
                    'timestamp'         => (int) ($order['timestamp'] ?? 0),
                    'date'              => (string) ($order['date'] ?? ''),
                    'status'            => (string) ($order['status'] ?? ''),
                    'status_label'      => (string) ($order['status_label'] ?? ''),
                    'status_type'       => $statusType,
                    'total'             => (string) ($order['total'] ?? ''),
                    'quantity'          => (int) ($eventData['quantity'] ?? 0),
                    'lines'             => (array) ($eventData['lines'] ?? []),
                    'attendees'         => (array) ($eventData['attendees'] ?? []),
                    'customer'          => (array) ($order['customer'] ?? []),
                    'customer_profile'  => (array) ($order['customer_profile'] ?? []),
                    'profile'           => (array) ($order['profile'] ?? []),
                    'primary_attendee'  => (array) ($order['primary_attendee'] ?? []),
                    'primary_id_number' => (string) ($order['primary_id_number'] ?? ''),
                    'is_pending'        => (string) ($order['status'] ?? '') === AwaitingProducerStatus::STATUS_SLUG,
                    'warnings'          => $orderWarnings,
                    'search_blob'       => $orderSearch,
                ];

                $events[$key]['search'] .= ' ' . $orderSearch;
            }
        }

        foreach ($events as &$event) {
            $event['search'] = strtolower(trim((string) $event['title'] . ' ' . (string) $event['search']));
            usort($event['orders'], static function (array $a, array $b): int {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            });
            $event['latest_order_ts'] = !empty($event['orders'])
                ? (int) ($event['orders'][0]['timestamp'] ?? 0)
                : (int) ($event['latest_order_ts'] ?? 0);
        }
        unset($event);

        uasort($events, static function (array $a, array $b): int {
            $dateDiff = ($b['event_date_ts'] ?? 0) <=> ($a['event_date_ts'] ?? 0);
            if ($dateDiff !== 0) {
                return $dateDiff;
            }

            $orderDiff = ($b['latest_order_ts'] ?? 0) <=> ($a['latest_order_ts'] ?? 0);
            if ($orderDiff !== 0) {
                return $orderDiff;
            }

            $pendingDiff = ($b['counts']['pending'] ?? 0) <=> ($a['counts']['pending'] ?? 0);
            if ($pendingDiff !== 0) {
                return $pendingDiff;
            }

            $approvedDiff = ($b['counts']['approved'] ?? 0) <=> ($a['counts']['approved'] ?? 0);
            if ($approvedDiff !== 0) {
                return $approvedDiff;
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        return array_values($events);
    }

    /**
     * @param array<string,mixed> $event
     * @return array<int,array<int,string>>
     */
    private function buildEventExportRows(array $event): array
    {
        $rows = [];
        $eventId    = (int) ($event['id'] ?? 0);
        $eventTitle = (string) ($event['title'] ?? '');
        $eventLink  = (string) ($event['permalink'] ?? '');

        foreach ((array) ($event['orders'] ?? []) as $order) {
            $lineItems = array_map(
                function (array $line): string {
                    $name     = $this->cleanExportValue($line['name'] ?? '');
                    $quantity = (int) ($line['quantity'] ?? 0);
                    $total    = $this->cleanExportValue($line['total'] ?? '');

                    $parts = [];
                    if ($name !== '') {
                        $parts[] = $name;
                    }
                    if ($quantity > 0) {
                        $parts[] = '× ' . $quantity;
                    }
                    if ($total !== '') {
                        $parts[] = '(' . $total . ')';
                    }

                    $result = trim(implode(' ', $parts));
                    return $result !== '' ? $result : $total;
                },
                (array) ($order['lines'] ?? [])
            );

            $lineSummary = implode(' | ', array_filter($lineItems));

            $orderBase = [
                $eventId,
                $this->cleanExportValue($eventTitle),
                $this->cleanExportValue($eventLink),
                $this->cleanExportValue('#' . (string) ($order['number'] ?? '')),
                $this->cleanExportValue($order['status_label'] ?? ''),
                $this->cleanExportValue($order['date'] ?? ''),
                $this->cleanExportValue($order['total'] ?? ''),
                (string) (int) ($order['quantity'] ?? 0),
                $lineSummary,
                $this->cleanExportValue($order['customer']['name'] ?? ''),
                $this->cleanExportValue($order['customer']['email'] ?? ''),
                $this->cleanExportValue($order['customer']['phone'] ?? ''),
                $this->cleanExportValue($order['primary_id_number'] ?? ''),
            ];

            $attendees = [];
            if (!empty($order['primary_attendee'])) {
                $attendees[] = ['data' => (array) $order['primary_attendee'], 'primary' => true];
            }
            foreach ((array) ($order['attendees'] ?? []) as $attendee) {
                $attendees[] = ['data' => (array) $attendee, 'primary' => false];
            }

            if ($attendees === []) {
                $rows[] = array_merge($orderBase, ['', '', '', '', '', '', '', '', '', '']);
                continue;
            }

            foreach ($attendees as $attendeeEntry) {
                $attendee = (array) ($attendeeEntry['data'] ?? []);
                $rows[] = array_merge(
                    $orderBase,
                    [
                        $attendeeEntry['primary'] ? 'ראשי' : 'משני',
                        $this->cleanExportValue($attendee['full_name'] ?? ''),
                        $this->cleanExportValue($attendee['email'] ?? ''),
                        $this->cleanExportValue($attendee['phone'] ?? ''),
                        $this->cleanExportValue($attendee['id_number'] ?? ''),
                        $this->cleanExportValue($attendee['birth_date'] ?? ''),
                        $this->cleanExportValue($attendee['gender'] ?? ''),
                        $this->cleanExportValue($attendee['instagram'] ?? ''),
                        $this->cleanExportValue($attendee['facebook'] ?? ''),
                        $this->cleanExportValue($attendee['whatsapp'] ?? ''),
                    ]
                );
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $event
     * @param array<int,array<int,string>> $rows
     */
    private function streamEventExport(array $event, array $rows): void
    {
        $filenameBase = sanitize_title($event['title'] ?? 'tapin-event');
        if ($filenameBase === '') {
            $filenameBase = 'tapin-event';
        }

        $filename = sprintf(
            '%s-%d-%s.csv',
            $filenameBase,
            (int) ($event['id'] ?? 0),
            gmdate('Ymd-His')
        );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            status_header(500);
            wp_die($this->decodeEntities('&#1502;&#1497;&#1508;&#1512;&#32;&#1489;&#1493;&#1514;&#1507;&#32;&#1488;&#1500;&#32;&#1512;&#1513;&#1493;&#1500;.'));
        }

        fwrite($output, "\xEF\xBB\xBF");

        $header = [
            'ID אירוע',
            'שם אירוע',
            'קישור לאירוע',
            'מספר הזמנה',
            'סטטוס הזמנה',
            'תאריך הזמנה',
            'סכום',
            'כמות כרטיסים',
            'שורות הזמנה',
            'שם הלקוח',
            'אימייל הלקוח',
            'טלפון הלקוח',
            'תעודת זהות ראשית',
            'סוג משתתף',
            'שם משתתף',
            'אימייל משתתף',
            'טלפון משתתף',
            'תעודת זהות משתתף',
            'תאריך לידה',
            'מגדר',
            'אינסטגרם',
            'פייסבוק',
            'וואטסאפ',
        ];

        fputcsv($output, $header);
        foreach ($rows as $row) {
            fputcsv($output, array_map([$this, 'cleanExportValue'], $row));
        }

        fclose($output);
        exit;
    }

    /**
     * Determine whether the given user can download the event orders export.
     *
     * @param WP_User|null $user
     */
    private function canDownloadOrders(?WP_User $user): bool
    {
        $allowed = $this->isAdministrator($user);

        /**
         * Filters the permission to download the producer approvals export.
         *
         * @param bool $allowed Whether the user can download the export.
         * @param WP_User|null $user The current viewer.
         */
        return (bool) apply_filters('tapin_events_can_download_producer_orders', $allowed, $user);
    }

    private function cleanExportValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $text = trim((string) $value);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text !== null ? trim($text) : '';
    }

    private function isAdministrator(?WP_User $user): bool
    {
        if (!$user instanceof WP_User) {
            return false;
        }

        if (is_multisite() && is_super_admin((int) $user->ID)) {
            return true;
        }

        return in_array('administrator', (array) $user->roles, true);
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

        if ((int) get_post_field('post_author', $productId) === $producerId) {
            return true;
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId && (int) get_post_field('post_author', $parentId) === $producerId) {
                return true;
            }
        }

        return false;
    }

    private function orderBelongsToProducer(WC_Order $order, int $producerId): bool
    {
        $metaIds = array_filter(array_map('intval', (array) $order->get_meta('_tapin_producer_ids')));
        if ($metaIds && in_array($producerId, $metaIds, true)) {
            return true;
        }

        foreach ($order->get_items('line_item') as $item) {
            if ($this->isProducerLineItem($item, $producerId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildOrderSummary(WC_Order $order, int $producerId): array
    {
        $items             = [];
        $attendeesList     = [];
        $totalQuantity     = 0;
        $eventMap          = [];
        $allAttendeesList  = [];
        $primaryAttendee   = null;

        foreach ($order->get_items('line_item') as $item) {
            if (!$this->isProducerLineItem($item, $producerId)) {
                continue;
            }

            $quantity = (int) $item->get_quantity();
            $items[] = sprintf('%s &#215; %d', esc_html($item->get_name()), $quantity);
            $totalQuantity += $quantity;

            $eventMeta = $this->resolveEventMeta($item);
            $eventKey  = (string) ($eventMeta['event_id'] ?: $eventMeta['product_id'] ?: $item->get_id());

            if (!isset($eventMap[$eventKey])) {
                $eventMap[$eventKey] = array_merge($eventMeta, [
                    'quantity'  => 0,
                    'lines'     => [],
                    'attendees' => [],
                ]);
            }

            $formattedTotal = function_exists('wc_price')
                ? wc_price($item->get_total(), ['currency' => $order->get_currency()])
                : number_format((float) $item->get_total(), 2);

            $eventMap[$eventKey]['quantity'] += $quantity;
            $eventMap[$eventKey]['lines'][] = [
                'name'     => $item->get_name(),
                'quantity' => $quantity,
                'total'    => $formattedTotal,
            ];

            $lineAttendees       = $this->extractAttendees($item);
            $lineDisplayAttendees = [];
            foreach ($lineAttendees as $attendee) {
                $allAttendeesList[] = $attendee;
                if ($primaryAttendee === null) {
                    $primaryAttendee = $attendee;
                    continue;
                }

                $attendeesList[] = $attendee;
                $lineDisplayAttendees[] = $attendee;
            }

            if ($lineDisplayAttendees !== []) {
                $eventMap[$eventKey]['attendees'] = array_merge($eventMap[$eventKey]['attendees'], $lineDisplayAttendees);
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

        $profile['gender'] = AttendeeFields::displayValue('gender', (string) ($profile['gender'] ?? ''));

        $profileUsername = '';
        $profileUrl = '';
        if ($userId) {
            $userObject = get_userdata($userId);
            if ($userObject instanceof \WP_User) {
                $rawSlug = (string) ($userObject->user_nicename ?: $userObject->user_login);
                $slug = sanitize_title($rawSlug);
                if ($slug !== '') {
                    $profileUsername = $slug;
                    $profileUrl = home_url('/user/' . rawurlencode($slug) . '/');
                }
            }
        }

        $status = $order->get_status();
        $statusLabel = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name('wc-' . $status)
            : $status;

        if ($allAttendeesList !== []) {
            $this->logAttendeeAccess($order, $producerId, count($allAttendeesList));
        }

        return [
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'date'           => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' H:i') : '',
            'timestamp'      => $order->get_date_created() ? (int) $order->get_date_created()->getTimestamp() : 0,
            'total'          => wp_strip_all_tags($order->get_formatted_order_total()),
            'total_quantity' => $totalQuantity,
            'items'          => $items,
            'attendees'      => $attendeesList,
            'primary_attendee' => $primaryAttendee ?: [],
            'customer'       => [
                'name'  => trim($order->get_formatted_billing_full_name()) ?: $order->get_billing_first_name() ?: ($order->get_user() ? $order->get_user()->display_name : $this->decodeEntities('&#1500;&#1511;&#1493;&#1495;&#32;&#1488;&#1504;&#1493;&#1504;&#1497;&#1502;&#1497;')),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'customer_profile'   => [
                'username' => $profileUsername,
                'url'      => $profileUrl,
                'user_id'  => $userId,
            ],
            'profile'             => $profile,
            'primary_id_number'   => $this->findPrimaryIdNumber($allAttendeesList),
            'status'              => $status,
            'status_label'        => $statusLabel,
            'is_approved'         => (bool) $order->get_meta('_tapin_producer_approved'),
            'events'              => array_values($eventMap),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveEventMeta(WC_Order_Item_Product $item): array
    {
        $product   = $item->get_product();
        $productId = $product instanceof WC_Product ? (int) $product->get_id() : (int) $item->get_product_id();
        $eventId   = 0;
        $title     = '';

        if ($product instanceof WC_Product) {
            if ($product->is_type('variation')) {
                $eventId = $product->get_parent_id() ?: $productId;
            } else {
                $eventId = $productId;
            }
            $title = $product->get_name();
        } else {
            $eventId = $productId;
            $title   = $item->get_name();
        }

        if ($title === '') {
            $title = $item->get_name();
        }

        if ($product instanceof WC_Product && $product->is_type('variation')) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                $parent = wc_get_product($parentId);
                if ($parent instanceof WC_Product) {
                    $parentName = $parent->get_name();
                    if ($parentName !== '') {
                        $title = $parentName;
                    }
                }
            }
        }

        $targetId  = $eventId ?: $productId;
        $permalink = $targetId ? (string) get_permalink($targetId) : '';
        $image     = $targetId ? (string) get_the_post_thumbnail_url($targetId, 'medium') : '';

        if ($image === '' && $productId) {
            $image = (string) get_the_post_thumbnail_url($productId, 'medium');
        }

        if ($image === '' && function_exists('wc_placeholder_img_src')) {
            $image = (string) wc_placeholder_img_src();
        }

        $eventTimestamp = $targetId ? Time::productEventTs((int) $targetId) : 0;
        $eventDateLabel = '';
        if ($eventTimestamp > 0) {
            $eventDateLabel = wp_date(
                get_option('date_format') . ' H:i',
                $eventTimestamp,
                wp_timezone()
            );
        }

        return [
            'event_id'   => $eventId ?: $productId ?: 0,
            'product_id' => $productId ?: 0,
            'title'      => $title,
            'permalink'  => $permalink,
            'image'      => $image,
            'event_date_ts'    => $eventTimestamp,
            'event_date_label' => $eventDateLabel,
        ];
    }

    private function classifyOrderStatus(string $status): string
    {
        $normalized = strtolower($status);

        if (in_array($normalized, [AwaitingProducerStatus::STATUS_SLUG, 'pending', 'on-hold'], true)) {
            return 'pending';
        }

        if (in_array($normalized, ['cancelled', 'refunded', 'failed'], true)) {
            return 'cancelled';
        }

        return 'approved';
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractAttendees(WC_Order_Item_Product $item): array
    {
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded === []) {
            $legacy = (string) $item->get_meta('Tapin Attendees', true);
            if ($legacy !== '') {
                $decoded = AttendeeSecureStorage::decrypt($legacy);
            }
        }

        if ($decoded !== []) {
            return array_map([$this, 'normalizeAttendee'], $decoded);
        }

        $order = $item->get_order();
        if ($order instanceof WC_Order) {
            $aggregate = $order->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                return array_map([$this, 'normalizeAttendee'], $aggregateDecoded);
            }
        }

        $fallback = [];
        $summaryKeys = AttendeeFields::summaryKeys();

        foreach ($item->get_formatted_meta_data('') as $meta) {
            $label = (string) $meta->key;
            if (
                strpos($label, "\u{05D4}\u{05DE}\u{05E9}\u{05EA}\u{05EA}\u{05E3}") === 0
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
     * @param array<int,array<string,mixed>> $attendees
     */
    private function findPrimaryIdNumber(array $attendees): string
    {
        foreach ($attendees as $attendee) {
            $raw = isset($attendee['id_number']) ? (string) $attendee['id_number'] : '';
            $normalized = AttendeeFields::displayValue('id_number', $raw);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
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
            if ($sanitized !== '') {
                $normalized[$key] = $sanitized;
                continue;
            }

            $display = AttendeeFields::displayValue($key, $raw);
            $normalized[$key] = $display !== '' ? $display : sanitize_text_field($raw);
        }
        return $normalized;
    }

    private function logAttendeeAccess(WC_Order $order, int $viewerId, int $count): void
    {
        $orderId = (int) $order->get_id();
        if ($orderId && isset($this->auditedOrders[$orderId])) {
            return;
        }

        if ($orderId) {
            $this->auditedOrders[$orderId] = true;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $user   = get_userdata($viewerId);
        $username = $user instanceof \WP_User ? $user->user_login : 'user-' . $viewerId;
        $display  = $user instanceof \WP_User ? $user->display_name : '';
        $label    = $display !== '' ? $display : $username;

        $message = sprintf(
            'Attendee data viewed by %s (ID %d) for order #%s (%d attendees)',
            $label,
            $viewerId,
            $order->get_order_number(),
            $count
        );

        $logger->info($message, ['source' => 'tapin-attendees-audit']);

        do_action('tapin_events_attendee_audit_log', $orderId, $viewerId, $count, time());
    }

    /**
     * @param array<string,array<string,mixed>> $stats
     * @return array<string,array<int,string>>
     */
    private function buildWarnings(array $stats): array
    {
        $warnings = [];

        foreach ($stats as $emailKey => $customer) {
            $largeOrders = array_filter(
                $customer['orders'],
                static fn($entry) => $entry['quantity'] >= self::LARGE_ORDER_THRESHOLD
            );

            if ($customer['total'] >= self::CUSTOMER_TOTAL_THRESHOLD || count($largeOrders) >= 2) {
                $name  = esc_html($customer['name'] ?: $customer['email']);
                $email = esc_html($customer['email']);
                $key   = strtolower(trim(is_string($emailKey) ? $emailKey : (string) $emailKey));
                if ($key === '') {
                    continue;
                }

                $warnings[$key][] = sprintf('&#1513;&#1497;&#1501;&#32;&#1500;&#1489;: %1$s (%2$s) &#1512;&#1499;&#1513;&#32;%3$d&#32;&#1499;&#1512;&#1496;&#1497;&#1505;&#1497;&#1501;&#32;&#1489;&#1505;&#1495;&#32;&#1499;&#1493;&#1500;.', $name, $email, (int) $customer['total']);
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
        $profile['gender']    = AttendeeFields::displayValue('gender', $profile['gender']);

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




