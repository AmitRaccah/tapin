<?php
namespace Tapin\Events\Support;

final class MetaKeys {
    public const SALE_WINDOWS = '_tapin_sale_windows';
    public const EVENT_DATE   = 'event_date';
    public const PAUSED       = '_sale_paused';
    public const EDIT_REQ     = 'tapin_edit_request';

    public static function define(): void {
        if (!defined('TAPIN_META_SALE_WINDOWS')) define('TAPIN_META_SALE_WINDOWS', self::SALE_WINDOWS);
        if (!defined('TAPIN_META_EVENT_DATE'))   define('TAPIN_META_EVENT_DATE',   self::EVENT_DATE);
        if (!defined('TAPIN_META_PAUSED'))       define('TAPIN_META_PAUSED',       self::PAUSED);
        if (!defined('TAPIN_META_EDIT_REQ'))     define('TAPIN_META_EDIT_REQ',     self::EDIT_REQ);
    }
}
