<?php
namespace Tapin\Events\Support;

use Tapin\Events\Core\Service;

/**
 * Back-compat shim – the OLD MU stack expected this service to exist.
 * All relevant save logic now lives in EventProductService; we keep a
 * no-op implementation to avoid missing-class notices.
 */
final class SaleWindowsSaver implements Service {
    public function register(): void {
        // Intentionally empty.
    }
}
