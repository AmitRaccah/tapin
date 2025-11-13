<?php

namespace Tapin\Events\Support\Notices;

/**
 * Shared notice normalizer to keep wc_add_notice payloads consistent.
 */
trait StringifyNoticeTrait
{
    /**
     * @param mixed $value
     */
    private function stringifyNotice($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }
}

