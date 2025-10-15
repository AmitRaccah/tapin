<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;

final class ProducerRequestsManager implements Service {
    public function register(): void {
        add_shortcode('producer_requests_manager', [$this,'render']);
    }

    public function render($atts = [], $content = ''): string {
        return do_shortcode('[producer_event_request]');
    }
}
