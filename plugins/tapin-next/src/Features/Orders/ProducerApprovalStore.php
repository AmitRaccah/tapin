<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders;

/**
 * Shared normalization helpers for producer approval meta maps.
 * Behavior mirrors existing inline implementations to avoid regressions.
 */
final class ProducerApprovalStore
{
    public const LEGACY_PRODUCER_ID = 0;

    /**
     * @param array<int|string,mixed> $indices
     * @return array<int,int>
     */
    public static function filterIndices(array $indices, int $limit = PHP_INT_MAX): array
    {
        $clean = [];
        foreach ($indices as $value) {
            $int = (int) $value;
            if ($int < 0) {
                continue;
            }
            $clean[] = $int;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        if ($limit > 0 && count($clean) > $limit) {
            $clean = array_slice($clean, 0, $limit);
        }

        return $clean;
    }

    /**
     * @param mixed $raw
     * @return array<int,float>
     */
    public static function normalizeProducerFloatMap($raw, ?int $producerId = null): array
    {
        $result = [];
        if (is_array($raw)) {
            foreach ($raw as $producerKey => $value) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (is_array($value)) {
                    continue;
                }
                $floatVal = max(0.0, (float) $value);
                if ($floatVal > 0.0) {
                    $result[$pid] = $floatVal;
                }
            }
        }

        if ($result !== []) {
            return $result;
        }

        if (is_numeric($raw)) {
            $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;
            $val    = max(0.0, (float) $raw);
            if ($val > 0.0) {
                $result[$target] = $val;
            }
        }

        return $result;
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,array<int,int>>>
     */
    public static function normalizeApprovedMetaByProducer($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_array($nested)) {
                        $hasNested = true;
                        break 2;
                    }
                }
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }

                $clean = self::normalizeApprovedMeta($map);
                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $clean = self::normalizeApprovedMeta($raw);
        if ($clean === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $clean];
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,int>>
     */
    public static function normalizeProducerPartialMap($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                $hasNested = true;
                break;
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }
                $clean = self::normalizePartialMap($map);
                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $legacy = self::normalizePartialMap($raw);
        if ($legacy === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $legacy];
    }

    /**
     * @param array<string|int,mixed> $meta
     * @return array<int,array<int,int>>
     */
    private static function normalizeApprovedMeta(array $meta): array
    {
        $result = [];
        foreach ($meta as $itemId => $indices) {
            $itemKey = (int) $itemId;
            if ($itemKey <= 0 || !is_array($indices)) {
                continue;
            }

            $clean = self::filterIndices($indices);
            if ($clean === []) {
                continue;
            }

            $result[$itemKey] = $clean;
        }

        return $result;
    }

    /**
     * @param array<int,int|string|float> $map
     * @return array<int,int>
     */
    private static function normalizePartialMap(array $map): array
    {
        $clean = [];
        foreach ($map as $itemId => $count) {
            $itemKey  = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }
            $clean[$itemKey] = $intCount;
        }

        return $clean;
    }
}
