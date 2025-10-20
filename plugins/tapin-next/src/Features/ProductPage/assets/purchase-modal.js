(function () {
  const config = window.TapinPurchaseModalData || {};
  const messages = Object.assign(
    {
      title: '׳₪׳¨׳˜׳™ ׳׳©׳×׳×׳₪׳™׳',
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
  const LOCKED_KEYS = ['instagram', 'facebook', 'phone'];
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
    const hiddenField = document.getElementById('tapinAttendeesField');
    const submitButton = form.querySelector('.single_add_to_cart_button');
    const qtyInput = form.querySelector('input.qty, input[name="quantity"]');

    if (!modal || !hiddenField || !submitButton) {
      return;
    }

    const formContainer = modal.querySelector('[data-form-container]');
    if (!formContainer) {
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
    }

    function populateForm(index) {
      const values = getPrefill(index);
      fieldKeys.forEach((key) => {
        const input = formContainer.querySelector('[data-field=\"' + key + '\"]');
        if (!input) {
          return;
        }

        input.value = values[key] || '';

        if (LOCKED_KEYS.includes(key)) {
          if (index === 0 && values[key]) {
            input.setAttribute('readonly', 'readonly');
            input.dataset.locked = 'true';
            input.classList.add('tapin-field--locked');
          } else {
            input.removeAttribute('readonly');
            input.dataset.locked = 'false';
            input.classList.remove('tapin-field--locked');
          }
        }
      });
      resetErrors();
    }

    function openModal() {
      const qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
      totalAttendees = Number.isFinite(qty) && qty > 0 ? qty : 1;
      attendees = [];
      currentIndex = 0;
      allowSubmit = false;
      hiddenField.value = '';

      if (titleEl) {
        titleEl.textContent = messages.title;
      }

      if (cancelButton) {
        cancelButton.textContent = messages.cancel;
      }

      updateStepIndicator();
      updateNextButtonText();
      populateForm(currentIndex);

      modal.classList.add('is-open');
      modal.removeAttribute('hidden');

      const firstInput = formContainer.querySelector('input');
      if (firstInput) {
        firstInput.focus();
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
      stepText.textContent = format(messages.step, currentIndex + 1, totalAttendees);
    }

    function updateNextButtonText() {
      if (!nextButton) {
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

    function markInvalid(input, message) {
      input.classList.add('tapin-field--invalid');
      const wrapper = input.closest('.tapin-field');
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

        const value = input.value.trim();
        const type = field.type || 'text';
        const isLocked = input.dataset.locked === 'true';

        if (type === 'email') {
          const emailValid = /\S+@\S+\.\S+/.test(value);
          if (!emailValid) {
            isValid = false;
            markInvalid(input, value ? messages.invalidEmail : messages.required);
          }
        } else if (!value && !isLocked) {
          isValid = false;
          markInvalid(input, messages.required);
        }

        if (value) {
          const lower = value.toLowerCase();
          if (!isLocked && key === 'instagram') {
            if (lower.indexOf('instagram.com') === -1 && value.charAt(0) !== '@') {
              isValid = false;
              markInvalid(input, messages.invalidInstagram || messages.required);
            }
          }
          if (!isLocked && key === 'facebook') {
            if (lower.indexOf('facebook') === -1) {
              isValid = false;
              markInvalid(input, messages.invalidFacebook || messages.required);
            }
          }
          if (key === 'phone') {
            const phoneDigits = value.replace(/\D+/g, '');
            if (phoneDigits.length < 10) {
              isValid = false;
              markInvalid(input, messages.invalidPhone || messages.required);
            }
          }
          if (key === 'id_number') {
            const idDigits = value.replace(/\D+/g, '');
            if (idDigits.length !== 9) {
              isValid = false;
              markInvalid(input, messages.invalidId || messages.required);
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
