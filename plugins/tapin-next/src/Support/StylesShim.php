<?php
namespace Tapin\Events\Support;

final class StylesShim {
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'inject'], 99);
        add_action('admin_enqueue_scripts', [$this, 'inject'], 99);
    }

    public function inject(): void {
        $css = Assets::combinedCss(
            Assets::sharedCss(),
            Assets::repeaterCss(),
            Assets::saleWindowsCss()
        );

        if (!wp_style_is('tapin-next-inline', 'enqueued')) {
            wp_register_style('tapin-next-inline', false);
            wp_enqueue_style('tapin-next-inline');
        }
        wp_add_inline_style('tapin-next-inline', $css);
    }
}
