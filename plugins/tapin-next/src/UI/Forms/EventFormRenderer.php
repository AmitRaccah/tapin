<?php
namespace Tapin\Events\UI\Forms;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;
use Tapin\Events\UI\Components\SaleWindowsRepeater;

final class EventFormRenderer {
    public static function renderFields(int $productId, array $options=[]): void {
        $opts = wp_parse_args($options, ['name_prefix'=>'sale_w','show_image'=>true]);
        $post = get_post($productId); if(!$post) return;

        $reg_price = get_post_meta($productId, '_regular_price', true);
        $stock     = get_post_meta($productId, '_stock', true);
        $windows   = SaleWindowsRepository::get($productId);
        $thumb_html = '';
        $bg_html    = '';
        if ($opts['show_image']) {
            $thumb_html = get_the_post_thumbnail($productId, 'medium', ['class' => 'tapin-form-row__preview-img']);
            $bg_id = (int) get_post_meta($productId, MetaKeys::EVENT_BG_IMAGE, true);
            if ($bg_id) {
                $bg_html = wp_get_attachment_image($bg_id, 'large', false, ['class' => 'tapin-form-row__preview-img']);
            }
        }

        $event_input = Time::tsToLocalInput(Time::productEventTs($productId)); ?>
        <style><?php echo Assets::sharedCss(); ?></style>
        <div class="tapin-form-row">
          <label>כותרת</label>
          <input type="text" name="title" value="<?php echo esc_attr($post->post_title); ?>">
        </div>
        <div class="tapin-form-row">
          <label>תיאור</label>
          <textarea name="desc" rows="4"><?php echo esc_textarea($post->post_content); ?></textarea>
        </div>
        <div class="tapin-columns-2">
          <div class="tapin-form-row">
            <label>מחיר</label>
            <input type="number" name="price" step="0.01" min="0" value="<?php echo esc_attr($reg_price); ?>">
          </div>
          <div class="tapin-form-row">
            <label>כמות כרטיסים</label>
            <input type="number" name="stock" step="1" min="0" value="<?php echo esc_attr($stock); ?>">
          </div>
        </div>
        <?php SaleWindowsRepeater::render($windows, $opts['name_prefix']); ?>
        <div class="tapin-form-row">
          <label>מועד האירוע</label>
          <input type="datetime-local" name="event_dt" value="<?php echo esc_attr($event_input); ?>">
        </div>
        <?php if ($opts['show_image']): ?>
        <div class="tapin-form-row">
          <label>תמונה</label>
          <?php if ($thumb_html): ?>
          <div class="tapin-form-row__preview">
            <?php echo $thumb_html; ?>
          </div>
          <?php endif; ?>
          <input type="file" name="image" accept="image/*">
        </div>
        <div class="tapin-form-row">
          <label>תמונת רקע לדף המכירה</label>
          <?php if ($bg_html): ?>
          <div class="tapin-form-row__preview">
            <?php echo $bg_html; ?>
          </div>
          <?php endif; ?>
          <input type="file" name="bg_image" accept="image/*">
          <small style="display:block;margin-top:6px;color:#475569;font-size:.85rem;">מומלץ להעלות תמונה לרוחב של 1920 פיקסלים לפחות כדי לשמור על חדות בכל מסך.</small>
        </div>
        <?php endif; ?>
        <?php
    }
}
