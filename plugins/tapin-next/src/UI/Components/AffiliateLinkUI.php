<?php

namespace Tapin\Events\UI\Components;

final class AffiliateLinkUI
{
    private static $inlineScriptAdded = false;

    public static function renderForProduct(int $productId): string
    {
        if ($productId <= 0 || !function_exists('afwc_get_product_affiliate_url')) {
            return '';
        }

        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            return '';
        }

        $authorId = (int) get_post_field('post_author', $productId);
        if ($authorId !== $currentUserId) {
            return '';
        }

        self::enqueueAssets();

        $helpText = 'יש לאפשר Product referral URLs בהגדרות Affiliate For WooCommerce';
        $genericError = 'אירעה שגיאה בעת טעינת הלינק. נסו שוב.';
        $successText = 'הלינק הועתק.';
        $loadingText = 'טוען לינק...';
        $buttonLabel = 'העתקת לינק';
        $successButtonLabel = 'הלינק הועתק!';
        $statusText = '';

        if (current_user_can('manage_woocommerce') && get_option('afwc_show_product_referral_url', 'no') !== 'yes') {
            $statusText = $helpText;
        }

        $noteClasses = 'tapin-aff-link__note';
        if ($statusText !== '') {
            $noteClasses .= ' tapin-aff-link__note--error';
        }

        ob_start();
        ?>
        <div class="tapin-aff-link" dir="rtl" data-aff-link-wrapper>
          <button
            type="button"
            class="tapin-aff-link__btn"
            data-product-id="<?php echo (int) $productId; ?>"
            data-empty-help="<?php echo esc_attr($helpText); ?>"
            data-generic-error="<?php echo esc_attr($genericError); ?>"
            data-success-text="<?php echo esc_attr($successText); ?>"
            data-loading-text="<?php echo esc_attr($loadingText); ?>"
            data-label-default="<?php echo esc_attr($buttonLabel); ?>"
            data-label-loading="<?php echo esc_attr($loadingText); ?>"
            data-label-success="<?php echo esc_attr($successButtonLabel); ?>"
          ><?php echo esc_html($buttonLabel); ?></button>
          <small class="<?php echo esc_attr($noteClasses); ?>" data-aff-link-note><?php echo esc_html($statusText); ?></small>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function enqueueAssets(): void
    {
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('afwc-affiliate-link');
            wp_enqueue_script('afwc-click-to-copy');
        }

        if (!self::$inlineScriptAdded && function_exists('wp_add_inline_script')) {
            if (wp_script_is('afwc-affiliate-link', 'enqueued') || wp_script_is('afwc-affiliate-link', 'registered')) {
                wp_add_inline_script('afwc-affiliate-link', self::inlineScript(), 'after');
                self::$inlineScriptAdded = true;
            }
        }
    }

    private static function inlineScript(): string
    {
        return <<<JS
(function($){
    'use strict';
    if (window.TapinAffiliateLinkUiInit) {
        return;
    }
    if (!$) {
        return;
    }
    window.TapinAffiliateLinkUiInit = true;

    function findWrapper(node) {
        while (node && node.nodeType === 1) {
            if (node.hasAttribute && node.hasAttribute('data-aff-link-wrapper')) {
                return node;
            }
            node = node.parentElement;
        }
        return null;
    }

    function setMessage(node, text, isError) {
        var wrapper = findWrapper(node);
        if (!wrapper) {
            return;
        }
        var note = wrapper.querySelector('[data-aff-link-note]');
        if (!note) {
            return;
        }
        note.textContent = text || '';
        if (isError && text) {
            note.classList.add('tapin-aff-link__note--error');
        } else {
            note.classList.remove('tapin-aff-link__note--error');
        }
    }

    function legacyCopy(text) {
        try {
            var temp = document.createElement('textarea');
            temp.style.position = 'fixed';
            temp.style.opacity = '0';
            temp.value = text;
            document.body.appendChild(temp);
            temp.focus();
            temp.select();
            var ok = document.execCommand('copy');
            temp.remove();
            return ok;
        } catch (err) {
            return false;
        }
    }

    function copyText(btn, text) {
        if (!text) {
            return Promise.reject();
        }
        if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function(){
                if (legacyCopy(text)) {
                    return;
                }
                return Promise.reject();
            });
        }
        return legacyCopy(text) ? Promise.resolve() : Promise.reject();
    }

    function setButtonLabel(btn, attr) {
        var label = btn.getAttribute(attr) || btn.getAttribute('data-label-default') || '';
        if (label) {
            btn.textContent = label;
        }
    }

    function resetButtonLabel(btn) {
        setButtonLabel(btn, 'data-label-default');
        btn.classList.remove('tapin-aff-link__btn--success');
    }

    function indicateSuccess(btn) {
        setButtonLabel(btn, 'data-label-success');
        btn.classList.add('tapin-aff-link__btn--success');
        setTimeout(function(){
            resetButtonLabel(btn);
        }, 1800);
    }

    $(document).on('click', '.tapin-aff-link__btn', function(evt){
        var btn = this;
        evt.preventDefault();
        if (btn.getAttribute('data-loading') === '1') {
            return;
        }
        var existing = btn.getAttribute('data-ctp');
        if (existing) {
            copyText(btn, existing)
                .then(function(){
                    indicateSuccess(btn);
                    setMessage(btn, btn.getAttribute('data-success-text') || '', false);
                })
                .catch(function(){
                    resetButtonLabel(btn);
                    setMessage(btn, btn.getAttribute('data-generic-error') || '', true);
                });
            return;
        }
        var params = window.afwcAffiliateLinkParams && window.afwcAffiliateLinkParams.product ? window.afwcAffiliateLinkParams.product : null;
        if (!params || !params.ajaxURL) {
            setMessage(btn, btn.getAttribute('data-generic-error') || '', true);
            return;
        }
        var productId = parseInt(btn.getAttribute('data-product-id'), 10);
        if (!productId) {
            return;
        }
        var loadingText = btn.getAttribute('data-loading-text') || '';
        setButtonLabel(btn, 'data-label-loading');
        setMessage(btn, loadingText || '', false);
        btn.setAttribute('data-loading', '1');
        btn.classList.add('tapin-aff-link__btn--loading');

        $.post(params.ajaxURL, {
            product_id: productId,
            security: params.security || ''
        }).done(function(res){
            btn.setAttribute('data-loading', '0');
            btn.classList.remove('tapin-aff-link__btn--loading');
            var data = res;
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (err) {
                    data = null;
                }
            }
            if (data && data.success && data.data && data.data.url) {
                btn.setAttribute('data-ctp', data.data.url);
                copyText(btn, data.data.url)
                    .then(function(){
                        indicateSuccess(btn);
                        setMessage(btn, btn.getAttribute('data-success-text') || '', false);
                    })
                    .catch(function(){
                        resetButtonLabel(btn);
                        setMessage(btn, btn.getAttribute('data-generic-error') || '', true);
                    });
            } else {
                resetButtonLabel(btn);
                setMessage(btn, btn.getAttribute('data-empty-help') || '', true);
            }
        }).fail(function(){
            btn.setAttribute('data-loading', '0');
            btn.classList.remove('tapin-aff-link__btn--loading');
            resetButtonLabel(btn);
            setMessage(btn, btn.getAttribute('data-generic-error') || '', true);
        });
    });
})(window.jQuery);
JS;
    }
}
