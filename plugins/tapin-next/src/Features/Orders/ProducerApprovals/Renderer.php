<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\ProducerApprovals\Utils\PhoneUrl;
use Tapin\Events\Features\Orders\ProducerApprovals\Utils\SocialUrl;
use Tapin\Events\UI\Components\CounterBadge;

final class Renderer
{
    /**
     * @param array<string,mixed> $view
     */
    public function render(array $view, string $noticeHtml = ''): string
    {
        $events = (array) ($view['events'] ?? []);
        $canDownloadExport = (bool) ($view['can_download_export'] ?? false);
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $actionUrl = $requestUri !== '' ? $requestUri : get_permalink();

        ob_start();
        ?>
        <div class="tapin-pa">
          <?php echo $noticeHtml; ?>
          <h3><?php echo esc_html__('הזמנות ממתינות לאישור', 'tapin'); ?></h3>

          <form method="post" id="tapinBulkForm" class="tapin-pa__form" action="<?php echo esc_url((string) $actionUrl); ?>">
            <?php wp_nonce_field('tapin_pa_bulk', 'tapin_pa_bulk_nonce'); ?>
            <div class="tapin-pa__controls">
              <div class="tapin-pa__search">
                <input type="search" id="tapinPaSearch" class="tapin-pa__search-input" placeholder="<?php echo esc_attr__('חיפוש לפי לקוח או אירוע...', 'tapin'); ?>">
              </div>
              <div class="tapin-pa__buttons">
                <button class="btn btn-ghost" type="button" id="tapinPaSelectAll"><?php echo esc_html__('בחר הכל', 'tapin'); ?></button>
                <button class="btn btn-primary" type="submit" name="bulk_approve"><?php echo esc_html__('אשר נבחרות', 'tapin'); ?></button>
                <button class="btn btn-ghost" type="button" id="tapinApproveAll"><?php echo esc_html__('אשר הכל', 'tapin'); ?></button>
                <button class="btn btn-danger" type="submit" name="bulk_cancel" onclick="return confirm('<?php echo esc_js(__('לבטל את ההזמנות שנבחרו?', 'tapin')); ?>')"><?php echo esc_html__('בטל ההזמנות שנבחרו', 'tapin'); ?></button>
              </div>
            </div>

            <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">

            <?php if ($events): ?>
              <div class="tapin-pa__events" id="tapinPaEvents">
                <?php foreach ($events as $index => $event): ?>
                  <?php
                  $pendingCount = (int) ($event['counts']['pending'] ?? 0);
                  $isOpen = false;
                  ?>
                  <div class="tapin-pa-event<?php echo $isOpen ? ' is-open' : ''; ?>" data-search="<?php echo esc_attr((string) ($event['search'] ?? '')); ?>">
                    <button class="tapin-pa-event__header" type="button" data-event-toggle aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                      <div class="tapin-pa-event__summary">
                        <?php if (!empty($event['image'])): ?>
                          <img class="tapin-pa-event__image" src="<?php echo esc_url((string) $event['image']); ?>" alt="">
                        <?php else: ?>
                          <div class="tapin-pa-event__image" aria-hidden="true"></div>
                        <?php endif; ?>
                        <div class="tapin-pa-event__text">
                          <h4>
                            <?php if (!empty($event['permalink'])): ?>
                              <a href="<?php echo esc_url((string) $event['permalink']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $event['title']); ?></a>
                            <?php else: ?>
                              <?php echo esc_html((string) $event['title']); ?>
                            <?php endif; ?>
                          </h4>
                          <div class="tapin-pa-event__stats">
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--pending"><?php echo esc_html__('ממתינים', 'tapin'); ?>: <?php echo $pendingCount; ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--partial"><?php echo esc_html__('אושר חלקית', 'tapin'); ?>: <?php echo (int) ($event['counts']['partial'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--approved"><?php echo esc_html__('מאושרים', 'tapin'); ?>: <?php echo (int) ($event['counts']['approved'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--cancelled"><?php echo esc_html__('מבוטלים', 'tapin'); ?>: <?php echo (int) ($event['counts']['cancelled'] ?? 0); ?></span>
                          </div>
                        </div>
                      </div>
                      <?php echo CounterBadge::render($pendingCount, ['class' => 'tapin-pa-event__indicator']); ?>
                      <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="tapin-pa-event__panel"<?php echo $isOpen ? '' : ' hidden'; ?>>
                      <?php if (!empty($event['event_date_label'])): ?>
                        <?php $eventDateHeading = __('תאריך האירוע', 'tapin'); ?>
                        <h4 class="tapin-pa-event__panel-heading">
                          <?php echo esc_html($eventDateHeading . ': ' . (string) $event['event_date_label']); ?>
                        </h4>
                      <?php endif; ?>
                      <?php
                      $capacityMeta  = (array) ($event['ticket_capacity'] ?? []);
                      $capacityTypes = (array) ($capacityMeta['types'] ?? []);
                      ?>
                      <?php if ($capacityTypes !== []): ?>
                        <div class="tapin-pa-capacity">
                          <h5 class="tapin-pa-capacity__title"><?php echo esc_html__('זמינות כרטיסים', 'tapin'); ?></h5>
                          <div class="tapin-pa-capacity__grid">
                            <?php foreach ($capacityTypes as $typeMeta): ?>
                              <?php
                              $cap        = (int) ($typeMeta['capacity'] ?? 0);
                              $sold       = (int) ($typeMeta['sold'] ?? 0);
                              $remaining  = (int) ($typeMeta['remaining'] ?? -1);
                              $isUnlimited = !empty($typeMeta['unlimited']);
                              $soldOut    = !empty($typeMeta['sold_out']);
                              ?>
                              <div class="tapin-pa-capacity__card<?php echo $soldOut ? ' tapin-pa-capacity__card--soldout' : ''; ?>">
                                <div class="tapin-pa-capacity__card-header">
                                  <span class="tapin-pa-capacity__label"><?php echo esc_html((string) ($typeMeta['label'] ?? '')); ?></span>
                                  <?php if ($soldOut): ?>
                                    <span class="tapin-pa-capacity__badge"><?php echo esc_html__('אזלו', 'tapin'); ?></span>
                                  <?php endif; ?>
                                </div>
                                <div class="tapin-pa-capacity__stats">
                                  <span><?php echo esc_html($isUnlimited ? __('ללא הגבלה', 'tapin') : sprintf(__('קיבולת: %d', 'tapin'), $cap)); ?></span>
                                  <span><?php echo esc_html(sprintf(__('מאושרים עד עכשיו: %d', 'tapin'), $sold)); ?></span>
                                  <?php if (!$isUnlimited): ?>
                                    <span><?php echo esc_html(sprintf(__('נותר פנוי: %d', 'tapin'), max(0, $remaining))); ?></span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
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
                            <?php echo esc_html__('הורדת בקשות', 'tapin'); ?>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($event['orders'])): ?>
                        <?php foreach ($event['orders'] as $orderIndex => $orderData): ?>
                          <?php
                          $statusType = (string) ($orderData['status_type'] ?? '');
                          $statusSlug = (string) ($orderData['status'] ?? '');
                          $statusLabel = trim((string) ($orderData['status_label'] ?? ''));
                          if ($statusLabel === '') {
                              $statusLabel = $statusType === 'pending'
                                  ? __('ממתינים', 'tapin')
                                  : ($statusType === 'approved'
                                      ? __('אושר', 'tapin')
                                      : __('מבוטל', 'tapin'));
                          }
                          $statusClass = 'tapin-pa-order--' . sanitize_html_class($statusType);
                          if ($statusSlug === \Tapin\Events\Features\Orders\PartiallyApprovedStatus::STATUS_SLUG) {
                              $statusClass .= ' tapin-pa-order--partial';
                          }
                          $altClass = ((int) $orderIndex % 2 === 1) ? ' tapin-pa-order--alt' : '';
                          $approvedMap = (array) ($orderData['approved_attendee_map'] ?? []);
                          $orderId = (int) ($orderData['id'] ?? 0);
                          ?>
                          <article class="tapin-pa-order <?php echo esc_attr($statusClass); ?><?php echo $altClass; ?>" data-search="<?php echo esc_attr((string) ($orderData['search_blob'] ?? '')); ?>">
                            <?php if ($orderId > 0): ?>
                              <input type="hidden" name="order_ids[]" value="<?php echo esc_attr((string) $orderId); ?>">
                            <?php endif; ?>
                            <header class="tapin-pa-order__header">
                              <div class="tapin-pa-order__left">
                                <div>
                                  <div><strong><?php echo esc_html('#' . (string) ($orderData['number'] ?? '')); ?></strong></div>
                                  <div class="tapin-pa-order__meta">
                                    <?php if (!empty($orderData['date'])): ?><span><?php echo esc_html((string) $orderData['date']); ?></span><?php endif; ?>
                                    <?php if (!empty($orderData['total'])): ?><span><?php echo esc_html((string) $orderData['total']); ?></span><?php endif; ?>
                                    <?php if ((int) ($orderData['quantity'] ?? 0) > 0): ?><span><?php echo esc_html(((int) $orderData['quantity']) . 'x'); ?></span><?php endif; ?>
                                  </div>
                                </div>
                              </div>
                              <span class="tapin-pa-order__status"><?php echo esc_html($statusLabel); ?></span>
                            </header>
                            <div class="tapin-pa-order__body">
                              <?php if (!empty($orderData['warnings'])): ?>
                                <div class="tapin-pa-order__warnings">
                                  <?php foreach ((array) $orderData['warnings'] as $warning): ?>
                                    <div class="tapin-pa__warning"><?php echo wp_kses_post((string) $warning); ?></div>
                                  <?php endforeach; ?>
                                </div>
                              <?php endif; ?>
                              <?php
                              $contactRows = [];
                              $nameValue = trim((string) ($orderData['customer']['name'] ?? ''));
                              if ($nameValue !== '') {
                                  $contactRows[] = [
                                      'label' => __('שם הלקוח', 'tapin'),
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
                                  $phoneMeta = PhoneUrl::normalizePhone($customerPhone);
                                  $contactRows[] = [
                                      'label' => __('טלפון', 'tapin'),
                                      'value' => $customerPhone,
                                      'type'  => $phoneMeta['digits'] !== '' ? 'phone' : 'text',
                                      'href'  => $phoneMeta['href'] !== '' ? $phoneMeta['href'] : $phoneMeta['digits'],
                                  ];
                              }

                              $saleTypeLabel = __('סוג מכירה', 'tapin');
                              $saleTypeRaw = (string) ($orderData['sale_type'] ?? 'organic');
                              $saleTypeValue = $saleTypeRaw === 'producer_link'
                                  ? __('לינק מפיק', 'tapin')
                                  : __('אורגני', 'tapin');
                              $contactRows[] = [
                                  'label' => $saleTypeLabel,
                                  'value' => $saleTypeValue,
                                  'type'  => 'text',
                                  'href'  => '',
                              ];

                              if (!empty($orderData['primary_id_number'])) {
                                  $contactRows[] = [
                                      'label' => __('תעודת זהות', 'tapin'),
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
                                      'label' => __('פרופיל בTapin', 'tapin'),
                                      'value' => '@' . ltrim($profileUsernameMeta, '@'),
                                      'type'  => $profileUrlMeta !== '' ? 'link' : 'text',
                                      'href'  => $profileUrlMeta,
                                  ];
                              }

                              $profileFieldMap = [
                                  __('שם פרטי', 'tapin') => $orderData['profile']['first_name'] ?? '',
                                  __('שם משפחה', 'tapin') => $orderData['profile']['last_name'] ?? '',
                                  __('תאריך לידה', 'tapin') => $orderData['profile']['birthdate'] ?? '',
                                  __('מגדר', 'tapin') => $orderData['profile']['gender'] ?? '',
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
                                          $insta = SocialUrl::normalizeInstagram($value);
                                          if ($insta['display'] !== '') {
                                              $displayValue = $insta['display'];
                                          }
                                          if ($insta['url'] !== '') {
                                              $candidate = $insta['url'];
                                          }
                                      }
                                      if ($candidate !== '' && !preg_match('#^https?://#i', $candidate)) {
                                          $candidate = 'https://' . ltrim($candidate, '/');
                                      }
                                      $isValidUrl = filter_var($candidate, FILTER_VALIDATE_URL);
                                      if ($isValidUrl) {
                                          $type = 'link';
                                          $href = $candidate;
                                      }
                                  } elseif ($label === 'WhatsApp') {
                                      $waUrl = PhoneUrl::whatsappUrl($value);
                                      if ($waUrl !== '') {
                                          $type = 'link';
                                          $href = $waUrl;
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
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html__('פרטי לקוח', 'tapin'); ?></h5>
                                  <div class="tapin-pa-order__grid">
                                    <?php foreach ($contactRows as $row): ?>
                                      <div class="tapin-pa-order__card">
                                        <span class="tapin-pa-order__label"><?php echo esc_html((string) $row['label']); ?></span>
                                        <span class="tapin-pa-order__value">
                                          <?php if ($row['type'] === 'email'): ?>
                                            <a href="mailto:<?php echo esc_attr((string) $row['href']); ?>"><?php echo esc_html((string) $row['value']); ?></a>
                                          <?php elseif ($row['type'] === 'phone' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="tel:<?php echo esc_attr((string) $row['href']); ?>"><?php echo esc_html((string) $row['value']); ?></a>
                                          <?php elseif ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="<?php echo esc_url((string) $row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $row['value']); ?></a>
                                          <?php else: ?>
                                            <?php echo esc_html((string) $row['value']); ?>
                                          <?php endif; ?>
                                        </span>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <?php if ($profileRows): ?>
                                <div class="tapin-pa-order__section">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html__('פרטי פרופיל', 'tapin'); ?></h5>
                                  <div class="tapin-pa-order__grid">
                                    <?php foreach ($profileRows as $row): ?>
                                      <div class="tapin-pa-order__card">
                                        <span class="tapin-pa-order__label"><?php echo esc_html((string) $row['label']); ?></span>
                                        <span class="tapin-pa-order__value">
                                          <?php if ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                            <a href="<?php echo esc_url((string) $row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $row['value']); ?></a>
                                          <?php else: ?>
                                            <?php echo esc_html((string) $row['value']); ?>
                                          <?php endif; ?>
                                        </span>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <?php if (!empty($orderData['lines'])): ?>
                                <ul class="tapin-pa-order__lines">
                                  <?php foreach ((array) $orderData['lines'] as $line): ?>
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
                                    $lineTypes = array_filter(array_map('trim', (array) ($line['ticket_types'] ?? [])));
                                    if ($lineTypes !== []) {
                                        $lineText .= ' (' . implode(' / ', $lineTypes) . ')';
                                    }
                                    ?>
                                    <li><?php echo esc_html($lineText); ?></li>
                                  <?php endforeach; ?>
                                </ul>
                              <?php endif; ?>

                              <?php
                              $primary = (array) ($orderData['primary_attendee'] ?? []);
                              $others = (array) ($orderData['attendees'] ?? []);
                              if ($primary !== []) {
                                  array_unshift($others, $primary);
                              }
                              $uiAttendees = $others;
                              ?>
                              <?php if ($uiAttendees !== []): ?>
                                <div class="tapin-pa-order__section tapin-pa-attendees">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html__('מוזמנים', 'tapin'); ?></h5>
                                  <div class="tapin-pa-attendees__grid">
                                    <?php foreach ($uiAttendees as $attendee): ?>
                                      <?php
                                      $itemId = isset($attendee['item_id']) ? (int) $attendee['item_id'] : 0;
                                      $attendeeIndex = isset($attendee['attendee_index']) ? (int) $attendee['attendee_index'] : -1;
                                      $hasPointer = $orderId > 0 && $itemId > 0 && $attendeeIndex >= 0;
                                      $checkboxEnabled = !empty($orderData['is_pending'])
                                          && empty($orderData['is_partial'])
                                          && $hasPointer;
                                      $checkboxName = sprintf('attendee_approve[%d][%d][]', $orderId, max(0, $itemId));
                                      $checkboxValue = $attendeeIndex;
                                      $itemApprovedList = isset($approvedMap[$itemId]) ? (array) $approvedMap[$itemId] : [];
                                      $hasSavedSelection = array_key_exists($itemId, $approvedMap);
                                      $isApprovedAttendee = in_array($attendeeIndex, $itemApprovedList, true)
                                          || !empty($attendee['is_producer_approved']);
                                      $shouldCheck = $checkboxEnabled && (!$hasSavedSelection || $isApprovedAttendee);
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
                                                  'label' => __('טלפון', 'tapin'),
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
                                                  'label' => __('תעודת זהות', 'tapin'),
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
                                                  'label' => __('תאריך לידה', 'tapin'),
                                                  'value' => $birthDate,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['gender'])) {
                                          $genderRaw = trim((string) $attendee['gender']);
                                          if ($genderRaw !== '') {
                                              $genderDisplay = \Tapin\Events\Support\AttendeeFields::displayValue('gender', $genderRaw);
                                              if ($genderDisplay === '') {
                                                  $genderDisplay = $genderRaw;
                                              }
                                              $attendeeRows[] = [
                                                  'label' => __('מגדר', 'tapin'),
                                                  'value' => $genderDisplay,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['instagram'])) {
                                          $instagramRaw = trim((string) $attendee['instagram']);
                                          if ($instagramRaw !== '') {
                                              $insta = SocialUrl::normalizeInstagram($instagramRaw);
                                              $candidate = $insta['url'] !== '' ? $insta['url'] : $instagramRaw;
                                              $display = $insta['display'] !== '' ? $insta['display'] : $instagramRaw;
                                              if ($candidate !== '' && !preg_match('#^https?://#i', $candidate)) {
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
                                              $waUrl = PhoneUrl::whatsappUrl($whatsappRaw);
                                              if ($waUrl !== '') {
                                                  $attendeeRows[] = [
                                                      'label' => 'WhatsApp',
                                                      'value' => $whatsappRaw,
                                                      'type'  => 'link',
                                                      'href'  => $waUrl,
                                                  ];
                                              }
                                          }
                                      }
                                      ?>

                                      <div class="tapin-pa-attendee<?php echo $isApprovedAttendee ? ' tapin-pa-attendee--approved' : ''; ?>">
                                        <div class="tapin-pa-attendee__header">
                                          <div class="tapin-pa-attendee__selector">
                                            <input
                                              type="checkbox"
                                              class="tapin-pa-attendee__checkbox"
                                              <?php if ($checkboxEnabled): ?>
                                                name="<?php echo esc_attr($checkboxName); ?>"
                                                value="<?php echo esc_attr((string) $checkboxValue); ?>"
                                                <?php echo $shouldCheck ? ' checked' : ''; ?>
                                                data-pending="1"
                                              <?php else: ?>
                                                disabled
                                                data-pending="0"
                                              <?php endif; ?>
                                            >
                                            <h6 class="tapin-pa-attendee__title">
                                              <?php
                                              $displayName = trim((string) ($attendee['full_name'] ?? ''));
                                              echo esc_html($displayName !== '' ? $displayName : __('אורח', 'tapin'));
                                              ?>
                                            </h6>
                                          </div>
                                          <?php if ($isApprovedAttendee): ?>
                                            <span class="tapin-pa-attendee__status tapin-pa-attendee__status--approved">
                                              <?php echo esc_html__('מאושר', 'tapin'); ?>
                                            </span>
                                          <?php endif; ?>
                                          <span class="tapin-pa-attendee__badge"><?php echo esc_html__('מוזמן/ת', 'tapin'); ?></span>
                                        </div>
                                        <?php if ($attendeeRows): ?>
                                          <ul class="tapin-pa-attendee__list">
                                            <?php foreach ($attendeeRows as $row): ?>
                                              <li>
                                                <span class="tapin-pa-attendee__label"><?php echo esc_html((string) $row['label']); ?></span>
                                                <span class="tapin-pa-attendee__value">
                                                  <?php if ($row['type'] === 'email'): ?>
                                                    <a href="mailto:<?php echo esc_attr((string) $row['href']); ?>"><?php echo esc_html((string) $row['value']); ?></a>
                                                  <?php elseif ($row['type'] === 'phone' && ($row['href'] ?? '') !== ''): ?>
                                                    <a href="tel:<?php echo esc_attr((string) $row['href']); ?>"><?php echo esc_html((string) $row['value']); ?></a>
                                                  <?php elseif ($row['type'] === 'link' && ($row['href'] ?? '') !== ''): ?>
                                                    <a href="<?php echo esc_url((string) $row['href']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $row['value']); ?></a>
                                                  <?php else: ?>
                                                    <?php echo esc_html((string) $row['value']); ?>
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
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="tapin-pa-empty"><?php echo esc_html__('אין אישורים להצגה כרגע.', 'tapin'); ?></div>
            <?php endif; ?>
          </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
