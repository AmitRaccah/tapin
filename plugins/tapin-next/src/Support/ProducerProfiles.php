<?php
namespace Tapin\Events\Support;

final class ProducerProfiles {
    public static function sharedCss(): string {
        return Assets::sharedCss();
    }

    public static function fieldDefaults(int $user_id): array {
        if (!$user_id) {
            return [];
        }
        $user = get_userdata($user_id);
        return [
            'producer_display_name'  => $user ? $user->display_name : '',
            'producer_about'         => get_user_meta($user_id, 'producer_about', true),
            'producer_address'       => get_user_meta($user_id, 'producer_address', true),
            'producer_phone_public'  => get_user_meta($user_id, 'producer_phone_public', true),
            'producer_phone_private' => get_user_meta($user_id, 'producer_phone_private', true),
            'producer_instagram'     => get_user_meta($user_id, 'producer_instagram', true),
            'producer_facebook'      => get_user_meta($user_id, 'producer_facebook', true),
            'producer_whatsapp'      => get_user_meta($user_id, 'producer_whatsapp', true),
            'producer_website'       => get_user_meta($user_id, 'producer_website', true),
            'producer_tiktok'        => get_user_meta($user_id, 'producer_tiktok', true),
            'producer_youtube'       => get_user_meta($user_id, 'producer_youtube', true),
        ];
    }

    public static function avatarUrl(int $user_id, string $size = 'medium'): string {
        $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
        if ($aid) {
            $src = wp_get_attachment_image_src($aid, $size) ?: wp_get_attachment_image_src($aid, 'full');
            if ($src && !empty($src[0])) {
                return $src[0];
            }
        }
        return '';
    }

    public static function coverUrl(int $user_id, string $size = 'large'): string {
        $cid = (int) get_user_meta($user_id, 'producer_cover_id', true);
        if ($cid) {
            $url = wp_get_attachment_image_url($cid, $size);
            if ($url) {
                return $url;
            }
        }
        return '';
    }

    private static function umDirForUser(int $user_id): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'ultimatemember/' . $user_id;
    }

    public static function umUrlForUserfile(int $user_id, string $filename): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . 'ultimatemember/' . $user_id . '/' . ltrim($filename, '/');
    }

    public static function umProfilePhotoUrl(int $user_id, string $size = 'medium'): string {
        $meta = get_user_meta($user_id, 'profile_photo', true);
        if ($meta) {
            if (is_numeric($meta) && (int) $meta > 0) {
                $src = wp_get_attachment_image_src((int) $meta, $size) ?: wp_get_attachment_image_src((int) $meta, 'full');
                if ($src && !empty($src[0])) {
                    return $src[0];
                }
            } else {
                return self::umUrlForUserfile($user_id, $meta);
            }
        }
        $meta2 = get_user_meta($user_id, 'um_profile_photo', true);
        if (!empty($meta2) && !is_numeric($meta2)) {
            return self::umUrlForUserfile($user_id, $meta2);
        }
        return self::avatarUrl($user_id, $size);
    }

    public static function umCoverUrl(int $user_id, string $size = 'full'): string {
        $meta = get_user_meta($user_id, 'cover_photo', true);
        if ($meta) {
            if (is_numeric($meta) && (int) $meta > 0) {
                $url = wp_get_attachment_image_url((int) $meta, $size);
                if ($url) {
                    return $url;
                }
            } else {
                return self::umUrlForUserfile($user_id, $meta);
            }
        }
        $meta2 = get_user_meta($user_id, 'um_cover_photo', true);
        if (!empty($meta2) && !is_numeric($meta2)) {
            return self::umUrlForUserfile($user_id, $meta2);
        }
        return self::coverUrl($user_id, $size);
    }

    public static function syncSocialsToUmKeys(int $user_id, array $values): void {
        $map = [
            'instagram' => ['instagram', 'instagram_url'],
            'facebook'  => ['facebook', 'facebook_url'],
            'tiktok'    => ['tiktok', 'tiktok_url'],
            'youtube'   => ['youtube', 'youtube_url'],
            'whatsapp'  => ['whatsapp', 'whatsapp_number', 'whatsapp_phone', 'phone_whatsapp'],
        ];

        foreach ($map as $key => $targets) {
            $value = isset($values[$key]) ? trim((string) $values[$key]) : '';
            if ($key === 'whatsapp') {
                $value = preg_replace('/\D+/', '', $value);
            }
            if ($value === '') {
                continue;
            }
            foreach ($targets as $target) {
                update_user_meta($user_id, $target, $value);
            }
        }

        if (!empty($values['website'])) {
            $url = esc_url_raw($values['website']);
            wp_update_user(['ID' => $user_id, 'user_url' => $url]);
            update_user_meta($user_id, 'website', $url);
            update_user_meta($user_id, 'website_url', $url);
        }
    }
}
