<?php
namespace Tapin\Events\Features\Producers;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\ProducerProfiles;
use Tapin\Events\Support\Security;

final class RequestsManager implements Service {
    public function register(): void {
        add_shortcode('producer_requests_manager', [$this, 'render']);
    }

    public function render($atts = []): string {
        $guard = Security::manager();
        if (!$guard->allowed || !$guard->user) {
            return '<div class="tapin-scope tapin-center-container">' . $guard->message . '</div>';
        }

        $current = $guard->user;

        $flash_key = 'tapin_pm_flash_' . $current->ID;
        $msg_html  = '';
        $payload   = get_transient($flash_key);
        if ($payload) {
            delete_transient($flash_key);
            if (is_array($payload) && !empty($payload['html'])) {
                $msg_html = $payload['html'];
            }
        }

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['tapin_pm_nonce']) &&
            wp_verify_nonce($_POST['tapin_pm_nonce'], 'tapin_pm_action')
        ) {
            $uid    = (int) ($_POST['uid'] ?? 0);
            $action = sanitize_key($_POST['action_type'] ?? '');
            $user   = $uid ? get_user_by('id', $uid) : null;

            if ($user && in_array($action, ['approve', 'reject', 'remove'], true)) {
                $html = '';
                if ($action === 'approve') {
                    $user->remove_role('customer');
                    $user->add_role('producer');
                    update_user_meta($uid, 'producer_status', 'approved');
                    $html = '<div class="tapin-notice tapin-notice--success">הבקשה אושרה. המשתמש הוגדר כמפיק.</div>';
                } elseif ($action === 'reject') {
                    $user->remove_role('producer');
                    update_user_meta($uid, 'producer_status', 'rejected');
                    $html = '<div class="tapin-notice tapin-notice--warning">הבקשה נדחתה.</div>';
                } else { // remove
                    $user->remove_role('producer');
                    $user->add_role('customer');
                    delete_user_meta($uid, 'producer_status');
                    $html = '<div class="tapin-notice tapin-notice--warning">המפיק הוסר.</div>';
                }

                clean_user_cache($uid);
                if (function_exists('UM')) {
                    UM()->user()->remove_cache($uid);
                }

                set_transient($flash_key, ['html' => $html], 60);
                wp_safe_redirect(esc_url_raw(add_query_arg('pmf', '1', get_permalink())));
                exit;
            }
        }

        $pending_users = get_users([
            'meta_key'   => 'producer_status',
            'meta_value' => 'pending',
            'number'     => 200,
        ]);
        $producers = get_users([
            'role'    => 'producer',
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 999,
        ]);

        ob_start(); ?>
        <div class="tapin-scope tapin-center-container">
            <style>
                <?php echo ProducerProfiles::sharedCss(); ?>
                .tapin-manager-grid{display:grid;gap:16px}
                .tapin-request-card__cover{height:140px;background:var(--tapin-ghost-bg);border-radius:var(--tapin-radius-md);overflow:hidden;margin-bottom:12px}
                .tapin-request-card__cover img{width:100%;height:100%;object-fit:cover}
                .tapin-request-card__header{display:flex;gap:12px;align-items:flex-start}
                .tapin-request-card__avatar{width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--tapin-border-color)}
                .tapin-request-card__name{font-weight:700;font-size:1.1rem;color:var(--tapin-text-dark)}
                .tapin-request-card__meta{font-size:.9rem;color:var(--tapin-text-light);line-height:1.6}
                .tapin-request-card__about{margin-top:10px;padding-top:10px;border-top:1px solid var(--tapin-border-color)}
                .tapin-request-card__socials{margin-top:10px;padding-top:10px;border-top:1px solid var(--tapin-border-color);font-size:.9rem;line-height:1.7}
                .tapin-producers-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--tapin-border-color);border-radius:var(--tapin-radius-lg);overflow:hidden}
                .tapin-producers-table th,.tapin-producers-table td{padding:12px 16px;border-bottom:1px solid var(--tapin-border-color);text-align:right;vertical-align:middle}
                .tapin-producers-table thead th{background:var(--tapin-ghost-bg);font-weight:700}
                .tapin-producers-table .actions-cell{text-align:left}
                .tapin-cat-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border:1px solid var(--tapin-border-color);border-radius:999px;background:#fff;cursor:pointer;font-size:.85rem;margin:4px 6px 0 0}
                .tapin-cat-chip input{margin:0}
                #tapinProdTable{margin-top:10px}
                @media(max-width:768px){
                    .tapin-manager-grid{grid-template-columns:1fr}
                    .tapin-producers-table th,.tapin-producers-table td{padding:10px}
                    .tapin-producers-table .actions-cell{text-align:right}
                }
            </style>
            <?php echo $msg_html; ?>

            <h3 class="tapin-title">בקשות ממתינות</h3>
            <?php if (!empty($pending_users)): ?>
                <div class="tapin-manager-grid">
                    <?php foreach ($pending_users as $pending):
                        $uid   = $pending->ID;
                        $fields = ProducerProfiles::fieldDefaults($uid);
                        $cover = ProducerProfiles::umCoverUrl($uid, 'large');
                        $avatar = ProducerProfiles::umProfilePhotoUrl($uid, 'medium');
                    ?>
                    <form method="post" class="tapin-card" style="padding:18px">
                        <div class="tapin-request-card__cover">
                            <?php if ($cover): ?>
                                <img src="<?php echo esc_url($cover); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <div class="tapin-request-card__header">
                            <?php if ($avatar): ?>
                                <img class="tapin-request-card__avatar" src="<?php echo esc_url($avatar); ?>" alt="">
                            <?php endif; ?>
                            <div>
                                <div class="tapin-request-card__name"><?php echo esc_html($pending->display_name ?: $pending->user_login); ?></div>
                                <div class="tapin-request-card__meta">
                                    <div>דוא"ל: <a href="mailto:<?php echo esc_attr($pending->user_email); ?>"><?php echo esc_html($pending->user_email); ?></a></div>
                                    <?php if (!empty($fields['producer_phone_private'])): ?>
                                        <div>טלפון פנימי: <?php echo esc_html($fields['producer_phone_private']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($fields['producer_phone_public'])): ?>
                                        <div>טלפון לפרסום: <?php echo esc_html($fields['producer_phone_public']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($fields['producer_about'])): ?>
                        <div class="tapin-request-card__about"><?php echo wpautop(esc_html($fields['producer_about'])); ?></div>
                        <?php endif; ?>
                        <div class="tapin-request-card__socials">
                            <?php if (!empty($fields['producer_instagram'])): ?><a href="<?php echo esc_url($fields['producer_instagram']); ?>" target="_blank" rel="noopener">Instagram</a><br><?php endif; ?>
                            <?php if (!empty($fields['producer_facebook'])): ?><a href="<?php echo esc_url($fields['producer_facebook']); ?>" target="_blank" rel="noopener">Facebook</a><br><?php endif; ?>
                            <?php if (!empty($fields['producer_tiktok'])): ?><a href="<?php echo esc_url($fields['producer_tiktok']); ?>" target="_blank" rel="noopener">TikTok</a><br><?php endif; ?>
                            <?php if (!empty($fields['producer_youtube'])): ?><a href="<?php echo esc_url($fields['producer_youtube']); ?>" target="_blank" rel="noopener">YouTube</a><br><?php endif; ?>
                        </div>
                        <div class="tapin-actions">
                            <button type="submit" name="action_type" value="approve" class="tapin-btn tapin-btn--primary">אשר</button>
                            <button type="submit" name="action_type" value="reject" class="tapin-btn tapin-btn--danger" onclick="return confirm('לדחות את הבקשה?');">דחה</button>
                        </div>
                        <?php wp_nonce_field('tapin_pm_action', 'tapin_pm_nonce'); ?>
                        <input type="hidden" name="uid" value="<?php echo (int) $uid; ?>">
                    </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>אין בקשות ממתינות.</p>
            <?php endif; ?>

            <h3 class="tapin-title" style="margin-top:40px;">רשימת המפיקים</h3>
            <div class="tapin-form-row">
                <input id="tapinProdSearch" type="text" placeholder="חיפוש לפי שם / דוא&quot;ל / טלפון...">
            </div>
            <table class="tapin-producers-table" id="tapinProdTable">
                <thead>
                    <tr>
                        <th>שם</th>
                        <th>דוא&quot;ל</th>
                        <th>טלפון</th>
                        <th class="actions-cell">פעולות</th>
                    </tr>
                </thead>
                <tbody id="tapinProdTBody">
                    <?php if (!empty($producers)): foreach ($producers as $producer):
                        $pid       = (int) $producer->ID;
                        $name      = $producer->display_name ?: $producer->user_login;
                        $phone_pub = get_user_meta($pid, 'producer_phone_public', true);
                        $phone_priv = get_user_meta($pid, 'producer_phone_private', true);
                        $search_blob = strtolower($name . ' ' . $producer->user_email . ' ' . ($phone_pub ?: '') . ' ' . ($phone_priv ?: ''));
                    ?>
                    <tr data-name="<?php echo esc_attr($search_blob); ?>">
                        <td data-label="שם"><?php echo esc_html($name); ?></td>
                        <td data-label="דוא&quot;ל"><a href="mailto:<?php echo esc_attr($producer->user_email); ?>"><?php echo esc_html($producer->user_email); ?></a></td>
                        <td data-label="טלפון"><?php echo $phone_pub ? '<a href="tel:' . esc_attr($phone_pub) . '">' . esc_html($phone_pub) . '</a>' : '—'; ?></td>
                        <td data-label="פעולות" class="actions-cell">
                            <form method="post" onsubmit="return confirm('להסיר את המפיק מהרשימה?');" style="margin:0">
                                <?php wp_nonce_field('tapin_pm_action', 'tapin_pm_nonce'); ?>
                                <input type="hidden" name="uid" value="<?php echo (int) $pid; ?>">
                                <input type="hidden" name="action_type" value="remove">
                                <button type="submit" class="tapin-btn tapin-btn--danger">הסר</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" style="text-align:center;">אין מפיקים מאושרים.</td></tr>
                    <?php endif; ?>
                    <tr id="tapinProdNoRes" style="display:none"><td colspan="4" style="text-align:center;">אין תוצאות</td></tr>
                </tbody>
            </table>
        </div>
        <script>
        (function(){
          var input = document.getElementById('tapinProdSearch');
          var tbody = document.getElementById('tapinProdTBody');
          if(!input || !tbody) return;
          var rows = Array.from(tbody.querySelectorAll('tr[data-name]'));
          var nores = document.getElementById('tapinProdNoRes');
          function filterRows(){
            var q = input.value.trim().toLowerCase();
            var visible = 0;
            rows.forEach(function(tr){
              var text = tr.dataset.name || '';
              if (!q || text.includes(q)) { tr.style.display = ''; visible++; } else { tr.style.display = 'none'; }
            });
            if (nores) { nores.style.display = visible ? 'none' : ''; }
          }
          input.addEventListener('input', filterRows);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

