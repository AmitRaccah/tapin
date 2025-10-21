(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function toNumber(value) {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : NaN;
  }

  ready(function init() {
    const form = document.querySelector('form.cart');
    if (!form) {
      return;
    }

    const qtyInput = form.querySelector('input.qty, input[name="quantity"]');
    const submitButton = form.querySelector('.single_add_to_cart_button');
    const bar = document.getElementById('tapinStickyPurchaseBar');

    if (!qtyInput || !submitButton || !bar) {
      return;
    }

    const decreaseBtn = bar.querySelector('[data-action="decrease"]');
    const increaseBtn = bar.querySelector('[data-action="increase"]');
    const quantityValue = bar.querySelector('[data-role="quantity"]');
    const quantityLabel = bar.querySelector('[data-role="label"]');
    const buyButton = bar.querySelector('[data-role="submit"]');

    if (!decreaseBtn || !increaseBtn || !quantityValue || !buyButton) {
      return;
    }

    function getStep() {
      const stepAttr = toNumber(qtyInput.getAttribute('step'));
      return Number.isFinite(stepAttr) && stepAttr > 0 ? stepAttr : 1;
    }

    function getMin() {
      const minAttr = toNumber(qtyInput.getAttribute('min'));
      return Number.isFinite(minAttr) ? minAttr : 1;
    }

    function getMax() {
      const maxAttr = toNumber(qtyInput.getAttribute('max'));
      return Number.isFinite(maxAttr) ? maxAttr : Infinity;
    }

    function getPrecision(num) {
      if (!Number.isFinite(num)) {
        return 0;
      }
      const str = num.toString();
      const idx = str.indexOf('.');
      return idx >= 0 ? str.length - idx - 1 : 0;
    }

    function normalize(value) {
      const min = getMin();
      const max = getMax();
      const step = getStep();

      let result = Number.isFinite(value) ? value : min;
      if (Number.isFinite(max)) {
        result = Math.max(min, Math.min(max, result));
      } else {
        result = Math.max(min, result);
      }

      if (!Number.isFinite(result)) {
        result = min;
      }

      const precision = Math.max(getPrecision(step), getPrecision(min));
      const multiplier = Math.pow(10, precision);
      const minScaled = Math.round(min * multiplier);
      const stepScaled = Math.max(1, Math.round(step * multiplier));
      const valueScaled = Math.round(result * multiplier);
      let steps = Math.round((valueScaled - minScaled) / stepScaled);
      if (steps < 0) {
        steps = 0;
      }
      let normalizedScaled = minScaled + steps * stepScaled;

      if (Number.isFinite(max)) {
        const maxScaled = Math.round(max * multiplier);
        if (normalizedScaled > maxScaled) {
          normalizedScaled = maxScaled;
        }
      }

      return normalizedScaled / multiplier;
    }

    function format(value) {
      const precision = Math.max(getPrecision(getStep()), getPrecision(getMin()));
      if (precision > 0) {
        return value.toFixed(precision);
      }
      return String(Math.round(value));
    }

    function updateLabel(value) {
      if (!quantityLabel) {
        return;
      }
      const singular = quantityLabel.dataset.singular || quantityLabel.textContent;
      const plural = quantityLabel.dataset.plural || quantityLabel.textContent;
      const rounded = Math.round(value);
      quantityLabel.textContent = rounded === 1 ? singular : plural;
    }

    function updateButtons(value) {
      const min = getMin();
      const max = getMax();
      const controlsDisabled = qtyInput.disabled;
      decreaseBtn.disabled = controlsDisabled || value <= min;
      increaseBtn.disabled = controlsDisabled || (Number.isFinite(max) && value >= max);
    }

    function setValue(value, fromInput) {
      const normalized = normalize(value);
      const formatted = format(normalized);

      if (qtyInput.value !== formatted) {
        qtyInput.value = formatted;
        if (!fromInput) {
          qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
          qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      quantityValue.textContent = formatted;
      updateLabel(normalized);
      updateButtons(normalized);

      return normalized;
    }

    function syncFromInput() {
      setValue(toNumber(qtyInput.value), true);
    }

    decreaseBtn.addEventListener('click', function () {
      setValue(normalize(toNumber(qtyInput.value)) - getStep(), false);
    });

    increaseBtn.addEventListener('click', function () {
      setValue(normalize(toNumber(qtyInput.value)) + getStep(), false);
    });

    qtyInput.addEventListener('input', syncFromInput);
    qtyInput.addEventListener('change', syncFromInput);

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

    setValue(toNumber(qtyInput.value), true);

    bar.removeAttribute('hidden');
    bar.classList.add('tapin-sticky-bar--visible');
  });
})();
