<?php
namespace Tapin\Events\Support;

final class MetaKeys {
    public const SALE_WINDOWS   = '_tapin_sale_windows';
    public const EVENT_DATE     = 'event_date';
    public const PAUSED         = '_sale_paused';
    public const EDIT_REQ       = 'tapin_edit_request';
    public const EVENT_BG_IMAGE = '_tapin_event_bg_image';

    public static function define(): void {
        if (!defined('TAPIN_META_SALE_WINDOWS'))   define('TAPIN_META_SALE_WINDOWS',   self::SALE_WINDOWS);
        if (!defined('TAPIN_META_EVENT_DATE'))     define('TAPIN_META_EVENT_DATE',     self::EVENT_DATE);
        if (!defined('TAPIN_META_PAUSED'))         define('TAPIN_META_PAUSED',         self::PAUSED);
        if (!defined('TAPIN_META_EDIT_REQ'))       define('TAPIN_META_EDIT_REQ',       self::EDIT_REQ);
        if (!defined('TAPIN_META_EVENT_BG_IMAGE')) define('TAPIN_META_EVENT_BG_IMAGE', self::EVENT_BG_IMAGE);
    }
}
