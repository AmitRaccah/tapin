<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class Renderer
{
    /**
     * @param array<string,mixed> $view
     */
    public function render(array $view, string $noticeHtml = ''): string
    {
        $events = (array) ($view['events'] ?? []);
        $canDownloadExport = (bool) ($view['can_download_export'] ?? false);
        $now = time();

        ob_start();
        ?>
        <div class="tapin-pa">
          <?php echo $noticeHtml; ?>
          <h3><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1502;&#1502;&#1514;&#1497;&#1504;&#1493;&#1514;&#32;&#1500;&#1488;&#1497;&#1513;&#1493;&#1512;')); ?></h3>

          <form method="post" id="tapinBulkForm" class="tapin-pa__form">
            <?php wp_nonce_field('tapin_pa_bulk', 'tapin_pa_bulk_nonce'); ?>
            <div class="tapin-pa__controls">
              <div class="tapin-pa__search">
                <input type="search" id="tapinPaSearch" class="tapin-pa__search-input" placeholder="<?php echo esc_attr(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1495;&#1497;&#1508;&#1493;&#1513;&#32;&#1500;&#1508;&#1497;&#32;&#1500;&#1511;&#1493;&#1495;&#32;&#1488;&#1493;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#46;&#46;&#46;')); ?>">
              </div>
              <div class="tapin-pa__buttons">
                <button class="btn btn-ghost" type="button" id="tapinPaSelectAll"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1489;&#1495;&#1512;&#32;&#1492;&#1499;&#1500;')); ?></button>
                <button class="btn btn-secondary" type="button" id="tapinPaPartialSave"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1513;&#1502;&#1493;&#1512;&#32;&#1488;&#1497;&#1513;&#1493;&#1512;&#1497;&#1501;&#32;&#1492;&#1495;&#1500;&#1511;&#1497;&#1501;')); ?></button>
                <button class="btn btn-primary" type="submit" name="bulk_approve"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1513;&#1512;&#32;&#1504;&#1489;&#1495;&#1512;&#1493;&#1514;')); ?></button>
                <button class="btn btn-ghost" type="button" id="tapinApproveAll"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1513;&#1512;&#32;&#1492;&#1499;&#1500;')); ?></button>
                <button class="btn btn-danger" type="submit" name="bulk_cancel" onclick="return confirm('<?php echo esc_js(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1500;&#1489;&#1496;&#1500;&#32;&#1488;&#1514;&#32;&#1492;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1513;&#1504;&#1489;&#1495;&#1512;&#1493;&#63;')); ?>')"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1489;&#1496;&#1500;&#32;&#1492;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1513;&#1504;&#1489;&#1495;&#1512;&#1493;')); ?></button>
              </div>
            </div>

            <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">

            <?php if ($events): ?>
              <div class="tapin-pa__events" id="tapinPaEvents">
                <?php foreach ($events as $index => $event): ?>
                  <?php $isOpen = ((int) ($event['counts']['pending'] ?? 0) > 0) || $index === 0; ?>
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
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--pending"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1502;&#1514;&#1497;&#1504;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['pending'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--partial"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1513;&#1493;&#1512;&#1497;&#1501;&#32;&#1492;&#1495;&#1500;&#1511;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['partial'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--approved"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1488;&#1493;&#1513;&#1512;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['approved'] ?? 0); ?></span>
                            <span class="tapin-pa-event__badge tapin-pa-event__badge--cancelled"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1489;&#1493;&#1496;&#1500;&#1497;&#1501;')); ?>: <?php echo (int) ($event['counts']['cancelled'] ?? 0); ?></span>
                          </div>
                        </div>
                      </div>
                      <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="tapin-pa-event__panel"<?php echo $isOpen ? '' : ' hidden'; ?>>
                      <?php if (!empty($event['event_date_label'])): ?>
                        <?php $eventDateHeading = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1492;&#1488;&#1497;&#1512;&#1493;&#1506;'); ?>
                        <h4 class="tapin-pa-event__panel-heading">
                          <?php echo esc_html($eventDateHeading . ': ' . (string) $event['event_date_label']); ?>
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
                            <?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1492;&#1493;&#1512;&#1491;&#1514;&#32;&#1489;&#1511;&#1513;&#1493;&#1514;')); ?>
                          </a>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($event['orders'])): ?>
                        <?php foreach ($event['orders'] as $orderIndex => $orderData): ?>
                          <?php
                          $statusType = (string) ($orderData['status_type'] ?? '');
                          switch ($statusType) {
                              case 'pending':
                                  $statusLabel = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1502;&#1514;&#1497;&#1504;&#1497;&#1501;');
                                  break;
                              case 'partial':
                                  $statusLabel = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1513;&#1512;&#32;&#1492;&#1495;&#1500;&#1511;&#1497;&#1514;');
                                  break;
                              case 'approved':
                                  $statusLabel = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1493;&#1513;&#1512;');
                                  break;
                              default:
                                  $statusLabel = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1489;&#1493;&#1496;&#1500;');
                                  break;
                          }
                          $statusClass = 'tapin-pa-order--' . sanitize_html_class($statusType);
                          $altClass = ((int) $orderIndex % 2 === 1) ? ' tapin-pa-order--alt' : '';
                          ?>
                          <article class="tapin-pa-order <?php echo esc_attr($statusClass); ?><?php echo $altClass; ?>" data-search="<?php echo esc_attr((string) ($orderData['search_blob'] ?? '')); ?>">
                            <header class="tapin-pa-order__header">
                              <div class="tapin-pa-order__left">
                                <?php if (!empty($orderData['is_pending'])): ?>
                                  <input class="tapin-pa-order__checkbox" type="checkbox" name="order_ids[]" value="<?php echo (int) ($orderData['id'] ?? 0); ?>" data-pending="1">
                                <?php else: ?>
                                  <input class="tapin-pa-order__checkbox" type="checkbox" disabled title="Already processed">
                                <?php endif; ?>
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
                              <?php if (!empty($orderData['lines'])): ?>
                                <?php foreach ((array) $orderData['lines'] as $lineMeta): ?>
                                  <?php
                                  $lineItemId = isset($lineMeta['item_id']) ? (int) $lineMeta['item_id'] : 0;
                                  if ($lineItemId <= 0) {
                                      continue;
                                  }
                                  ?>
                                  <input type="hidden" name="approve_attendee[<?php echo (int) ($orderData['id'] ?? 0); ?>][<?php echo $lineItemId; ?>][_tapin_presence]" value="1">
                                <?php endforeach; ?>
                              <?php endif; ?>
                              <?php
                              $contactRows = [];
                              $nameValue = trim((string) ($orderData['customer']['name'] ?? ''));
                              if ($nameValue !== '') {
                                  $contactRows[] = [
                                      'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1513;&#1502;&#32;&#1492;&#1500;&#1511;&#1493;&#1495;'),
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
                                      'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1496;&#1500;&#1508;&#1493;&#1503;'),
                                      'value' => $customerPhone,
                                      'type'  => $digitsOnly !== '' ? 'phone' : 'text',
                                      'href'  => $telHref !== '' ? $telHref : $digitsOnly,
                                  ];
                              }

                              if (!empty($orderData['primary_id_number'])) {
                                  $contactRows[] = [
                                      'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1514;&#1506;&#1493;&#1491;&#1514;&#32;&#1494;&#1492;&#1493;&#1514;'),
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
                                      'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1508;&#1512;&#1493;&#1508;&#1497;&#1500;&#32;&#1489;&#84;&#97;&#112;&#105;&#110;'),
                                      'value' => '@' . ltrim($profileUsernameMeta, '@'),
                                      'type'  => $profileUrlMeta !== '' ? 'link' : 'text',
                                      'href'  => $profileUrlMeta,
                                  ];
                              }

                              $profileFieldMap = [
                                  \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1513;&#1501;&#32;&#1508;&#1512;&#1496;&#1497;') => $orderData['profile']['first_name'] ?? '',
                                  \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1513;&#1501;&#32;&#1502;&#1513;&#1508;&#1495;&#1492;') => $orderData['profile']['last_name'] ?? '',
                                  \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1500;&#1497;&#1491;&#1492;') => $orderData['profile']['birthdate'] ?? '',
                                  \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1490;&#1491;&#1512;') => $orderData['profile']['gender'] ?? '',
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
                                          $handle = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\SocialUrl::trimHandle($value);
                                          if ($handle !== '') {
                                              $candidate = 'https://instagram.com/' . ltrim($handle, '@');
                                              $displayValue = $handle;
                                          } else {
                                              $altHandle = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\SocialUrl::trimHandle('@' . ltrim($value, '@/'));
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
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1508;&#1512;&#1496;&#1497;&#32;&#1500;&#1511;&#1493;&#1495;')); ?></h5>
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
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1508;&#1512;&#1496;&#1497;&#32;&#1508;&#1512;&#1493;&#1508;&#1497;&#1500;')); ?></h5>
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
                                    ?>
                                    <li><?php echo esc_html($lineText); ?></li>
                                  <?php endforeach; ?>
                                </ul>
                              <?php endif; ?>

                              <?php if (!empty($orderData['attendees'])): ?>
                                <div class="tapin-pa-order__section tapin-pa-attendees">
                                  <h5 class="tapin-pa-order__section-title"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1493;&#1494;&#1502;&#1504;&#1497;&#1501;')); ?></h5>
                                  <div class="tapin-pa-attendees__grid">
                                    <?php foreach ((array) $orderData['attendees'] as $attendee): ?>
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
                                                  'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1496;&#1500;&#1508;&#1493;&#1503;'),
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
                                                  'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1514;&#1506;&#1493;&#1491;&#1514;&#32;&#1494;&#1492;&#1493;&#1514;'),
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
                                                  'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1500;&#1497;&#1491;&#1492;'),
                                                  'value' => $birthDate,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['gender'])) {
                                          $genderRaw = trim((string) $attendee['gender']);
                                          if ($genderRaw !== '') {
                                              $attendeeRows[] = [
                                                  'label' => \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1490;&#1491;&#1512;'),
                                                  'value' => $genderRaw,
                                                  'type'  => 'text',
                                                  'href'  => '',
                                              ];
                                          }
                                      }

                                      if (!empty($attendee['instagram'])) {
                                          $instagramRaw = trim((string) $attendee['instagram']);
                                          if ($instagramRaw !== '') {
                                              $candidate = $instagramRaw;
                                              $display = \Tapin\Events\Features\Orders\ProducerApprovals\Utils\SocialUrl::trimHandle($instagramRaw);
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

                                      <?php
                                      $orderId = (int) ($orderData['id'] ?? 0);
                                      $itemId = isset($attendee['item_id']) ? (int) $attendee['item_id'] : 0;
                                      $attendeeIdx = isset($attendee['idx']) ? (int) $attendee['idx'] : -1;
                                      $isApprovedAttendee = !empty($attendee['approved']);
                                      $eventDateTs = isset($attendee['event_date_ts']) ? (int) $attendee['event_date_ts'] : 0;
                                      $eventPassed = $eventDateTs > 0 && $eventDateTs < $now;
                                      $approveTooltip = $eventPassed
                                          ? \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1500;&#1488;&#32;&#1504;&#1497;&#1510;&#1503;&#32;&#1500;&#1488;&#1513;&#1512;&#32;&#1492;&#1494;&#1502;&#1504;&#1492;&#32;&#1500;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1513;&#1499;&#1489;&#1512;&#32;&#1506;&#1489;&#1512;')
                                          : '';
                                      $canApproveAttendee = $orderId > 0 && $itemId > 0 && $attendeeIdx >= 0;
                                      $approveFieldName = $canApproveAttendee
                                          ? sprintf('approve_attendee[%d][%d][%d]', $orderId, $itemId, $attendeeIdx)
                                          : '';
                                      ?>
                                      <div class="tapin-pa-attendee">
                                        <div class="tapin-pa-attendee__header">
                                          <h6 class="tapin-pa-attendee__title">
                                            <?php
                                            $displayName = trim((string) ($attendee['full_name'] ?? ''));
                                            echo esc_html($displayName !== '' ? $displayName : \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1493;&#1512;&#1495;'));
                                            ?>
                                          </h6>
                                          <?php if ($canApproveAttendee): ?>
                                            <label class="tapin-pa-attendee__approve-box">
                                              <input
                                                type="checkbox"
                                                class="tapin-pa-attendee__approve"
                                                name="<?php echo esc_attr($approveFieldName); ?>"
                                                value="1"
                                                data-order-id="<?php echo esc_attr((string) $orderId); ?>"
                                                data-item-id="<?php echo esc_attr((string) $itemId); ?>"
                                                data-attendee-idx="<?php echo esc_attr((string) $attendeeIdx); ?>"
                                                <?php echo $isApprovedAttendee ? 'checked' : ''; ?>
                                                <?php echo $eventPassed ? 'disabled' : ''; ?>
                                                <?php echo $approveTooltip !== '' ? 'title="' . esc_attr($approveTooltip) . '"' : ''; ?>
                                              >
                                              <span><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1513;&#1512;')); ?></span>
                                            </label>
                                          <?php endif; ?>
                                          <span class="tapin-pa-attendee__badge"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1502;&#1493;&#1494;&#1502;&#1503;&#47;&#1514;')); ?></span>
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
              <div class="tapin-pa-empty"><?php echo esc_html(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1503;&#32;&#1488;&#1497;&#1513;&#1493;&#1512;&#1497;&#1501;&#32;&#1500;&#1492;&#1510;&#1490;&#1492;&#32;&#1499;&#1512;&#1490;&#1506;&#46;')); ?></div>
            <?php endif; ?>
          </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
