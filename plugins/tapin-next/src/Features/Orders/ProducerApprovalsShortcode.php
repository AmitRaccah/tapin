<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\ProducerApprovals\ProducerApprovalsFeature;
use Tapin\Events\Features\Orders\ProducerApprovals\ShortcodeController;
use Tapin\Events\Features\Orders\ProducerApprovals\ExportController;

final class ProducerApprovalsShortcode implements Service
{
    public function register(): void
    {
        // Delegate hook registration to the new modular feature registrar.
        (new ProducerApprovalsFeature())->register();
    }

    // Backwards-compatible endpoints if referenced directly elsewhere.
    public function render(): string
    {
        return ShortcodeController::render();
    }

    public function exportEvent(): void
    {
        (new ExportController())->handle();
    }
}

