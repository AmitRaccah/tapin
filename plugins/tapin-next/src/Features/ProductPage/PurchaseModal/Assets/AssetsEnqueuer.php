<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Assets;

use Tapin\Events\Features\ProductPage\PurchaseModal\Constants;
use Tapin\Events\Features\ProductPage\PurchaseModal\Fields\FieldDefinitionsProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Guards\ProductGuards;
use Tapin\Events\Features\ProductPage\PurchaseModal\Messaging\MessagesProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Features\ProductPage\PurchaseModal\Users\TransparentUserManager;

final class AssetsEnqueuer
{
    private ProductGuards $guards;
    private TicketTypeCache $ticketTypeCache;
    private MessagesProvider $messages;
    private TransparentUserManager $userManager;
    private FieldDefinitionsProvider $fields;

    public function __construct(
        ProductGuards $guards,
        TicketTypeCache $ticketTypeCache,
        MessagesProvider $messages,
        TransparentUserManager $userManager,
        FieldDefinitionsProvider $fields
    ) {
        $this->guards = $guards;
        $this->ticketTypeCache = $ticketTypeCache;
        $this->messages = $messages;
        $this->userManager = $userManager;
        $this->fields = $fields;
    }

    public function enqueueAssets(): void
    {
        if (!$this->guards->isEligibleProduct()) {
            return;
        }

        $assetsDirPath = Constants::assetsDirPath();
        $assetsDirUrl = Constants::assetsDirUrl();

        wp_enqueue_style(
            Constants::STYLE_HANDLE,
            $assetsDirUrl . 'purchase-modal.css',
            [],
            $this->assetVersion($assetsDirPath . 'purchase-modal.css')
        );

        $productId = (int) get_the_ID();
        $ticketCache = $productId ? $this->ticketTypeCache->ensureTicketTypeCache($productId) : ['list' => [], 'index' => []];

        $modalData = [
            'prefill'     => $this->userManager->getPrefillData(),
            'ticketTypes' => $ticketCache['list'],
            'messages'    => $this->messages->getModalMessages(),
            'fields'      => $this->fields->getDefinitions(),
        ];

        $scriptBaseHandle = Constants::SCRIPT_HANDLE;
        $scriptBasePath = $assetsDirPath . 'js/tapin-purchase/';
        $scriptBaseUrl = $assetsDirUrl . 'js/tapin-purchase/';

        $utilsHandle = $scriptBaseHandle . '-utils';
        $messagesHandle = $scriptBaseHandle . '-messages';
        $domHandle = $scriptBaseHandle . '-dom';
        $ticketsHandle = $scriptBaseHandle . '-tickets';
        $planHandle = $scriptBaseHandle . '-plan';
        $formHandle = $scriptBaseHandle . '-form';
        $modalHandle = $scriptBaseHandle . '-modal';
        $indexHandle = $scriptBaseHandle . '-index';

        wp_register_script(
            $utilsHandle,
            $scriptBaseUrl . 'tapin.utils.js',
            [],
            $this->assetVersion($scriptBasePath . 'tapin.utils.js'),
            true
        );

        wp_add_inline_script(
            $utilsHandle,
            'window.TapinPurchaseModalData = ' . wp_json_encode($modalData) . ';',
            'before'
        );

        wp_register_script(
            $messagesHandle,
            $scriptBaseUrl . 'tapin.messages.js',
            [$utilsHandle],
            $this->assetVersion($scriptBasePath . 'tapin.messages.js'),
            true
        );

        wp_register_script(
            $domHandle,
            $scriptBaseUrl . 'tapin.dom.js',
            [$messagesHandle],
            $this->assetVersion($scriptBasePath . 'tapin.dom.js'),
            true
        );

        wp_register_script(
            $ticketsHandle,
            $scriptBaseUrl . 'tapin.tickets.js',
            [$domHandle],
            $this->assetVersion($scriptBasePath . 'tapin.tickets.js'),
            true
        );

        wp_register_script(
            $planHandle,
            $scriptBaseUrl . 'tapin.plan.js',
            [$ticketsHandle],
            $this->assetVersion($scriptBasePath . 'tapin.plan.js'),
            true
        );

        wp_register_script(
            $formHandle,
            $scriptBaseUrl . 'tapin.form.js',
            [$planHandle],
            $this->assetVersion($scriptBasePath . 'tapin.form.js'),
            true
        );

        wp_register_script(
            $modalHandle,
            $scriptBaseUrl . 'tapin.modal.js',
            [$formHandle],
            $this->assetVersion($scriptBasePath . 'tapin.modal.js'),
            true
        );

        wp_register_script(
            $indexHandle,
            $scriptBaseUrl . 'tapin.index.js',
            [$modalHandle],
            $this->assetVersion($scriptBasePath . 'tapin.index.js'),
            true
        );

        wp_enqueue_script($indexHandle);
    }

    private function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }
}
