<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal;

final class Constants
{
    public const SCRIPT_HANDLE = 'tapin-purchase-modal';
    public const STYLE_HANDLE = 'tapin-purchase-modal';
    public const SESSION_KEY_PENDING = 'tapin_pending_checkout';
    public const PRICE_OVERRIDE_META = '_tapin_price_override';

    private function __construct()
    {
    }

    public static function baseFile(): string
    {
        return dirname(__DIR__) . '/PurchaseDetailsModal.php';
    }

    public static function assetsDirPath(): string
    {
        return trailingslashit(dirname(self::baseFile())) . 'assets/';
    }

    public static function assetsDirUrl(): string
    {
        return trailingslashit(plugin_dir_url(self::baseFile())) . 'assets/';
    }
}
