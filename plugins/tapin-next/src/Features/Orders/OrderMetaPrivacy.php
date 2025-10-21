<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use WP_REST_Response;

final class OrderMetaPrivacy implements Service
{
    private const SENSITIVE_KEYS = [
        '_tapin_attendees_json',
        '_tapin_attendees',
        'Tapin Attendees',
        '_tapin_attendees_key',
    ];

    public function register(): void
    {
        add_filter('is_protected_meta', [$this, 'protectMeta'], 20, 3);
        add_filter('woocommerce_rest_prepare_shop_order_object', [$this, 'filterRestResponse'], 10, 3);
        add_filter('woocommerce_rest_prepare_shop_order_item_object', [$this, 'filterRestResponse'], 10, 3);
        add_filter('woocommerce_store_api_order_response', [$this, 'filterStoreApiOrder'], 10, 2);
    }

    public function protectMeta(bool $protected, string $metaKey, string $metaType): bool
    {
        if (in_array($metaKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return $protected;
    }

    /**
     * @param WP_REST_Response|mixed $response
     * @param mixed                   $object
     */
    public function filterRestResponse($response, $object, $request)
    {
        if (!$response instanceof WP_REST_Response) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        $data = $this->filterResponseData($data);
        $response->set_data($data);

        return $response;
    }

    /**
     * @param array<string,mixed> $response
     * @param mixed               $order
     * @return array<string,mixed>
     */
    public function filterStoreApiOrder(array $response, $order): array
    {
        return $this->filterResponseData($response);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function filterResponseData(array $data): array
    {
        if (isset($data['meta_data']) && is_array($data['meta_data'])) {
            $data['meta_data'] = $this->filterMetaEntries($data['meta_data']);
        }

        if (isset($data['line_items']) && is_array($data['line_items'])) {
            foreach ($data['line_items'] as &$line) {
                if (is_array($line) && isset($line['meta_data']) && is_array($line['meta_data'])) {
                    $line['meta_data'] = $this->filterMetaEntries($line['meta_data']);
                }
            }
            unset($line);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (is_array($item) && isset($item['meta_data']) && is_array($item['meta_data'])) {
                    $item['meta_data'] = $this->filterMetaEntries($item['meta_data']);
                }
            }
            unset($item);
        }

        return $data;
    }

    /**
     * @param array<int,mixed> $meta
     * @return array<int,mixed>
     */
    private function filterMetaEntries(array $meta): array
    {
        $filtered = [];
        foreach ($meta as $entry) {
            $key = '';
            if (is_array($entry)) {
                $key = isset($entry['key']) ? (string) $entry['key'] : '';
            } elseif (is_object($entry) && isset($entry->key)) {
                $key = (string) $entry->key;
            }

            if ($key !== '' && in_array($key, self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $filtered[] = $entry;
        }

        return array_values($filtered);
    }
}
