<?php
namespace Tapin\Events\Support;

final class MetaKeys {
    public const SALE_WINDOWS        = '_tapin_sale_windows';
    public const EVENT_DATE          = 'event_date';
    public const PAUSED              = '_sale_paused';
    public const EDIT_REQ            = 'tapin_edit_request';
    public const EVENT_BG_IMAGE      = '_tapin_event_bg_image';
    public const EVENT_ADDRESS       = '_tapin_event_address';
    public const EVENT_CITY          = '_tapin_event_city';
    public const EVENT_MIN_AGE       = '_tapin_event_min_age';
    public const TICKET_TYPES        = '_tapin_ticket_types';
    public const TICKET_TYPE_SALES   = '_tapin_ticket_type_sales';
    public const PRODUCER_AFF_TYPE   = '_tapin_producer_aff_type';
    public const PRODUCER_AFF_AMOUNT = '_tapin_producer_aff_amount';
    public const TICKET_FEE_PERCENT  = '_tapin_ticket_fee_percent';

    public static function define(): void {
        if (!defined('TAPIN_META_SALE_WINDOWS'))   define('TAPIN_META_SALE_WINDOWS',   self::SALE_WINDOWS);
        if (!defined('TAPIN_META_EVENT_DATE'))     define('TAPIN_META_EVENT_DATE',     self::EVENT_DATE);
        if (!defined('TAPIN_META_PAUSED'))         define('TAPIN_META_PAUSED',         self::PAUSED);
        if (!defined('TAPIN_META_EDIT_REQ'))       define('TAPIN_META_EDIT_REQ',       self::EDIT_REQ);
        if (!defined('TAPIN_META_EVENT_BG_IMAGE')) define('TAPIN_META_EVENT_BG_IMAGE', self::EVENT_BG_IMAGE);
        if (!defined('TAPIN_META_EVENT_ADDRESS')) define('TAPIN_META_EVENT_ADDRESS', self::EVENT_ADDRESS);
        if (!defined('TAPIN_META_EVENT_CITY')) define('TAPIN_META_EVENT_CITY', self::EVENT_CITY);
        if (!defined('TAPIN_META_EVENT_MIN_AGE')) define('TAPIN_META_EVENT_MIN_AGE', self::EVENT_MIN_AGE);
        if (!defined('TAPIN_META_TICKET_TYPES'))   define('TAPIN_META_TICKET_TYPES',   self::TICKET_TYPES);
        if (!defined('TAPIN_META_TICKET_TYPE_SALES')) define('TAPIN_META_TICKET_TYPE_SALES', self::TICKET_TYPE_SALES);
        if (!defined('TAPIN_META_PRODUCER_AFF_TYPE')) define('TAPIN_META_PRODUCER_AFF_TYPE', self::PRODUCER_AFF_TYPE);
        if (!defined('TAPIN_META_PRODUCER_AFF_AMOUNT')) define('TAPIN_META_PRODUCER_AFF_AMOUNT', self::PRODUCER_AFF_AMOUNT);
        if (!defined('TAPIN_META_TICKET_FEE_PERCENT')) {
            define('TAPIN_META_TICKET_FEE_PERCENT', self::TICKET_FEE_PERCENT);
        }
    }
}
