<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

use Tapin\Events\Features\ProductPage\PurchaseModal\Constants;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Support\TicketTypeTracer;
use WC_Product;

final class PricingAdjuster
{
    private TicketTypeCache $ticketTypeCache;

    public function __construct(TicketTypeCache $ticketTypeCache)
    {
        $this->ticketTypeCache = $ticketTypeCache;
    }

    public function applyAttendeePricing($cart): void
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $key => $item) {
            $incomingItemPrice = isset($item['tapin_ticket_price']) ? (float) $item['tapin_ticket_price'] : null;
            $incomingAttendeePrice = !empty($item['tapin_attendees'][0]['ticket_price']) ? (float) $item['tapin_attendees'][0]['ticket_price'] : null;
            $price = $incomingItemPrice ?? $incomingAttendeePrice;
            $source = null;
            $typeId = !empty($item['tapin_attendees'][0]['ticket_type'])
                ? sanitize_key((string) $item['tapin_attendees'][0]['ticket_type'])
                : '';
            $productId = 0;
            if (!empty($item['product_id'])) {
                $productId = (int) $item['product_id'];
            } elseif (!empty($item['data']) && method_exists($item['data'], 'get_id')) {
                $productId = (int) $item['data']->get_id();
            }

            if ($incomingItemPrice !== null) {
                $source = 'item';
            } elseif ($incomingAttendeePrice !== null) {
                $source = 'attendee';
            }

            if ($price === null && $productId > 0) {
                $cache = $this->ticketTypeCache->ensureTicketTypeCache($productId);
                $index = $cache['index'];

                if ($typeId !== '' && !empty($index[$typeId]) && isset($index[$typeId]['price'])) {
                    $price = (float) $index[$typeId]['price'];
                    $source = 'cache';
                } elseif ($typeId === '') {
                    $label = !empty($item['tapin_attendees'][0]['ticket_type_label'])
                        ? (string) $item['tapin_attendees'][0]['ticket_type_label']
                        : '';
                    $needle = \Tapin\Events\Support\AttendeeFields::normalizeLabel($label);
                    if ($needle !== '') {
                        foreach ($index as $idCandidate => $meta) {
                            $name = isset($meta['name']) ? (string) $meta['name'] : '';
                            if ($name !== '' && \Tapin\Events\Support\AttendeeFields::labelsEqual($name, $label)) {
                                $typeId = (string) $idCandidate;
                                if (isset($meta['price'])) {
                                    $price = (float) $meta['price'];
                                    $source = 'cache_label';
                                }
                                break;
                            }
                        }
                    }
                }
            }

            if (class_exists(TicketTypeTracer::class) && method_exists(TicketTypeTracer::class, 'applyDetailed')) {
                TicketTypeTracer::applyDetailed((string) $key, (int) $productId, (string) $typeId, (string) ($source ?? ''), $incomingItemPrice, $incomingAttendeePrice, $price);
            } elseif (class_exists(TicketTypeTracer::class)) {
                TicketTypeTracer::apply((string) $key, $incomingItemPrice, $incomingAttendeePrice, $price);
            }

            if ($price === null) {
                continue;
            }

            $decimals = wc_get_price_decimals();
            $formattedPrice = wc_format_decimal($price, $decimals);
            $numericPrice = (float) $formattedPrice;
            $quantity = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
            $lineTotal = (float) wc_format_decimal($numericPrice * $quantity, $decimals);

            if (isset($cart->cart_contents[$key])) {
                $cart->cart_contents[$key]['tapin_ticket_price'] = $numericPrice;
                $cart->cart_contents[$key]['line_subtotal'] = $lineTotal;
                $cart->cart_contents[$key]['line_total'] = $lineTotal;
                if (isset($cart->cart_contents[$key]['line_subtotal_tax'])) {
                    $cart->cart_contents[$key]['line_subtotal_tax'] = 0.0;
                }
                if (isset($cart->cart_contents[$key]['line_tax'])) {
                    $cart->cart_contents[$key]['line_tax'] = 0.0;
                }
                if (isset($cart->cart_contents[$key]['line_tax_data']) && is_array($cart->cart_contents[$key]['line_tax_data'])) {
                    $taxData = $cart->cart_contents[$key]['line_tax_data'];
                    foreach (['total', 'subtotal'] as $taxKey) {
                        if (isset($taxData[$taxKey]) && is_array($taxData[$taxKey])) {
                            foreach ($taxData[$taxKey] as $taxRateId => $taxAmount) {
                                $taxData[$taxKey][$taxRateId] = 0.0;
                            }
                        }
                    }
                    $cart->cart_contents[$key]['line_tax_data'] = $taxData;
                }
            }

            if (isset($item['data']) && $item['data'] instanceof WC_Product) {
                $productObj = $item['data'];
                $productObj->set_regular_price($formattedPrice);
                $productObj->set_sale_price('');
                if (method_exists($productObj, 'set_date_on_sale_from')) {
                    $productObj->set_date_on_sale_from(null);
                }
                if (method_exists($productObj, 'set_date_on_sale_to')) {
                    $productObj->set_date_on_sale_to(null);
                }
                if (method_exists($productObj, 'set_on_sale')) {
                    $productObj->set_on_sale(false);
                }
                $productObj->set_price($formattedPrice);
                $productObj->update_meta_data(Constants::PRICE_OVERRIDE_META, $formattedPrice);
            }
        }
    }

    public function filterCartProductPrice($price, $product)
    {
        $override = $this->resolvePriceOverride($product);
        if ($override === null) {
            return $price;
        }

        return $override;
    }

    public function filterCartProductSalePrice($price, $product)
    {
        $override = $this->resolvePriceOverride($product);
        if ($override === null) {
            return $price;
        }

        return '';
    }

    public function filterCartProductIsOnSale($isOnSale, $product)
    {
        $override = $this->resolvePriceOverride($product);
        if ($override === null) {
            return $isOnSale;
        }

        return false;
    }

    private function resolvePriceOverride($product): ?string
    {
        if (!$product instanceof WC_Product) {
            return null;
        }

        $raw = $product->get_meta(Constants::PRICE_OVERRIDE_META, true);
        if ($raw === '' || $raw === null) {
            return null;
        }

        $decimals = wc_get_price_decimals();
        return wc_format_decimal($raw, $decimals);
    }
}
