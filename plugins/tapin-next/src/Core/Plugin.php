<?php
namespace Tapin\Events\Core;

use Tapin\Events\Support\MetaKeys;

class Plugin {
    private const FILE_INCLUDES = [
        'Support/StylesShim.php',
        'Support/ThanksNotice.php',
        'Domain/SaleWindowsRepository.php',
        'Integrations/smart-slider-compat.php',
        'Features/ProductPage/SaleWindowsCards.php',
    ];

    private const BASE_SERVICES = [
        \Tapin\Events\Support\Compat::class,
        \Tapin\Events\Support\Capabilities::class,
        \Tapin\Events\Support\StylesShim::class,
        \Tapin\Events\Support\ThanksNotice::class,
        \Tapin\Events\Integrations\SmartSliderCache::class,
        \Tapin\Events\Features\Producers\Portal::class,
        \Tapin\Events\Features\Producers\RequestsManager::class,
        \Tapin\Events\Features\Rest\ProducersController::class,
        \Tapin\Events\Features\Shortcodes\ProducerEventsCenter::class,
        \Tapin\Events\Features\Shortcodes\ProducerEventsGrid::class,
    ];

    private const WC_SERVICES = [
        \Tapin\Events\Features\Shortcodes\ProducerEventRequest::class,
        \Tapin\Events\Features\Shortcodes\EventsAdminCenter::class,
        \Tapin\Events\Features\Shortcodes\ProducerEventSales::class,
        \Tapin\Events\Features\Orders\ProducerApprovals\ProducerApprovalsFeature::class,
        \Tapin\Events\Features\Orders\OrderMetaPrivacy::class,
        \Tapin\Events\Features\Orders\AbandonedCheckoutCartCleanup::class,
        \Tapin\Events\Features\ProductPage\SaleWindowsCards::class,
        \Tapin\Events\Features\ProductPage\EventDetailsCards::class,
        \Tapin\Events\Features\ProductPage\ProductBackground::class,
        \Tapin\Events\Features\ProductPage\StickyPurchaseBar::class,
        \Tapin\Events\Features\ProductPage\PurchaseDetailsModal::class,
        \Tapin\Events\Features\Orders\AwaitingProducerStatus::class,
        \Tapin\Events\Features\Orders\PartiallyApprovedStatus::class,
        \Tapin\Events\Features\Orders\AwaitingProducerGate::class,
        \Tapin\Events\Features\Orders\TicketEmails\TicketEmailDispatcher::class,
        \Tapin\Events\Features\Orders\Email\EmailsService::class,
        \Tapin\Events\Features\Shortcodes\TicketCheckin::class,
        \Tapin\Events\Features\Shortcodes\ProducerTicketDashboard::class,
        \Tapin\Events\Features\PricingOverrides::class,
        \Tapin\Events\Features\PurchasableGate::class,
        \Tapin\Events\Integrations\Affiliate\AffiliateService::class,
    ];

    private function safeRegister(string $class): void {
        try {
            if (!class_exists($class)) {
                $this->maybeLog("Missing class: {$class}");
                return;
            }

            $obj = null;
            try {
                $obj = new $class();
            } catch (\Throwable $e) {
                $this->maybeLog("Failed to instantiate {$class}: " . $e->getMessage());
                return;
            }

            try {
                if ($obj instanceof Service || method_exists($obj, 'register')) {
                    $obj->register();
                } else {
                    $this->maybeLog("{$class} has no register() method");
                }
            } catch (\Throwable $e) {
                $this->maybeLog("register() crashed in {$class}: " . $e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->maybeLog("safeRegister fatal for {$class}: " . $e->getMessage());
        }
    }

    private function tryRequire(string $path): void {
        if (file_exists($path)) {
            require_once $path;
        }
    }

    public function boot(array $cfg = []): void {
        $sandbox = !empty($cfg['sandbox']);

        if (class_exists(MetaKeys::class)) {
            MetaKeys::define();
        }

        $src = dirname(__DIR__);
        foreach (self::FILE_INCLUDES as $relative) {
            $this->tryRequire($src . '/' . $relative);
        }

        foreach (self::BASE_SERVICES as $class) {
            $this->safeRegister($class);
        }

        if (class_exists('\WooCommerce')) {
            foreach (self::WC_SERVICES as $class) {
                $this->safeRegister($class);
            }
        }

        if ($sandbox) {
            return;
        }
    }

    private function maybeLog(string $message): void {
        $shouldLog = defined('TAPIN_NEXT_DEBUG')
            ? (bool) TAPIN_NEXT_DEBUG
            : (defined('WP_DEBUG') && WP_DEBUG);

        if ($shouldLog) {
            error_log('[Tapin Next] ' . $message);
        }
    }
}
