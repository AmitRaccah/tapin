(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function init() {
    const form = document.querySelector('form.cart');
    if (!form) {
      return;
    }

    const submitButton = form.querySelector('.single_add_to_cart_button');
    const bar = document.getElementById('tapinStickyPurchaseBar');

    if (!submitButton || !bar) {
      return;
    }

    const buyButton = bar.querySelector('[data-role="submit"]');

    if (!buyButton) {
      return;
    }

    if (buyButton) {
      buyButton.addEventListener('click', function (event) {
        event.preventDefault();
        if (submitButton.disabled) {
          return;
        }
        submitButton.click();
      });
    }

    function syncButtonState() {
      const disabled = submitButton.disabled || submitButton.hasAttribute('disabled') || submitButton.classList.contains('disabled');
      buyButton.disabled = disabled;
      bar.classList.toggle('tapin-sticky-bar--disabled', disabled);
    }

    syncButtonState();

    let observer = null;
    if (typeof MutationObserver === 'function') {
      observer = new MutationObserver(syncButtonState);
      observer.observe(submitButton, { attributes: true, attributeFilter: ['disabled', 'class'] });

      window.addEventListener('beforeunload', function () {
        if (observer) {
          observer.disconnect();
        }
      });
    }

    bar.removeAttribute('hidden');
    bar.classList.add('tapin-sticky-bar--visible');
  });
})();
