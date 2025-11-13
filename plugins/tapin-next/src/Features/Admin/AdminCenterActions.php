<?php
namespace Tapin\Events\Features\Admin;

use Tapin\Events\Domain\EventProductService;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Security;
use Tapin\Events\Support\Util;

final class AdminCenterActions {
    public static function handle(array $atts=[]): string {
        if ($_SERVER['REQUEST_METHOD']!=='POST') return '';

        $security = Security::manager();
        if (!$security->allowed) {
            wp_die(
                $security->message !== '' ? $security->message : Security::forbiddenMessage('?"?"?� ?????�?T?? ?`???`?T??.'),
                'tapin_admin_forbidden',
                ['response' => 403]
            );
        }

        if (empty($_POST['tapin_admin_nonce']) || !wp_verify_nonce($_POST['tapin_admin_nonce'],'tapin_admin_action')) {
            wp_die(Security::forbiddenMessage('Invalid action nonce.'), 'tapin_admin_invalid_nonce', ['response' => 403]);
        }
        $pid = (int)($_POST['pid'] ?? 0);
        $p = $pid ? get_post($pid) : null;
        if (!$p || get_post_type($pid)!=='product') return '';

        $key = 'tapin_admin_action_pid_'.$pid.'_'.md5(serialize($_POST));
        if (get_transient($key)) {
            return self::respond('duplicate_attempt', $pid, '<div class="tapin-notice tapin-notice--error">הפעולה כבר בוצעה.</div>', false);
        }
        set_transient($key,1,5);

        $svc = new EventProductService();
        $action = '';
        foreach (['approve_new','quick_save','approve_edit','reject_edit','trash','pause_sale','resume_sale'] as $k) if(isset($_POST[$k])){$action=$k;break;}

        switch ($action) {
            case 'approve_new':
                $termIds = Util::catSlugsToIds(isset($_POST['cats'])?(array)$_POST['cats']:[]);
                if (!$termIds) { delete_transient($key); return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--error">יש לבחור לפחות קטגוריה אחת.</div>', false); }
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
                self::persistProducerAffiliateMeta($pid);
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
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--success">האירוע אושר ופורסם.</div>', true);

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
                self::persistProducerAffiliateMeta($pid);
                if (isset($_POST['cats'])) {
                    $termIds = Util::catSlugsToIds((array)$_POST['cats']);
                    if ($termIds) wp_set_object_terms($pid, $termIds, 'product_cat', false);
                }
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--success">הנתונים נשמרו.</div>', true);

            case 'approve_edit':
                $req = get_post_meta($pid, MetaKeys::EDIT_REQ, true);
                if (is_array($req) && !empty($req['data'])) {
                    $data=$req['data'];
                    $svc->applyFields($pid, $data);
                    if (!empty($data['new_image_id'])) set_post_thumbnail($pid,(int)$data['new_image_id']);
                    if (!empty($data['new_background_id'])) update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $data['new_background_id']);
                    self::persistProducerAffiliateMeta($pid);
                    delete_post_meta($pid, MetaKeys::EDIT_REQ);
                    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                    clean_post_cache($pid);
                    return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--success">בקשת העריכה אושרה והוחלה.</div>', true);
                }
                self::reportAction($action, $pid, false);
                return '';

            case 'reject_edit':
                delete_post_meta($pid, MetaKeys::EDIT_REQ);
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--warning">בקשת העריכה נדחתה.</div>', true);

            case 'trash':
                wp_trash_post($pid);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--warning">האירוע נמחק והועבר לאשפה.</div>', true);

            case 'pause_sale':
                update_post_meta($pid, MetaKeys::PAUSED, 'yes');
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--warning">מכירת הכרטיסים הושהתה.</div>', true);

            case 'resume_sale':
                delete_post_meta($pid, MetaKeys::PAUSED);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return self::respond($action, $pid, '<div class="tapin-notice tapin-notice--success">המכירה חודשה.</div>', true);
        }
        return '';
    }

    private static function persistProducerAffiliateMeta(int $pid): void
    {
        if ($pid <= 0) {
            return;
        }

        $hasType   = isset($_POST['producer_aff_type']);
        $hasAmount = array_key_exists('producer_aff_amount', $_POST);
        if (!$hasType && !$hasAmount) {
            return;
        }

        $rawType = $hasType ? sanitize_key(wp_unslash((string) $_POST['producer_aff_type'])) : '';
        $type = in_array($rawType, ['percent', 'flat'], true) ? $rawType : 'percent';
        $rawAmount = $hasAmount ? (string) wp_unslash((string) $_POST['producer_aff_amount']) : '0';
        $amount = max(0, (float) $rawAmount);

        update_post_meta($pid, MetaKeys::PRODUCER_AFF_TYPE, $type);
        update_post_meta($pid, MetaKeys::PRODUCER_AFF_AMOUNT, $amount);
    }

    private static function respond(string $action, int $pid, string $message, bool $success, array $context = []): string
    {
        self::reportAction($action, $pid, $success, $context);
        return $message;
    }

    private static function reportAction(string $action, int $pid, bool $success, array $context = []): void
    {
        /**
         * Allows observers to monitor bulk admin center actions for auditing purposes.
         */
        do_action('tapin/admin_center/action_processed', $action, $pid, $success, $context, get_current_user_id());

        if (function_exists('tapin_next_debug_log')) {
            tapin_next_debug_log(sprintf('[admin-center] %s action "%s" on product %d', $success ? 'completed' : 'failed', $action ?: '(none)', $pid));
        }
    }
}
