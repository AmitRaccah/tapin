<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Admin\AdminCenterActions;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\Cap;
use Tapin\Events\Support\Util;
use Tapin\Events\UI\Forms\EventFormRenderer;

final class EventsAdminCenter implements Service {
    public function register(): void { add_shortcode('events_admin_center', [$this,'render']); }

    public function render($atts=[]): string {
        if (!Cap::isManager()) { status_header(403); return '<div class="tapin-notice tapin-notice--error">הדף למנהלים בלבד.</div>'; }
        $a = shortcode_atts(['feature_on_approve'=>'0'], $atts, 'events_admin_center');

        $msg = AdminCenterActions::handle($a);

        $pending_q = new \WP_Query(['post_type'=>'product','post_status'=>['pending'],'posts_per_page'=>-1,'no_found_rows'=>true]);
        $pending_ids = $pending_q->have_posts()?wp_list_pluck($pending_q->posts,'ID'):[];
        $active_q = new \WP_Query(['post_type'=>'product','post_status'=>['publish'],'meta_key'=>'event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'event_date','compare'=>'>=','value'=>wp_date('Y-m-d H:i:s', time(), wp_timezone()),'type'=>'DATETIME']],'posts_per_page'=>-1,'no_found_rows'=>true]);
        $active_ids = $active_q->have_posts()?wp_list_pluck($active_q->posts,'ID'):[];
        $edit_req_q = new \WP_Query(['post_type'=>'product','post_status'=>['publish'],'posts_per_page'=>-1,'no_found_rows'=>true,'meta_query'=>[['key'=>'tapin_edit_request','compare'=>'EXISTS']]]);
        $edit_ids = $edit_req_q->have_posts()?wp_list_pluck($edit_req_q->posts,'ID'):[];

        ob_start(); ?>
        <style><?php echo Assets::sharedCss(); ?></style>
        <div class="tapin-center-container">
          <?php echo $msg; ?>

          <h3 class="tapin-title">אירועים ממתינים לאישור</h3>
          <div class="tapin-form-grid">
          <?php if($pending_ids): foreach($pending_ids as $pid): ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card">
              <?php EventFormRenderer::renderFields($pid, ['name_prefix'=>'sale_w']); ?>
              <div class="tapin-form-row">
                <label>קטגוריות</label>
                <div class="tapin-cat-list">
                  <?php foreach(Util::catOptions() as $slug=>$name): ?>
                    <label class="tapin-cat-chip"><input type="checkbox" name="cats[]" value="<?php echo esc_attr($slug); ?>"> <span><?php echo esc_html($name); ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="tapin-actions">
                <button type="submit" name="approve_new" class="tapin-btn tapin-btn--primary">אישור ופרסום</button>
                <button type="submit" name="quick_save" class="tapin-btn tapin-btn--ghost">שמירה</button>
                <button type="submit" name="trash" class="tapin-btn tapin-btn--danger" onclick="return confirm('להעביר לאשפה?');">מחיקה</button>
              </div>
              <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
              <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
          <?php endforeach; else: ?><p>אין אירועים ממתינים.</p><?php endif; ?>
          </div>

          <h3 class="tapin-title" style="margin-top:40px;">בקשות עריכה</h3>
          <div class="tapin-form-grid">
            <?php if($edit_ids): foreach($edit_ids as $pid): $req = get_post_meta($pid, 'tapin_edit_request', true); $data=$req['data']??[]; ?>
            <form method="post" class="tapin-card">
              <div class="tapin-columns-2">
                <div><strong>נוכחי</strong>
                  <div>כותרת: <?php echo esc_html(get_the_title($pid)); ?></div>
                  <div>מחיר: <?php echo function_exists('wc_price') ? wc_price((float)get_post_meta($pid,'_regular_price',true)) : esc_html(get_post_meta($pid,'_regular_price',true)); ?></div>
                  <div>כמות: <?php echo esc_html(get_post_meta($pid,'_stock',true)); ?></div>
                </div>
                <div><strong>מבוקש</strong>
                  <div>כותרת: <?php echo esc_html($data['title']??''); ?></div>
                  <div>מחיר: <?php echo esc_html($data['price']??''); ?></div>
                  <div>כמות: <?php echo esc_html($data['stock']??''); ?></div>
                </div>
              </div>
              <div class="tapin-actions">
                <button type="submit" name="approve_edit" class="tapin-btn tapin-btn--primary">אישור בקשה</button>
                <button type="submit" name="reject_edit" class="tapin-btn tapin-btn--danger" onclick="return confirm('לדחות את הבקשה?');">דחייה</button>
              </div>
              <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
              <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; else: ?><p>אין בקשות עריכה.</p><?php endif; ?>
          </div>

          <h3 class="tapin-title" style="margin-top:40px;">אירועים פעילים</h3>
          <div class="tapin-form-grid">
            <?php if($active_ids): foreach($active_ids as $pid):
              $terms = get_the_terms($pid,'product_cat');
              $selected = $terms && !is_wp_error($terms) ? wp_list_pluck($terms,'slug') : [];
              $is_paused = get_post_meta($pid, '_sale_paused', true) === 'yes';
            ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card <?php echo $is_paused?'tapin-card--paused':''; ?>">
              <?php EventFormRenderer::renderFields($pid, ['name_prefix'=>'sale_w']); ?>
              <div class="tapin-form-row">
                <label>קטגוריות</label>
                <div class="tapin-cat-list">
                  <?php foreach(Util::catOptions() as $slug=>$name): ?>
                  <label class="tapin-cat-chip"><input type="checkbox" name="cats[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug,$selected)); ?>> <span><?php echo esc_html($name); ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="tapin-actions">
                <button type="submit" name="quick_save" class="tapin-btn tapin-btn--ghost">שמירה</button>
                <?php if ($is_paused): ?>
                  <button type="submit" name="resume_sale" class="tapin-btn tapin-btn--primary">חידוש מכירה</button>
                <?php else: ?>
                  <button type="submit" name="pause_sale" class="tapin-btn tapin-btn--warning" onclick="return confirm('להשהות מכירה?');">השהיית מכירה</button>
                <?php endif; ?>
                <button type="submit" name="trash" class="tapin-btn tapin-btn--danger" onclick="return confirm('למחוק?');">מחיקה</button>
              </div>
              <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
              <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; else: ?><p>אין אירועים פעילים.</p><?php endif; ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
