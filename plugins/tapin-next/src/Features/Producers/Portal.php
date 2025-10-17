<?php
namespace Tapin\Events\Features\Producers;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\ProducerProfiles;
use Tapin\Events\Support\Security;

final class Portal implements Service {
    private RegistrationForm $form;

    public function __construct(?RegistrationForm $form = null) {
        $this->form = $form ?? new RegistrationForm();
    }

    public function register(): void {
        add_action('init', [$this, 'registerEndpoints']);
        add_filter('woocommerce_get_query_vars', [$this, 'registerQueryVars']);
        add_filter('woocommerce_account_menu_items', [$this, 'filterAccountMenu']);
        add_filter('woocommerce_endpoint_become-producer_title', static fn() => 'בקשת מפיק');
        add_filter('woocommerce_endpoint_producer_title', static fn() => 'פרופיל מפיק');

        add_action('woocommerce_account_become-producer_endpoint', function () {
            $this->form->render(false);
        });
        add_action('woocommerce_account_producer_endpoint', function () {
            $this->renderProducerEndpoint();
        });

        add_shortcode('producer_signup', function () {
            return $this->form->render(false, true);
        });

        add_action('wp_footer', [$this->form, 'displayFlash']);

        add_action('um_profile_content_main_default', [$this, 'umDisplayAbout'], 20);
        add_filter('um_prepare_fields_for_profile', [$this, 'umHideEmail'], 10, 2);
        add_filter('um_user_avatar_url_filter', [$this, 'umAvatarUrl'], 10, 2);
        add_filter('pre_get_avatar_data', [$this, 'avatarFilter'], 10, 2);
        add_action('wp_head', [$this, 'injectCoverCss']);
    }

    public function registerEndpoints(): void {
        add_rewrite_endpoint('become-producer', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('producer', EP_ROOT | EP_PAGES);
    }

    public function registerQueryVars(array $vars): array {
        $vars['become-producer'] = 'become-producer';
        $vars['producer']        = 'producer';
        return $vars;
    }

    public function filterAccountMenu(array $items): array {
        if (!is_user_logged_in()) {
            return $items;
        }
        $user   = wp_get_current_user();
        $status = get_user_meta($user->ID, 'producer_status', true);

        if (in_array('producer', (array) $user->roles, true)) {
            $items['producer'] = 'פרופיל מפיק';
        } elseif ($status === 'pending' || $status === 'rejected') {
            $items['become-producer'] = ($status === 'pending')
                ? 'בקשת מפיק – ממתין'
                : 'בקשת מפיק (נדחה)';
        }
        return $items;
    }

    private function renderProducerEndpoint(): void {
        $guard = Security::producer();
        if (!$guard->allowed) {
            echo '<div class="tapin-scope tapin-center-container">' . $guard->message . '</div>';
            return;
        }

        echo $this->form->render(true);
    }

    // --- Ultimate Member integrations ---

    public function umDisplayAbout($args): void {
        $user_id = !empty($args['user_id']) ? (int) $args['user_id'] : 0;
        if (!$user_id && function_exists('um_profile_id')) {
            $user_id = um_profile_id();
        }
        if (!$user_id) {
            return;
        }

        $about = get_user_meta($user_id, 'producer_about', true);
        if ($about) {
            echo '<div class="um-field" style="padding: 15px 0;">';
            echo '<div class="um-field-label" style="font-weight: bold; margin-bottom: 8px;"><label>אודות</label></div>';
            echo '<div class="um-field-value">' . wpautop(esc_html($about)) . '</div>';
            echo '</div>';
        }
    }

    public function umHideEmail($fields, $user_id) {
        if (isset($fields['user_email']) && user_can($user_id, 'producer')) {
            unset($fields['user_email']);
        }
        return $fields;
    }

    public function umAvatarUrl($url, $user_id) {
        $meta = get_user_meta($user_id, 'profile_photo', true);
        if ($meta) {
            if (is_numeric($meta)) {
                $src = wp_get_attachment_image_src((int) $meta, 'medium') ?: wp_get_attachment_image_src((int) $meta, 'full');
                if ($src && !empty($src[0])) {
                    return $src[0];
                }
            } else {
                $file_url = ProducerProfiles::umUrlForUserfile($user_id, $meta);
                if ($file_url) {
                    return $file_url;
                }
            }
        }

        $meta2 = get_user_meta($user_id, 'um_profile_photo', true);
        if (!empty($meta2) && !is_numeric($meta2)) {
            return ProducerProfiles::umUrlForUserfile($user_id, $meta2);
        }

        $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
        if ($aid) {
            $src = wp_get_attachment_image_src($aid, 'medium') ?: wp_get_attachment_image_src($aid, 'full');
            if ($src && !empty($src[0])) {
                return $src[0];
            }
        }
        return $url;
    }

    public function avatarFilter($args, $id_or_email) {
        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_string($id_or_email)) {
            $u = get_user_by('email', $id_or_email);
            if ($u) {
                $user_id = $u->ID;
            }
        }
        if ($user_id) {
            $meta = get_user_meta($user_id, 'profile_photo', true);
            if ($meta) {
                if (is_numeric($meta)) {
                    $src = wp_get_attachment_image_src((int) $meta, 'thumbnail') ?: wp_get_attachment_image_src((int) $meta, 'full');
                    if ($src && !empty($src[0])) {
                        $args['url'] = $src[0];
                    }
                } else {
                    $args['url'] = ProducerProfiles::umUrlForUserfile($user_id, $meta);
                }
            } else {
                $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
                if ($aid) {
                    $src = wp_get_attachment_image_src($aid, 'thumbnail') ?: wp_get_attachment_image_src($aid, 'full');
                    if ($src && !empty($src[0])) {
                        $args['url'] = $src[0];
                    }
                }
            }
        }
        return $args;
    }

    public function injectCoverCss(): void {
        if (!function_exists('um_is_core_page')) {
            return;
        }
        if (!(um_is_core_page('user') || um_is_core_page('profile') || um_is_core_page('account'))) {
            return;
        }
        $uid = function_exists('um_profile_id') ? um_profile_id() : get_current_user_id();
        if (!$uid) {
            return;
        }
        $cover = $this->getUmCoverUrl($uid);
        if (!$cover) {
            return;
        }
        $css = '.um-profile.um-viewing .um-cover-e{background-image:url(' . esc_url($cover) . ')!important;background-size:cover!important;background-position:center!important}';
        echo '<style id="tapin-um-cover-css">' . $css . '</style>';
    }

    private function getUmCoverUrl(int $user_id, string $size = 'full'): string {
        $meta = get_user_meta($user_id, 'cover_photo', true);
        if ($meta) {
            if (is_numeric($meta)) {
                $url = wp_get_attachment_image_url((int) $meta, $size);
                if ($url) {
                    return $url;
                }
            } else {
                return ProducerProfiles::umUrlForUserfile($user_id, $meta);
            }
        }
        $cid = (int) get_user_meta($user_id, 'producer_cover_id', true);
        if ($cid) {
            $url = wp_get_attachment_image_url($cid, $size);
            if ($url) {
                return $url;
            }
        }
        return '';
    }
}
