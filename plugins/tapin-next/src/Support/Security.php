<?php
namespace Tapin\Events\Support;

final class SecurityResult {
    public bool $allowed;
    public ?\WP_User $user;
    public string $message;

    public function __construct(bool $allowed, string $message = '', ?\WP_User $user = null) {
        $this->allowed = $allowed;
        $this->message = $message;
        $this->user    = $user;
    }
}

final class Security {
    public static function loggedIn(?string $redirect = null): SecurityResult {
        if (is_user_logged_in()) {
            return new SecurityResult(true, '', wp_get_current_user());
        }
        status_header(403);
        return new SecurityResult(false, self::loginMessage($redirect));
    }

    public static function manager(): SecurityResult {
        $login = self::loggedIn();
        if (!$login->allowed) {
            return $login;
        }
        if (Cap::isManager()) {
            return new SecurityResult(true, '', $login->user);
        }
        status_header(403);
        return new SecurityResult(false, self::forbiddenMessage('הדף להנהלה בלבד.'));
    }

    public static function producer(bool $allowManagers = true): SecurityResult {
        $login = self::loggedIn();
        if (!$login->allowed) {
            return $login;
        }

        $user  = $login->user;
        $roles = (array) ($user ? $user->roles : []);

        if ($allowManagers && Cap::isManager()) {
            return new SecurityResult(true, '', $user);
        }

        if (array_intersect($roles, ['producer', 'owner'])) {
            return new SecurityResult(true, '', $user);
        }

        status_header(403);
        return new SecurityResult(false, self::forbiddenMessage('הדף מיועד למפיקים בלבד.'));
    }

    public static function forbiddenMessage(string $text): string {
        return self::wrapMessage(esc_html($text), 'error');
    }

    public static function successMessage(string $text): string {
        return self::wrapMessage(esc_html($text), 'success');
    }

    private static function loginMessage(?string $redirect = null): string {
        $target = $redirect;
        if ($target === null) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $target     = $requestUri !== '' ? $requestUri : home_url('/');
        }
        $loginUrl = function_exists('wp_login_url') ? wp_login_url($target) : wp_login_url();
        $link     = '<a href="' . esc_url($loginUrl) . '">התחברות</a>';
        return self::wrapMessage('יש להתחבר למערכת. ' . $link . '.', 'error');
    }

    private static function wrapMessage(string $html, string $type): string {
        $class = 'tapin-notice tapin-notice--' . $type;
        return sprintf(
            '<div class="%s" style="direction:rtl;text-align:right">%s</div>',
            esc_attr($class),
            wp_kses_post($html)
        );
    }
}
