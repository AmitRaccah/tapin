<?php
namespace Tapin\Events\Core;

class Plugin {
    private function safeRegister(string $class): void {
        try {
            if (!class_exists($class)) { error_log("[Tapin Next] Missing class: {$class}"); return; }
            $obj = null;
            try { $obj = new $class(); } catch (\Throwable $e) { error_log("[Tapin Next] Failed to instantiate {$class}: ".$e->getMessage()); return; }
            try { if (method_exists($obj,'register')) { $obj->register(); } else { error_log("[Tapin Next] {$class} has no register() method"); } }
            catch (\Throwable $e) { error_log("[Tapin Next] register() crashed in {$class}: ".$e->getMessage()); }
        } catch (\Throwable $e) { error_log("[Tapin Next] safeRegister fatal for {$class}: ".$e->getMessage()); }
    }

    private function tryRequire(string $path): void {
        if (file_exists($path)) require_once $path;
    }

    public function boot(array $cfg = []): void {
        $sandbox = !empty($cfg['sandbox']);

        if (class_exists('\Tapin\Events\Support\MetaKeys')) { \Tapin\Events\Support\MetaKeys::define(); }

        $src = dirname(__DIR__);
        $this->tryRequire($src.'/Support/StylesShim.php');
        $this->tryRequire($src.'/Support/SaleWindowsSaver.php');
        $this->tryRequire($src.'/Domain/SaleWindowsRepository.php');
        $this->tryRequire($src.'/Features/ProductPage/SaleWindowsCards.php');

        $this->safeRegister(\Tapin\Events\Support\Compat::class);
        $this->safeRegister(\Tapin\Events\Support\StylesShim::class);
        $this->safeRegister(\Tapin\Events\Support\SaleWindowsSaver::class);

        $this->safeRegister(\Tapin\Events\Features\Shortcodes\ProducerEventRequest::class);
        $this->safeRegister(\Tapin\Events\Features\Shortcodes\EventsAdminCenter::class);
        $this->safeRegister(\Tapin\Events\Features\Orders\ProducerApprovalsShortcode::class);
        $this->safeRegister(\Tapin\Events\Features\Shortcodes\ProducerEventSales::class);

        $this->safeRegister(\Tapin\Events\Features\ProductPage\SaleWindowsCards::class);
        $this->safeRegister(\Tapin\Events\Features\Orders\AwaitingProducerStatus::class);
        $this->safeRegister(\Tapin\Events\Features\UM\ProfileCompletion::class);
        $this->safeRegister(\Tapin\Events\Features\Producers\RequestsManager::class);

        $this->tryRequire($src.'/Features/Infra/ss3-cache-bridge.php');

        if (class_exists('\WooCommerce')) { $this->safeRegister(\Tapin\Events\Features\PricingOverrides::class); }
        if ($sandbox) { return; }
    }
}
