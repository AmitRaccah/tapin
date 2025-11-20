<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Support\Search;
use Tapin\Events\Support\Time;
use Tapin\Events\UI\Components\AffiliateLinkUI;

final class SalesRenderer
{
    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $text
     */
    public function render(array $rows, int $currentUserId, array $text): string
    {
        ob_start();
        ?>
        <div class="tapin-pa tapin-sales" dir="rtl">
          <h3><?php echo esc_html($text['page_heading']); ?></h3>
          <?php if ($rows): ?>
            <div class="tapin-pa__events" id="tapinPaEvents">
              <?php $loopIndex = 0; ?>
              <?php foreach ($rows as $productId => $row): ?>
                <?php
                $isOpen = $loopIndex === 0;
                $loopIndex++;
                $stats = isset($row['stats']) && is_array($row['stats']) ? $row['stats'] : [];
                $regularTotal = (int) ($stats['regular_total'] ?? 0);
                $regularLink = (int) ($stats['regular_affiliate'] ?? 0);
                $regularDirect = (int) ($stats['regular_direct'] ?? max(0, $regularTotal - $regularLink));
                $specialTypes = isset($stats['special_types']) && is_array($stats['special_types']) ? $stats['special_types'] : [];
                if ($specialTypes) {
                    uasort($specialTypes, static function (array $a, array $b): int {
                        return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
                    });
                }
                $windowStats = isset($stats['windows']) && is_array($stats['windows']) ? $stats['windows'] : [];
                $linkHtml = ($currentUserId === (int) ($row['author_id'] ?? 0)) ? AffiliateLinkUI::renderForProduct((int) $productId) : '';
                $searchBlob = $this->buildSearchBlob($row);
                $affCommission = (float) ($row['ref_commission'] ?? 0);
                $eventDateLabel = isset($row['event_date_label']) ? (string) $row['event_date_label'] : '';
                $sumTotal = (float) ($row['sum'] ?? 0.0);
                $sumAffiliate = (float) ($row['ref_sum'] ?? 0.0);
                $sumDirect = max(0.0, $sumTotal - $sumAffiliate);
                $feePercent = isset($row['fee_percent']) ? (float) $row['fee_percent'] : 0.0;
                $feeTotal = isset($row['fee_total']) ? (float) $row['fee_total'] : 0.0;
                $producerCommissionLabel = $this->describeProducerCommission($row['commission_meta'] ?? [], $text);
                ?>
                <div class="tapin-pa-event<?php echo $isOpen ? ' is-open' : ''; ?>" data-search="<?php echo esc_attr($searchBlob); ?>">
                  <button class="tapin-pa-event__header" type="button" data-event-toggle aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                    <div class="tapin-pa-event__summary">
                      <?php if (!empty($row['thumb'])): ?>
                        <img class="tapin-pa-event__image" src="<?php echo esc_url($row['thumb']); ?>" alt="<?php echo esc_attr($row['name']); ?>" loading="lazy">
                      <?php else: ?>
                        <div class="tapin-pa-event__image" aria-hidden="true"></div>
                      <?php endif; ?>
                      <div class="tapin-pa-event__text">
                        <h4>
                          <?php if (!empty($row['view'])): ?>
                            <a href="<?php echo esc_url($row['view']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['name']); ?></a>
                          <?php else: ?>
                            <?php echo esc_html($row['name']); ?>
                          <?php endif; ?>
                        </h4>
                        <?php if ($eventDateLabel !== ''): ?>
                          <div style="font-size:.85rem;color:#475569;margin-top:2px;"><?php echo esc_html($eventDateLabel); ?></div>
                        <?php endif; ?>
                        <div class="tapin-pa-event__stats">
                          <span class="tapin-pa-event__badge"><?php echo esc_html($text['total_tickets']); ?>: <?php echo number_format_i18n((int) ($row['qty'] ?? 0)); ?></span>
                          <span class="tapin-pa-event__badge"><?php echo esc_html($text['total_revenue']); ?>: <?php echo function_exists('wc_price') ? wc_price((float) ($row['sum'] ?? 0)) : esc_html(number_format_i18n((float) ($row['sum'] ?? 0), 2)); ?></span>
                          <span class="tapin-pa-event__badge"><?php echo esc_html($text['regular_link']); ?>: <?php echo number_format_i18n($regularLink); ?></span>
                        </div>
                      </div>
                    </div>
                    <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
                  </button>
                  <div class="tapin-pa-event__panel"<?php echo $isOpen ? '' : ' hidden'; ?>>
                    <?php if ($linkHtml !== ''): ?>
                      <div class="tapin-pa-event__actions">
                        <?php echo $linkHtml; ?>
                      </div>
                    <?php endif; ?>
                    <article class="tapin-pa-order tapin-pa-order--alt" data-search="<?php echo esc_attr($searchBlob); ?>">
                      <header class="tapin-pa-order__header">
                        <div class="tapin-pa-order__meta">
                          <span><?php echo esc_html($text['regular_link']); ?>: <?php echo number_format_i18n($regularLink); ?></span>
                          <span><?php echo esc_html($text['regular_not_link']); ?>: <?php echo number_format_i18n($regularDirect); ?></span>
                          <span><?php echo esc_html($text['affiliate_fee']); ?>: <?php echo function_exists('wc_price') ? wc_price($affCommission) : esc_html(number_format_i18n($affCommission, 2)); ?></span>
                        </div>
                      </header>
                      <div class="tapin-pa-order__body">
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html($text['regular_heading']); ?></h5>
                          <div class="tapin-pa-order__grid">
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['regular_total']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularTotal); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['from_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularLink); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['not_from_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularDirect); ?></div>
                            </div>
                          </div>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html($text['amounts_heading']); ?></h5>
                          <div class="tapin-pa-order__grid">
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['sum_total']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumTotal); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['sum_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumAffiliate); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['sum_direct']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumDirect); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['sum_commission_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($affCommission); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['producer_commission']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo esc_html($producerCommissionLabel); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html($text['ticket_fee']); ?></div>
                              <div class="tapin-pa-order__value">
                                <?php
                                $percentLabel = $feePercent > 0.0
                                    ? number_format_i18n($feePercent, 2) . '%'
                                    : '0%';
                                echo esc_html($percentLabel);
                                echo ' ';
                                echo $this->formatMoney($feeTotal);
                                ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html($text['special_heading']); ?></h5>
                          <?php if ($specialTypes): ?>
                            <ul class="tapin-pa-order__lines">
                              <?php foreach ($specialTypes as $special): ?>
                                <?php
                                $label = isset($special['label']) ? (string) $special['label'] : '';
                                $qty = isset($special['qty']) ? (int) $special['qty'] : 0;
                                ?>
                                <li><?php echo esc_html($label !== '' ? $label : $text['special_heading']); ?> &mdash; <?php echo number_format_i18n($qty); ?></li>
                              <?php endforeach; ?>
                            </ul>
                          <?php else: ?>
                            <div class="tapin-pa__warning"><?php echo esc_html($text['special_empty']); ?></div>
                          <?php endif; ?>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html($text['windows_heading']); ?></h5>
                          <?php if ($windowStats): ?>
                            <div class="tapin-pa-order__grid">
                              <?php foreach ($windowStats as $windowIndex => $window): ?>
                                <?php
                                $windowLabel = $this->formatWindowLabel($window, $windowIndex + 1, $text);
                                $windowLink = (int) ($window['affiliate'] ?? 0);
                                $windowDirect = (int) ($window['direct'] ?? 0);
                                ?>
                                <div class="tapin-pa-order__card">
                                  <div class="tapin-pa-order__label"><?php echo esc_html($windowLabel); ?></div>
                                  <div class="tapin-pa-order__value">
                                    <div><?php echo esc_html($text['from_link']); ?>: <?php echo number_format_i18n($windowLink); ?></div>
                                    <div><?php echo esc_html($text['not_from_link']); ?>: <?php echo number_format_i18n($windowDirect); ?></div>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php else: ?>
                            <div class="tapin-pa__warning"><?php echo esc_html($text['windows_empty']); ?></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </article>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="tapin-pa-empty"><?php echo esc_html($text['empty_state']); ?></div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function formatMoney(float $value): string
    {
        if (function_exists('wc_price')) {
            return wc_price($value);
        }
        return esc_html(number_format_i18n($value, 2));
    }

    private function describeProducerCommission(array $meta, array $text): string
    {
        $type = isset($meta['type']) ? (string) $meta['type'] : '';
        $amount = isset($meta['amount']) ? (float) $meta['amount'] : 0.0;

        if ($type === 'percent' && $amount > 0) {
            return sprintf(
                '%s%% (%s)',
                number_format_i18n($amount, 2),
                $text['producer_commission_percent']
            );
        }

        if ($type === 'flat' && $amount > 0) {
            $money = function_exists('wc_price') ? wc_price($amount) : esc_html(number_format_i18n($amount, 2));
            return sprintf(
                '%s (%s)',
                wp_strip_all_tags($money),
                $text['producer_commission_flat']
            );
        }

        return $text['producer_commission_none'];
    }

    private function buildSearchBlob(array $row): string
    {
        $parts = [];
        if (!empty($row['name'])) {
            $parts[] = (string) $row['name'];
        }
        if (!empty($row['regular_type_label'])) {
            $parts[] = (string) $row['regular_type_label'];
        }
        $special = $row['stats']['special_types'] ?? [];
        if (is_array($special)) {
            foreach ($special as $entry) {
                if (!empty($entry['label'])) {
                    $parts[] = (string) $entry['label'];
                }
            }
        }

        return Search::normalize(implode(' ', $parts));
    }

    private function formatWindowLabel(array $window, int $position, array $text): string
    {
        $format = get_option('date_format') . ' H:i';
        $start = isset($window['start']) ? (int) $window['start'] : 0;
        $end = isset($window['end']) ? (int) $window['end'] : 0;
        $from = $start > 0 ? Time::fmtLocal($start, $format) : '';
        $to = $end > 0 ? Time::fmtLocal($end, $format) : '';

        if ($from !== '' && $to !== '') {
            return sprintf('%s %s %s %s', $text['range_from'], $from, $text['range_to'], $to);
        }
        if ($from !== '') {
            return sprintf('%s %s', $text['range_from'], $from);
        }
        if ($to !== '') {
            return sprintf('%s %s', $text['range_to'], $to);
        }
        return sprintf('%s #%d', $text['window_single'], $position);
    }
}
