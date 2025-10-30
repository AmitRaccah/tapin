<?php
namespace Tapin\Events\Features\Admin;

use Tapin\Events\Domain\EventProductService;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Util;

final class AdminCenterActions {
    public static function handle(array $atts=[]): string {
        if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['tapin_admin_nonce']) || !wp_verify_nonce($_POST['tapin_admin_nonce'],'tapin_admin_action')) return '';
        $pid = (int)($_POST['pid'] ?? 0);
        $p = $pid ? get_post($pid) : null;
        if (!$p || get_post_type($pid)!=='product') return '';

        $key = 'tapin_admin_action_pid_'.$pid.'_'.md5(serialize($_POST));
        if (get_transient($key)) return '<div class="tapin-notice tapin-notice--error">הפעולה כבר בוצעה.</div>';
        set_transient($key,1,5);

        $svc = new EventProductService();
        $action = '';
        foreach (['approve_new','quick_save','approve_edit','reject_edit','trash','pause_sale','resume_sale'] as $k) if(isset($_POST[$k])){$action=$k;break;}

        switch ($action) {
            case 'approve_new':
                $termIds = Util::catSlugsToIds(isset($_POST['cats'])?(array)$_POST['cats']:[]);
                if (!$termIds) { delete_transient($key); return '<div class="tapin-notice tapin-notice--error">יש לבחור לפחות קטגוריה אחת.</div>'; }
                $ticketTypesPost = TicketTypesRepository::parseFromPost('ticket_type');
                $saleWindowsPost = SaleWindowsRepository::parseFromPost('sale_w', $ticketTypesPost);
                $svc->applyFields($pid,[
                    'title'=>$_POST['title']??'','desc'=>$_POST['desc']??'',
                    'price'=>$_POST['price']??'','stock'=>$_POST['stock']??'',
                    'event_dt'=>$_POST['event_dt']??'','image_field'=>'image',
                    'background_field'=>'bg_image',
                    'ticket_types'=> $ticketTypesPost,
                    'sale_windows'=> $saleWindowsPost
                ]);
                wp_set_object_terms($pid, $termIds, 'product_cat', false);
                if ($pending = get_term_by('slug','pending-events','product_cat')) wp_remove_object_terms($pid, [(int)$pending->term_id], 'product_cat');
                wp_set_object_terms($pid,'simple','product_type',false);
                if ($p = wc_get_product($pid)){
                    $p->set_status('publish'); $p->set_catalog_visibility('visible');
                    $feature = in_array(strtolower((string)($atts['feature_on_approve'] ?? '0')), ['1','yes','true'], true);
                    if ($feature) $p->set_featured(true);
                    $p->save();
                }
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--success">האירוע אושר ופורסם.</div>';

            case 'quick_save':
                $ticketTypesPost = TicketTypesRepository::parseFromPost('ticket_type');
                $saleWindowsPost = SaleWindowsRepository::parseFromPost('sale_w', $ticketTypesPost);
                $svc->applyFields($pid,[
                    'title'=>$_POST['title']??'','desc'=>$_POST['desc']??'',
                    'price'=>$_POST['price']??'','stock'=>$_POST['stock']??'',
                    'event_dt'=>$_POST['event_dt']??'','image_field'=>'image',
                    'background_field'=>'bg_image',
                    'ticket_types'=> $ticketTypesPost,
                    'sale_windows'=> $saleWindowsPost
                ]);
                if (isset($_POST['cats'])) {
                    $termIds = Util::catSlugsToIds((array)$_POST['cats']);
                    if ($termIds) wp_set_object_terms($pid, $termIds, 'product_cat', false);
                }
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--success">הנתונים נשמרו.</div>';

            case 'approve_edit':
                $req = get_post_meta($pid, MetaKeys::EDIT_REQ, true);
                if (is_array($req) && !empty($req['data'])) {
                    $data=$req['data'];
                    $svc->applyFields($pid, $data);
                    if (!empty($data['new_image_id'])) set_post_thumbnail($pid,(int)$data['new_image_id']);
                    if (!empty($data['new_background_id'])) update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $data['new_background_id']);
                    delete_post_meta($pid, MetaKeys::EDIT_REQ);
                    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                    clean_post_cache($pid);
                    return '<div class="tapin-notice tapin-notice--success">בקשת העריכה אושרה והוחלה.</div>';
                }
                return '';

            case 'reject_edit':
                delete_post_meta($pid, MetaKeys::EDIT_REQ);
                return '<div class="tapin-notice tapin-notice--warning">בקשת העריכה נדחתה.</div>';

            case 'trash':
                wp_trash_post($pid);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--warning">האירוע נמחק והועבר לאשפה.</div>';

            case 'pause_sale':
                update_post_meta($pid, MetaKeys::PAUSED, 'yes');
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--warning">מכירת הכרטיסים הושהתה.</div>';

            case 'resume_sale':
                delete_post_meta($pid, MetaKeys::PAUSED);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--success">המכירה חודשה.</div>';
        }
        return '';
    }
}
