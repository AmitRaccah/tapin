<?php
namespace Tapin\Events\Features;

use Tapin\Events\Core\Service;

final class UserProfileCompletion implements Service {
    private const UM_COMPLETE_PAGE_ID = 575;

    public function register(): void {
        add_action('user_register', [$this,'markIncomplete'],10);
        add_action('woocommerce_created_customer', [$this,'markIncomplete'],10);
        add_action('nsl_google_register_new_user', function(int $uid){
            $this->markIncomplete($uid);
            update_user_meta($uid, 'first_name', '');
            update_user_meta($uid, 'last_name',  '');
        },10);

        add_filter('login_redirect', [$this,'redirectIfMissing'], 10, 3);
        add_filter('google_register_redirect_url', fn($url)=> $this->editUrl(), 10);
        add_action('template_redirect', [$this,'guard']);
        add_action('user_register', [$this,'setDefaultStatus'], 5);
        add_action('nsl_google_register_new_user', [$this,'setDefaultStatus'], 5, 1);
        add_action('wp_login', function($login, $user){ $this->setDefaultStatus($user->ID); }, 10, 2);
    }

    private function editUrl(): string {
        return add_query_arg(['um_action'=>'edit'], get_permalink(self::UM_COMPLETE_PAGE_ID));
    }

    public function markIncomplete(int $uid): void { update_user_meta($uid, 'must_complete_profile', 1); }

    private function shouldRequireLastName(int $uid): bool {
        $u = get_userdata($uid); if(!$u) return true;
        $roles = (array)$u->roles;
        if (in_array('producer', $roles, true)) return false;
        $status = get_user_meta($uid, 'producer_status', true);
        if (in_array($status, ['pending','approved','rejected'], true)) return false;
        return true;
    }

    private function requiredFields(int $uid): array {
        $req = ['first_name','gender','birth_date', ['whatsapp','billing_phone','phone_number','phone','mobile','mobile_number']];
        if ($this->shouldRequireLastName($uid)) $req[]='last_name';
        return $req;
    }

    private function getMissing(int $uid): array {
        $missing=[]; foreach ($this->requiredFields($uid) as $item){
            $keys = is_array($item)?$item:[$item];
            $ok=false;
            foreach ($keys as $key){
                $v = get_user_meta($uid, $key, true);
                if (is_array($v)) $v = trim(implode(',', array_filter($v)));
                else $v = trim((string)$v);
                if ($v !== '') { $ok=true; break; }
            }
            if (!$ok) $missing[] = is_array($item)?$keys[0]:$item;
        }
        return $missing;
    }

    public function redirectIfMissing($redirect_to, $request, $user){
        if (!is_a($user, 'WP_User')) return $redirect_to;
        $missing = $this->getMissing($user->ID);
        return !empty($missing) ? $this->editUrl() : $redirect_to;
    }

    public function guard(): void {
        if (!is_user_logged_in() || is_admin() || wp_doing_ajax()) return;
        $uid = get_current_user_id();
        $missing = $this->getMissing($uid);

        if (isset($_GET['umcdebug']) && current_user_can('manage_options')) {
            wp_die('UM Complete – missing: <b>'.implode(', ', $missing).'</b>');
        }
        if (empty($missing)) { delete_user_meta($uid, 'must_complete_profile'); return; }

        $onEdit = (get_queried_object_id() === self::UM_COMPLETE_PAGE_ID) && (isset($_GET['um_action']) && $_GET['um_action']==='edit');
        if (!$onEdit) { wp_safe_redirect($this->editUrl()); exit; }
    }

    public function setDefaultStatus($user_id){
        $status = get_user_meta($user_id, 'account_status', true);
        if (empty($status)) update_user_meta($user_id, 'account_status', 'approved');
    }
}
