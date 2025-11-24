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
        $paymentMethod    = $order->get_payment_method();
        $gateway          = self::getGateway($order);
        $normalizedAmount = $amount !== null ? max(0.0, (float) $amount) : null;

        $captureCallbacks = [];

        if ($normalizedAmount !== null && $normalizedAmount > 0 && self::supportsPartialCapture($gateway)) {
            if ($gateway && method_exists($gateway, 'capture_charge')) {
                $captureCallbacks[] = static function () use ($gateway, $order, $normalizedAmount) {
                    return $gateway->capture_charge($order, $normalizedAmount);
                };
            } elseif ($gateway && method_exists($gateway, 'process_capture')) {
                $captureCallbacks[] = static function () use ($gateway, $order, $normalizedAmount) {
                    return $gateway->process_capture($order, $normalizedAmount);
                };
            } elseif ($paymentMethod && has_action('woocommerce_order_action_' . $paymentMethod . '_capture_charge')) {
                $captureCallbacks[] = static function () use ($order, $paymentMethod, $normalizedAmount) {
                    return do_action('woocommerce_order_action_' . $paymentMethod . '_capture_charge', $order, $normalizedAmount);
                };
            } elseif ($paymentMethod && has_action('woocommerce_order_action_' . $paymentMethod . '_capture')) {
                $captureCallbacks[] = static function () use ($order, $paymentMethod, $normalizedAmount) {
                    return do_action('woocommerce_order_action_' . $paymentMethod . '_capture', $order, $normalizedAmount);
                };
            }
        }

        if ($paymentMethod && strpos($paymentMethod, 'wcpay') !== false && has_action('woocommerce_order_action_wcpay_capture_charge')) {
            $captureCallbacks[] = static function () use ($order) {
                return do_action('woocommerce_order_action_wcpay_capture_charge', $order);
            };
        }

        if ($paymentMethod && strpos($paymentMethod, 'stripe') !== false && has_action('woocommerce_order_action_stripe_capture_charge')) {
            $captureCallbacks[] = static function () use ($order) {
                return do_action('woocommerce_order_action_stripe_capture_charge', $order);
            };
        }

        if ($captureCallbacks === []) {
            self::addFailureNote($order, __('Tapin: capture could not be attempted for this gateway. Please capture manually.', 'tapin'));
            return false;
        }

        foreach ($captureCallbacks as $callback) {
            try {
                $result = $callback();
                if (self::didCaptureSucceed($result)) {
                    return true;
                }
            } catch (\Throwable $e) {
                self::addFailureNote(
                    $order,
                    sprintf(
                        /* translators: 1: gateway id 2: error message */
                        __('Tapin: capture failed for %1$s. %2$s', 'tapin'),
                        $paymentMethod !== '' ? $paymentMethod : __('payment gateway', 'tapin'),
                        $e->getMessage()
                    )
                );
                return false;
            }
        }

        self::addFailureNote(
            $order,
            sprintf(
                /* translators: %s payment method id */
                __('Tapin: capture failed for %s. Please capture manually.', 'tapin'),
                $paymentMethod !== '' ? $paymentMethod : __('payment gateway', 'tapin')
            )
        );

        return false;
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

    /**
     * @param mixed $result
     */
    private static function didCaptureSucceed($result): bool
    {
        if ($result instanceof \WP_Error) {
            return false;
        }

        if (is_array($result) && isset($result['result'])) {
            $value = $result['result'];
            if (is_string($value)) {
                return strtolower($value) === 'success';
            }

            return (bool) $value;
        }

        if (is_object($result) && isset($result->result)) {
            $value = $result->result;
            if (is_string($value)) {
                return strtolower($value) === 'success';
            }

            return (bool) $value;
        }

        if ($result === false) {
            return false;
        }

        return $result === null ? true : (bool) $result;
    }

    private static function addFailureNote(WC_Order $order, string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }

        $order->add_order_note($message);
        $order->save();
    }
}
