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

        $event_dt_local = get_post_meta($productId, MetaKeys::EVENT_DATE, true);
        $reg_price = get_post_meta($productId, '_regular_price', true);
        $stock     = get_post_meta($productId, '_stock', true);
        $windows   = SaleWindowsRepository::get($productId);

        $event_input = '';
        if ($event_dt_local) {
            try { $event_input=(new \DateTime($event_dt_local, Time::tz()))->format('Y-m-d\TH:i'); }
            catch(\Throwable $e){ $event_input=''; }
        } ?>
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
          <input type="file" name="image" accept="image/*">
        </div>
        <?php endif; ?>
        <?php
    }
}
