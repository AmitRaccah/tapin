(function () {
  const config = window.TapinPurchaseModalData || {};
  const messages = Object.assign(
    {
      title: '׳₪׳¨׳˜׳™ ׳׳©׳×׳×׳₪׳™׳',
      quantityTitle: '',
      quantitySubtitle: '',
      quantityNext: '',
      quantitySingular: '',
      quantityPlural: '',
      quantityIncrease: '',
      quantityDecrease: '',
      step: '׳׳©׳×׳×׳£ %1 ׳׳×׳•׳ %2',
      next: '׳”׳‘׳',
      finish: '׳¡׳™׳•׳ ׳•׳”׳׳©׳ ׳׳×׳©׳׳•׳',
      cancel: '׳‘׳™׳˜׳•׳',
      required: '׳™׳© ׳׳׳׳ ׳׳× ׳›׳ ׳”׳©׳“׳•׳×',
      invalidEmail: '׳›׳×׳•׳‘׳× ׳”׳׳™׳׳™׳™׳ ׳׳™׳ ׳” ׳×׳§׳™׳ ׳”',
      invalidInstagram: 'Instagram handle must start with @ or include instagram.com',
      invalidFacebook: 'Facebook link must include the word facebook',
      invalidPhone: 'Phone number must contain at least 10 digits',
      invalidId: 'ID number must be exactly 9 digits',
    },
    config.messages || {}
  );
  const fieldConfig = config.fields || {};
  const LOCKED_KEYS = ['instagram', 'facebook', 'phone', 'full_name', 'email', 'birth_date', 'gender'];
  const fieldKeys = Object.keys(fieldConfig);

  function format(str, ...args) {
    return str.replace(/%(\d+)\$s/g, (match, index) => {
      const i = parseInt(index, 10) - 1;
      return typeof args[i] !== 'undefined' ? args[i] : match;
    });
  }

  function init() {
    const form = document.querySelector('form.cart');
    if (!form) {
      return;
    }

    const modal = document.getElementById('tapinPurchaseModal');
    if (modal && document.body && modal.parentElement !== document.body) {
      // Move the modal to the document body so it is not constrained by parent stacking contexts.
      document.body.appendChild(modal);
    }
    const hiddenField = document.getElementById('tapinAttendeesField');
    const submitButton = form.querySelector('.single_add_to_cart_button');
    const qtyInput = form.querySelector('input.qty, input[name="quantity"]');

    if (!modal || !hiddenField || !submitButton) {
      return;
    }

    const formContainer = modal.querySelector('[data-form-container]');
    const quantityStep = modal.querySelector('[data-quantity-step]');
    const quantityValueEl = modal.querySelector('[data-quantity-value]');
    const quantityDecreaseBtn = modal.querySelector('[data-quantity-action="decrease"]');
    const quantityIncreaseBtn = modal.querySelector('[data-quantity-action="increase"]');

    if (!formContainer || !quantityStep || !quantityValueEl) {
      return;
    }

    const stepText = modal.querySelector('[data-step-text]');
    const titleEl = modal.querySelector('.tapin-purchase-modal__title');
    const nextButton = modal.querySelector('[data-modal-action="next"]');
    const cancelButton = modal.querySelector('[data-modal-role="cancel"]');
    const closeButtons = modal.querySelectorAll('[data-modal-dismiss]');

    let allowSubmit = false;
    let totalAttendees = 1;
    let currentIndex = 0;
    let attendees = [];
    let isQuantityPhase = true;
    let selectedQuantity = 1;

    function toNumber(value) {
      const parsed = parseFloat(value);
      return Number.isFinite(parsed) ? parsed : NaN;
    }

    function getPrecision(num) {
      if (!Number.isFinite(num)) {
        return 0;
      }
      const str = num.toString();
      const idx = str.indexOf('.');
      return idx >= 0 ? str.length - idx - 1 : 0;
    }

    function getStep() {
      if (!qtyInput) {
        return 1;
      }
      const stepAttr = toNumber(qtyInput.getAttribute('step'));
      return Number.isFinite(stepAttr) && stepAttr > 0 ? stepAttr : 1;
    }

    function getMin() {
      if (!qtyInput) {
        return 1;
      }
      const minAttr = toNumber(qtyInput.getAttribute('min'));
      return Number.isFinite(minAttr) ? minAttr : 1;
    }

    function getMax() {
      if (!qtyInput) {
        return Infinity;
      }
      const maxAttr = toNumber(qtyInput.getAttribute('max'));
      return Number.isFinite(maxAttr) ? maxAttr : Infinity;
    }

    function normalizeQuantity(value) {
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

    function formatQuantity(value) {
      const precision = Math.max(getPrecision(getStep()), getPrecision(getMin()));
      if (precision > 0) {
        return value.toFixed(precision);
      }
      return String(Math.round(value));
    }

    function updateQuantityUI(value) {
      const normalized = normalizeQuantity(value);
      selectedQuantity = normalized;
      const formatted = formatQuantity(normalized);
      const min = getMin();
      const max = getMax();
      const controlsDisabled = qtyInput ? qtyInput.disabled : false;
      totalAttendees = Math.max(1, Math.round(normalized));

      if (quantityValueEl) {
        quantityValueEl.textContent = formatted;
        const baseLabel = Math.round(normalized) === 1
          ? messages.quantitySingular || ''
          : messages.quantityPlural || '';
        const ariaLabel = (baseLabel ? baseLabel + ' ' : '') + formatted;
        quantityValueEl.setAttribute('aria-label', ariaLabel.trim());
      }

      if (quantityDecreaseBtn) {
        quantityDecreaseBtn.disabled = controlsDisabled || normalized <= min;
        if (messages.quantityDecrease) {
          quantityDecreaseBtn.setAttribute('aria-label', messages.quantityDecrease);
        }
      }

      if (quantityIncreaseBtn) {
        quantityIncreaseBtn.disabled = controlsDisabled || (Number.isFinite(max) && normalized >= max);
        if (messages.quantityIncrease) {
          quantityIncreaseBtn.setAttribute('aria-label', messages.quantityIncrease);
        }
      }

      return normalized;
    }

    function setQtyInputValue(value) {
      if (!qtyInput) {
        return;
      }
      const normalized = normalizeQuantity(value);
      const formatted = formatQuantity(normalized);
      if (qtyInput.value !== formatted) {
        qtyInput.value = formatted;
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    function syncQuantityFromInput() {
      if (!qtyInput) {
        return;
      }
      updateQuantityUI(toNumber(qtyInput.value));
    }

    function showQuantityPhase() {
      isQuantityPhase = true;
      totalAttendees = Math.max(1, Math.round(selectedQuantity));

      if (quantityStep) {
        quantityStep.removeAttribute('hidden');
      }
      if (formContainer) {
        formContainer.setAttribute('hidden', 'hidden');
      }
      if (titleEl) {
        titleEl.textContent = messages.quantityTitle || messages.title || '';
      }
      if (stepText) {
        stepText.textContent = messages.quantitySubtitle || '';
      }
      updateStepIndicator();
      updateNextButtonText();
    }

    function showAttendeePhase() {
      isQuantityPhase = false;
      if (quantityStep) {
        quantityStep.setAttribute('hidden', 'hidden');
      }
      if (formContainer) {
        formContainer.removeAttribute('hidden');
      }
      if (titleEl) {
        titleEl.textContent = messages.title || '';
      }
      updateStepIndicator();
      updateNextButtonText();
      populateForm(currentIndex);

      const firstInput = formContainer.querySelector('input');
      if (firstInput) {
        firstInput.focus();
      }
    }

    function getPrefill(index) {
      if (index === 0 && config.prefill) {
        return Object.assign({}, config.prefill);
      }
      return fieldKeys.reduce((acc, key) => {
        acc[key] = '';
        return acc;
      }, {});
    }

    function resetErrors() {
      modal.querySelectorAll('.tapin-field__error').forEach((el) => {
        el.textContent = '';
      });
      modal.querySelectorAll('.tapin-field input').forEach((input) => {
        input.classList.remove('tapin-field--invalid');
      });
      modal.querySelectorAll('.tapin-choice').forEach((group) => {
        group.classList.remove('tapin-choice--invalid');
      });
    }

    function getChoiceGroup(fieldKey) {
      return formContainer.querySelector('[data-choice-group=\"' + fieldKey + '\"]');
    }

    function updateChoiceState(fieldKey, value) {
      const group = getChoiceGroup(fieldKey);
      if (!group) {
        return;
      }
      const normalized = value || '';
      const buttons = group.querySelectorAll('[data-choice-value]');
      buttons.forEach((btn) => {
        const isSelected = btn.dataset.choiceValue === normalized;
        btn.classList.toggle('is-selected', isSelected);
        btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      });
      if (normalized === '') {
        group.classList.remove('tapin-choice--has-value');
      } else {
        group.classList.add('tapin-choice--has-value');
        group.classList.remove('tapin-choice--invalid');
      }
    }

    function setupChoiceFields() {
      const groups = formContainer.querySelectorAll('[data-choice-group]');
      groups.forEach((group) => {
        const fieldKey = group.dataset.choiceGroup;
        if (!fieldKey) {
          return;
        }
        const input = formContainer.querySelector('[data-field=\"' + fieldKey + '\"]');
        if (!input) {
          return;
        }
        group.querySelectorAll('[data-choice-value]').forEach((btn) => {
          btn.setAttribute('aria-pressed', 'false');
          btn.addEventListener('click', () => {
            if (input.dataset.locked === 'true') {
              return;
            }
            const choiceValue = btn.dataset.choiceValue || '';
            input.value = choiceValue;
            updateChoiceState(fieldKey, choiceValue);
          });
        });
      });
    }

    function populateForm(index) {
      const values = getPrefill(index);
      fieldKeys.forEach((key) => {
        const field = fieldConfig[key] || {};
        const fieldType = field.type || 'text';
        const input = formContainer.querySelector('[data-field=\"' + key + '\"]');
        if (!input) {
          return;
        }

        const value = values[key] || '';
        input.value = value;

        if (fieldType === 'choice') {
          updateChoiceState(key, value);
        }

        if (LOCKED_KEYS.includes(key)) {
          const choiceGroup = fieldType === 'choice' ? getChoiceGroup(key) : null;
          if (index === 0 && value) {
            input.setAttribute('readonly', 'readonly');
            input.dataset.locked = 'true';
            input.classList.add('tapin-field--locked');
            if (choiceGroup) {
              choiceGroup.classList.add('tapin-choice--locked');
            }
          } else {
            input.removeAttribute('readonly');
            input.dataset.locked = 'false';
            input.classList.remove('tapin-field--locked');
            if (choiceGroup) {
              choiceGroup.classList.remove('tapin-choice--locked');
            }
          }
        }
      });
      resetErrors();
    }

    function openModal() {
      const qty = qtyInput ? toNumber(qtyInput.value) : NaN;
      selectedQuantity = updateQuantityUI(qty);
      totalAttendees = Math.max(1, Math.round(selectedQuantity));
      attendees = [];
      currentIndex = 0;
      allowSubmit = false;
      hiddenField.value = '';
      resetErrors();

      if (cancelButton) {
        cancelButton.textContent = messages.cancel;
      }

      showQuantityPhase();

      modal.classList.add('is-open');
      modal.removeAttribute('hidden');

      const focusTarget = quantityIncreaseBtn || quantityDecreaseBtn;
      if (focusTarget) {
        focusTarget.focus();
      }
    }

    function closeModal(resetData = true) {
      modal.classList.remove('is-open');
      modal.setAttribute('hidden', 'hidden');
      if (resetData) {
        hiddenField.value = '';
        attendees = [];
        resetErrors();
      }
      if (submitButton) {
        submitButton.focus();
      }
    }

    function updateStepIndicator() {
      if (!stepText) {
        return;
      }
      if (isQuantityPhase) {
        stepText.textContent = messages.quantitySubtitle || '';
        return;
      }
      stepText.textContent = format(messages.step, currentIndex + 1, totalAttendees);
    }

    function updateNextButtonText() {
      if (!nextButton) {
        return;
      }
      if (isQuantityPhase) {
        nextButton.textContent = messages.quantityNext || messages.next;
        return;
      }
      nextButton.textContent = currentIndex + 1 === totalAttendees ? messages.finish : messages.next;
    }

    function submitForm() {
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitButton || undefined);
        return;
      }

      if (submitButton && typeof submitButton.click === 'function') {
        submitButton.click();
        return;
      }

      form.submit();
      allowSubmit = false;
    }

    function markInvalid(input, message, fieldType) {
      input.classList.add('tapin-field--invalid');
      const wrapper = input.closest('.tapin-field');
      if (wrapper && fieldType === 'choice') {
        const group = wrapper.querySelector('[data-choice-group]');
        if (group) {
          group.classList.add('tapin-choice--invalid');
        }
      }
      if (!wrapper) {
        return;
      }
      const errorEl = wrapper.querySelector('.tapin-field__error');
      if (errorEl) {
        errorEl.textContent = message;
      }
    }

    function collectCurrentStep() {
      resetErrors();
      const result = {};
      let isValid = true;

      fieldKeys.forEach((key) => {
        const field = fieldConfig[key] || {};
        const input = formContainer.querySelector('[data-field=\"' + key + '\"]');
        if (!input) {
          return;
        }

        const type = field.type || input.dataset.fieldType || 'text';
        const value = type === 'choice' ? input.value : input.value.trim();
        const isLocked = input.dataset.locked === 'true';

        if (type === 'email') {
          const emailValid = /\S+@\S+\.\S+/.test(value);
          if (!emailValid) {
            isValid = false;
            markInvalid(input, value ? messages.invalidEmail : messages.required, type);
          }
        } else if (!value && !isLocked) {
          isValid = false;
          markInvalid(input, messages.required, type);
        }

        if (value) {
          const lower = value.toLowerCase();
          if (!isLocked && key === 'instagram') {
            if (lower.indexOf('instagram.com') === -1 && value.charAt(0) !== '@') {
              isValid = false;
              markInvalid(input, messages.invalidInstagram || messages.required, type);
            }
          }
          if (!isLocked && key === 'facebook') {
            if (lower.indexOf('facebook') === -1) {
              isValid = false;
              markInvalid(input, messages.invalidFacebook || messages.required, type);
            }
          }
          if (key === 'phone') {
            const phoneDigits = value.replace(/\D+/g, '');
            if (phoneDigits.length < 10) {
              isValid = false;
              markInvalid(input, messages.invalidPhone || messages.required, type);
            }
          }
          if (key === 'id_number') {
            const idDigits = value.replace(/\D+/g, '');
            if (idDigits.length !== 9) {
              isValid = false;
              markInvalid(input, messages.invalidId || messages.required, type);
            }
          }
        }

        result[key] = value;
      });

      return isValid ? result : null;
    }

    function finalize() {
      hiddenField.value = JSON.stringify(attendees);
      allowSubmit = true;
      closeModal(false);
      setTimeout(() => {
        submitForm();
      }, 0);
    }

    function handleNext() {
      if (isQuantityPhase) {
        const normalized = updateQuantityUI(selectedQuantity);
        setQtyInputValue(normalized);
        attendees = [];
        currentIndex = 0;
        hiddenField.value = '';
        showAttendeePhase();
        return;
      }

      const data = collectCurrentStep();
      if (!data) {
        return;
      }

      attendees[currentIndex] = data;

      if (currentIndex + 1 < totalAttendees) {
        currentIndex += 1;
        updateStepIndicator();
        updateNextButtonText();
        populateForm(currentIndex);
        return;
      }

      finalize();
    }

    if (quantityDecreaseBtn) {
      quantityDecreaseBtn.addEventListener('click', () => {
        updateQuantityUI(selectedQuantity - getStep());
      });
    }

    if (quantityIncreaseBtn) {
      quantityIncreaseBtn.addEventListener('click', () => {
        updateQuantityUI(selectedQuantity + getStep());
      });
    }

    if (qtyInput) {
      qtyInput.addEventListener('input', syncQuantityFromInput);
      qtyInput.addEventListener('change', syncQuantityFromInput);
    }

    form.addEventListener('submit', (event) => {
      if (allowSubmit) {
        allowSubmit = false;
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
      }
      openModal();
    });

    submitButton.addEventListener('click', (event) => {
      if (allowSubmit) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
      }
      openModal();
    });

    if (nextButton) {
      nextButton.addEventListener('click', handleNext);
    }

    setupChoiceFields();

    closeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        attendees = [];
        closeModal(true);
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        attendees = [];
        closeModal(true);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
