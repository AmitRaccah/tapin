<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class Assets
{
    public const HANDLE = 'tapin-pa';

    public static function enqueue(): void
    {
        $baseFile = \defined('TAPIN_NEXT_PATH') ? (string) \TAPIN_NEXT_PATH . '/tapin.php' : __FILE__;
        $baseDir  = dirname($baseFile);

        $cssRel = 'assets/producer-approvals.css';
        $jsRel  = 'assets/producer-approvals.js';

        $cssUrl = plugins_url($cssRel, $baseFile);
        $jsUrl  = plugins_url($jsRel,  $baseFile);

        $cssPath = $baseDir . '/' . $cssRel;
        $jsPath  = $baseDir . '/' . $jsRel;

        $cssVer = file_exists($cssPath) ? (string) filemtime($cssPath) : null;
        $jsVer  = file_exists($jsPath)  ? (string) filemtime($jsPath)  : null;

        wp_register_style(self::HANDLE, $cssUrl, [], $cssVer);
        wp_register_script(self::HANDLE, $jsUrl, [], $jsVer, true);

        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);
    }
}

