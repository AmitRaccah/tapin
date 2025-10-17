<?php
namespace Tapin\Events\Support;

final class ProducerProfiles {
    public static function sharedCss(): string {
        return '
        .tapin-scope{ --tapin-radius-md:12px; --tapin-radius-lg:16px; --tapin-primary-color:#2a1a5e; --tapin-text-dark:#1f2937; --tapin-text-light:#334155; --tapin-border-color:#e5e7eb; --tapin-success-bg:#16a34a; --tapin-danger-bg:#ef4444; --tapin-warning-bg:#f59e0b; --tapin-info-bg:#0ea5e9; --tapin-ghost-bg:#f1f5f9; --tapin-card-shadow:0 4px 12px rgba(2,6,23,.05); }
        .tapin-scope .tapin-center-container { max-width:1100px; margin-inline:auto; direction:rtl; text-align:right; }
        .tapin-scope .tapin-center-container *, .tapin-scope .tapin-center-container *::before, .tapin-scope .tapin-center-container *::after { box-sizing:border-box; }
        .tapin-scope .tapin-title { font-size:28px; font-weight:800; color:var(--tapin-primary-color); margin:14px 0 20px; }
        .tapin-scope .tapin-card { background:#fff; border:1px solid var(--tapin-border-color); border-radius:var(--tapin-radius-lg); padding:20px; box-shadow:var(--tapin-card-shadow); }
        .tapin-scope .tapin-form-row { margin-bottom:16px; }
        .tapin-scope .tapin-form-row:last-child { margin-bottom:0; }
        .tapin-scope .tapin-form-row label { display:block; margin-bottom:6px; font-weight:700; color:var(--tapin-text-dark); }
        .tapin-scope .tapin-form-row input[type="text"],
        .tapin-scope .tapin-form-row input[type="email"],
        .tapin-scope .tapin-form-row input[type="file"],
        .tapin-scope .tapin-form-row input[type="url"],
        .tapin-scope .tapin-form-row textarea { width:100%; padding:12px 14px; border:1px solid var(--tapin-border-color); border-radius:var(--tapin-radius-md); background:#fff; transition:border-color .2s, box-shadow .2s; }
        .tapin-scope .tapin-form-row input:focus,
        .tapin-scope .tapin-form-row textarea:focus { border-color:var(--tapin-primary-color); box-shadow:0 0 0 3px rgba(42,26,94,0.1); outline:none; }
        .tapin-scope .tapin-columns-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .tapin-scope .tapin-actions { display:flex; gap:12px; align-items:center; margin-top:20px; flex-wrap:wrap; }
        .tapin-scope .tapin-btn { padding:12px 20px; border-radius:var(--tapin-radius-md); border:0; cursor:pointer; font-weight:600; transition:opacity .2s; font-size:1rem; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; }
        .tapin-scope .tapin-btn:hover { opacity:.85; }
        .tapin-scope .tapin-btn--primary { background:var(--tapin-success-bg); color:#fff; }
        .tapin-scope .tapin-btn--danger { background:var(--tapin-danger-bg); color:#fff; }
        .tapin-scope .tapin-btn--warning { background:var(--tapin-warning-bg); color:#fff; }
        .tapin-scope .tapin-btn--ghost { background:var(--tapin-ghost-bg); color:var(--tapin-text-dark); }
        .tapin-scope .tapin-notice { padding:12px; border-radius:8px; margin-bottom:20px; direction:rtl; text-align:right; }
        .tapin-scope .tapin-notice--error { background:#fff4f4; border:1px solid #f3c2c2; color:#7f1d1d; }
        .tapin-scope .tapin-notice--success { background:#f0fff4; border:1px solid #b8e1c6; color:#065f46; }
        .tapin-scope .tapin-notice--warning { background:#fff7ed; border:1px solid #ffd7b5; color:#854d0e; }
        @media (max-width:768px){
          .tapin-scope .tapin-columns-2 { grid-template-columns:1fr; }
          .tapin-scope .tapin-actions { flex-direction:column; align-items:stretch; }
          .tapin-scope .tapin-btn { width:100%; }
        }';
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
