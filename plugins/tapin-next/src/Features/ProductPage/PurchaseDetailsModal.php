<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\ProductPage\PurchaseModal\PurchaseModalServiceProvider;

final class PurchaseDetailsModal implements Service
{
    private PurchaseModalServiceProvider $provider;

    public function __construct(?PurchaseModalServiceProvider $provider = null)
    {
        $this->provider = $provider ?? new PurchaseModalServiceProvider();
    }

    public function register(): void
    {
        $this->provider->register();
    }
}
