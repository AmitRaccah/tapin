<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

use WC_Order;
use WC_Payment_Gateway;

final class PaymentGatewayHelper
{
    public static function getGateway(WC_Order $order): ?WC_Payment_Gateway
    {
        if (!function_exists('WC')) {
            return null;
        }

        $gateways = WC()->payment_gateways();
        if (!is_object($gateways) || !method_exists($gateways, 'payment_gateways')) {
            return null;
        }

        $list = $gateways->payment_gateways();
        if (!is_array($list)) {
            return null;
        }

        $method = $order->get_payment_method();
        if ($method === '' || !isset($list[$method])) {
            return null;
        }

        $gateway = $list[$method];

        return $gateway instanceof WC_Payment_Gateway ? $gateway : null;
    }

    public static function supportsPartialCapture(?WC_Payment_Gateway $gateway): bool
    {
        if (!$gateway instanceof WC_Payment_Gateway) {
            return false;
        }

        $supports = method_exists($gateway, 'supports') ? $gateway->supports('partial_capture') : false;
        if (!$supports && method_exists($gateway, 'supports')) {
            $supports = $gateway->supports('captures') || $gateway->supports('capture');
        }

        if ($supports) {
            return true;
        }

        $id = isset($gateway->id) ? (string) $gateway->id : '';

        return $id !== '' && (strpos($id, 'stripe') !== false || strpos($id, 'wcpay') !== false);
    }

    public static function capture(WC_Order $order, ?float $amount = null): bool
    {
        $didCapture = false;
        $paymentMethod = $order->get_payment_method();
        $gateway = self::getGateway($order);

        $normalizedAmount = $amount !== null ? max(0.0, (float) $amount) : null;

        if ($normalizedAmount !== null && $normalizedAmount > 0 && self::supportsPartialCapture($gateway)) {
            if ($gateway && method_exists($gateway, 'capture_charge')) {
                $gateway->capture_charge($order, $normalizedAmount);
                $didCapture = true;
            } elseif ($gateway && method_exists($gateway, 'process_capture')) {
                $gateway->process_capture($order, $normalizedAmount);
                $didCapture = true;
            } elseif ($paymentMethod && has_action('woocommerce_order_action_' . $paymentMethod . '_capture_charge')) {
                do_action('woocommerce_order_action_' . $paymentMethod . '_capture_charge', $order, $normalizedAmount);
                $didCapture = true;
            } elseif ($paymentMethod && has_action('woocommerce_order_action_' . $paymentMethod . '_capture')) {
                do_action('woocommerce_order_action_' . $paymentMethod . '_capture', $order, $normalizedAmount);
                $didCapture = true;
            }
        }

        if (!$didCapture && $paymentMethod && strpos($paymentMethod, 'wcpay') !== false && has_action('woocommerce_order_action_wcpay_capture_charge')) {
            do_action('woocommerce_order_action_wcpay_capture_charge', $order);
            $didCapture = true;
        }

        if (!$didCapture && $paymentMethod && strpos($paymentMethod, 'stripe') !== false && has_action('woocommerce_order_action_stripe_capture_charge')) {
            do_action('woocommerce_order_action_stripe_capture_charge', $order);
            $didCapture = true;
        }

        return $didCapture;
    }

    public static function maybeRefund(WC_Order $order, float $amount, string $reason = ''): bool
    {
        $gateway = self::getGateway($order);
        if (!$gateway instanceof WC_Payment_Gateway || !method_exists($gateway, 'supports') || !$gateway->supports('refunds')) {
            return false;
        }

        $target = max(0.0, $amount);
        if ($target <= 0.0) {
            return false;
        }

        try {
            $result = $gateway->process_refund($order->get_id(), $target, $reason);
            return $result === true || $result instanceof \WC_Order_Refund;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
