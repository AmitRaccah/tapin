<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Core\Service;

final class ProducerApprovalsFeature implements Service
{
    public function register(): void
    {
        add_shortcode('producer_order_approvals', [ShortcodeController::class, 'render']);
        add_action('admin_post_tapin_pa_export_event', [ExportController::class, 'handle']);
    }
}

