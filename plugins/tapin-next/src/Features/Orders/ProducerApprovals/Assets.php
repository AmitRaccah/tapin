<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class Assets
{
    public const HANDLE = 'tapin-pa';

    public static function enqueue(): void
    {
        $baseFile = \defined('TAPIN_NEXT_PATH') ? (string) \TAPIN_NEXT_PATH . '/tapin.php' : __FILE__;
        $css  = plugins_url('assets/producer-approvals.css', $baseFile);
        $js   = plugins_url('assets/producer-approvals.js', $baseFile);

        wp_register_style(self::HANDLE, $css, [], null);
        wp_register_script(self::HANDLE, $js, [], null, true);

        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);
    }
}

