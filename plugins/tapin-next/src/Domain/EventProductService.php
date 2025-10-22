<?php
namespace Tapin\Events\Domain;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;
use Tapin\Events\Support\Util;

final class EventProductService {

    public function applyFields(int $pid, array $arr): void {
        $title = sanitize_text_field($arr['title'] ?? '');
        $desc  = wp_kses_post($arr['desc'] ?? '');
        $price = ($arr['price']!=='' ? Util::fmtPriceVal($arr['price']) : '');
        $stock = isset($arr['stock']) && $arr['stock'] !== '' ? absint($arr['stock']) : null;
        $event_dt_local = sanitize_text_field($arr['event_dt'] ?? '');
        $event_ts = $event_dt_local ? Time::localStrToUtcTs($event_dt_local) : 0;

        if ($title !== '') wp_update_post(['ID'=>$pid,'post_title'=>$title]);
        if ($desc  !== '') wp_update_post(['ID'=>$pid,'post_content'=>$desc]);

        if ($price !== '') {
            update_post_meta($pid,'_regular_price',$price);
            update_post_meta($pid,'_price',$price);
        }

        if ($stock !== null && $stock >= 0) {
            update_post_meta($pid, '_manage_stock', 'yes');
            update_post_meta($pid, '_stock', $stock);
        }

        // remove legacy sale meta
        delete_post_meta($pid,'_sale_price');
        delete_post_meta($pid,'_sale_price_dates_from');
        delete_post_meta($pid,'_sale_price_dates_to');

        if (array_key_exists('sale_windows', $arr) && is_array($arr['sale_windows'])) {
            SaleWindowsRepository::save($pid, $arr['sale_windows']);
        }

        if ($event_ts) {
            $local_str = wp_date('Y-m-d H:i:s', $event_ts, Time::tz());
            update_post_meta($pid, MetaKeys::EVENT_DATE, $local_str);
        }

        $uploadFields = [];
        if (!empty($arr['image_field']) && !empty($_FILES[$arr['image_field']]['name'] ?? '')) {
            $uploadFields['image'] = $arr['image_field'];
        }
        if (!empty($arr['background_field']) && !empty($_FILES[$arr['background_field']]['name'] ?? '')) {
            $uploadFields['background'] = $arr['background_field'];
        }

        if ($uploadFields) {
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
        }

        if (isset($uploadFields['image'])) {
            $att_id = media_handle_upload($uploadFields['image'], $pid);
            if (!is_wp_error($att_id)) {
                set_post_thumbnail($pid, $att_id);
            }
        }

        if (isset($uploadFields['background'])) {
            $bg_id = media_handle_upload($uploadFields['background'], $pid);
            if (!is_wp_error($bg_id)) {
                update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $bg_id);
            }
        }

        if (!empty($arr['new_background_id'])) {
            update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $arr['new_background_id']);
        }
    }
}
