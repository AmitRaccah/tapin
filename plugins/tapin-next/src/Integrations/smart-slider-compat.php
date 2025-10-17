<?php

use Tapin\Events\Integrations\SmartSliderCache;

if (!function_exists('tapin_ss3_clear_all_sliders_cache')) {
    function tapin_ss3_clear_all_sliders_cache(): void
    {
        SmartSliderCache::clearStatically();
    }
}

