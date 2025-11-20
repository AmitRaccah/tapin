<?php

namespace Tapin\Events\UI\Forms;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\TicketFee;
use Tapin\Events\Support\Time;
use Tapin\Events\UI\Components\SaleWindowsRepeater;
use Tapin\Events\UI\Components\TicketTypesEditor;

final class EventFormRenderer
{
    public static function renderFields(int $productId, array $options = []): void
    {
        $opts = wp_parse_args($options, ['name_prefix' => 'sale_w', 'show_image' => true]);
        $post = get_post($productId);
        if (!$post) {
            return;
        }

        $ticketTypes = TicketTypesRepository::get($productId);
        $windows     = SaleWindowsRepository::get($productId, $ticketTypes);

        $thumbHtml = '';
        $bgHtml    = '';
        if (!empty($opts['show_image'])) {
            $thumbHtml = get_the_post_thumbnail($productId, 'medium', ['class' => 'tapin-form-row__preview-img']);
            $bgId      = (int) get_post_meta($productId, MetaKeys::EVENT_BG_IMAGE, true);
            if ($bgId) {
                $bgHtml = wp_get_attachment_image($bgId, 'large', false, ['class' => 'tapin-form-row__preview-img']);
            }
        }

        $eventInput = Time::tsToLocalInput(Time::productEventTs($productId));
        $feePercent = TicketFee::getPercent($productId); ?>
        <style><?php echo Assets::sharedCss(); ?></style>
        <div class="tapin-form-row">
            <label>כותרת האירוע</label>
            <input type="text" name="title" value="<?php echo esc_attr($post->post_title); ?>">
        </div>
        <div class="tapin-form-row">
            <label>תיאור האירוע</label>
            <textarea name="desc" rows="4"><?php echo esc_textarea($post->post_content); ?></textarea>
        </div>
        <?php TicketTypesEditor::render($ticketTypes); ?>
        <?php SaleWindowsRepeater::render($windows, $opts['name_prefix'], $ticketTypes); ?>
        <div class="tapin-form-row">
            <label>עמלת כרטיס (%)</label>
            <input
                type="number"
                name="ticket_fee_percent"
                min="0"
                step="0.1"
                value="<?php echo esc_attr($feePercent); ?>">
            <small style="display:block;margin-top:6px;color:#475569;font-size:.85rem;">
                האחוז שיתווסף לכל כרטיס באירוע זה (ברירת מחדל 5% אם לא תשונה).
            </small>
        </div>
        <div class="tapin-form-row">
            <label>תאריך ושעת האירוע</label>
            <input type="datetime-local" name="event_dt" value="<?php echo esc_attr($eventInput); ?>">
        </div>
        <?php if (!empty($opts['show_image'])): ?>
            <div class="tapin-form-row">
                <label>תמונת קאבר ראשית</label>
                <?php if ($thumbHtml): ?>
                    <div class="tapin-form-row__preview">
                        <?php echo $thumbHtml; ?>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="tapin-form-row">
                <label>תמונת רקע לדף המוצר</label>
                <?php if ($bgHtml): ?>
                    <div class="tapin-form-row__preview">
                        <?php echo $bgHtml; ?>
                    </div>
                <?php endif; ?>
                <input type="file" name="bg_image" accept="image/*">
                <small style="display:block;margin-top:6px;color:#475569;font-size:.85rem;">מומלץ להעלות תמונה רוחבית בגודל של לפחות ‎1920‎ פיקסלים כדי להבטיח תצוגה חדה.</small>
            </div>
        <?php endif; ?>
        <?php
    }
}
