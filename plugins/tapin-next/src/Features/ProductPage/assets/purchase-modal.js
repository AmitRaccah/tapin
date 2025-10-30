(function () {
  const config = window.TapinPurchaseModalData || {};
  const messages = Object.assign(
    {
      title: 'Purchase Details',
      ticketStepTitle: 'Choose Your Tickets',
      ticketStepSubtitle: 'Select how many tickets you need from each available type.',
      ticketStepNext: 'Continue',
      ticketStepError: 'Select at least one ticket to continue.',
      ticketStepSoldOut: 'Sold out',
      ticketStepIncluded: 'Included',
      ticketStepAvailability: 'Available: %s',
      ticketStepNoLimit: 'No limit',
      ticketStepDecrease: 'Decrease',
      ticketStepIncrease: 'Increase',
      ticketTotalLabel: 'Total tickets:',
      ticketHintLabel: 'Ticket type:',
      payerTitle: 'Buyer Details',
      participantTitle: 'Participant %1$s',
      step: 'Participant %1$s of %2$s',
      next: 'Next',
      finish: 'Complete Purchase',
      cancel: 'Cancel',
      required: 'This field is required.',
      invalidEmail: 'Enter a valid email address.',
      invalidInstagram: 'Enter a valid Instagram handle.',
      invalidTikTok: 'Enter a valid TikTok handle.',
      invalidFacebook: 'Enter a valid Facebook URL.',
      invalidPhone: 'Enter a valid phone number (10 digits).',
      invalidId: 'Enter a valid ID number (9 digits).',
    },
    config.messages || {}
  );
  const fieldConfig = config.fields || {};
  const fieldKeys = Object.keys(fieldConfig);

  function format(str, ...args) {
    return str.replace(/%(\d+)\$s/g, (match, index) => {
      const i = parseInt(index, 10) - 1;
      return typeof args[i] !== 'undefined' ? args[i] : match;
    });
  }

  function normalizeInstagram(value) {
    const trimmed = (value || '').trim();
    if (!trimmed) {
      return '';
    }
    const match = trimmed.match(/instagram\.com\/(@?[^/?#]+)/i);
    const handle = match ? match[1] : trimmed.replace(/^@+/, '').replace(/^\/+/, '');
    const normalized = handle.replace(/\/+$/, '').toLowerCase();
    return /^[a-z0-9._]{1,30}$/.test(normalized) ? normalized : '';
  }

  function normalizeTikTok(value) {
    const trimmed = (value || '').trim();
    if (!trimmed) {
      return '';
    }
    const match = trimmed.match(/tiktok\.com\/@([^/?#]+)/i);
    const handle = match ? match[1] : trimmed.replace(/^@+/, '').replace(/^\/+/, '');
    const normalized = handle.replace(/\/+$/, '').toLowerCase();
    return /^[a-z0-9._]{1,24}$/.test(normalized) ? normalized : '';
  }

  function init() {
    const form = document.querySelector('form.cart');
    if (!form) {
      return;
    }

    const modal = document.getElementById('tapinPurchaseModal');
    if (!modal) {
      return;
    }

    const hiddenField = document.getElementById('tapinAttendeesField');
    const submitButton = form.querySelector('.single_add_to_cart_button');
    const qtyInput = form.querySelector('input.qty, input[name="quantity"]');

    if (!hiddenField || !submitButton) {
      return;
    }

    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }

    const formContainer = modal.querySelector('[data-form-container]');
    const ticketStepEl = modal.querySelector('[data-ticket-step]');
    const ticketTotalEl = modal.querySelector('[data-ticket-total-count]');
    const ticketTotalLabelEl = modal.querySelector('[data-ticket-total-label]');
    const ticketErrorEl = modal.querySelector('[data-ticket-error]');
    const ticketHintEl = modal.querySelector('[data-ticket-hint]');
    const titleEl = modal.querySelector('.tapin-purchase-modal__title');
    const stepText = modal.querySelector('[data-step-text]');
    const nextButton = modal.querySelector('[data-modal-action="next"]');
    const cancelButton = modal.querySelector('[data-modal-role="cancel"]');
    const closeButtons = modal.querySelectorAll('[data-modal-dismiss]');
    const ticketListEl = ticketStepEl
      ? ticketStepEl.querySelector('[data-ticket-list]') || ticketStepEl.querySelector('.tapin-ticket-step__list')
      : null;
    let ticketCards = ticketStepEl ? Array.from(ticketStepEl.querySelectorAll('[data-ticket-card]')) : [];

    const ticketTypesConfig = Array.isArray(config.ticketTypes) ? config.ticketTypes : [];

    function createTicketCard(type) {
      if (!type || !type.id) {
        return null;
      }

      const typeId = String(type.id);
      const card = document.createElement('div');
      card.className = 'tapin-ticket-card';

      const priceValue = typeof type.price === 'number' ? type.price : parseFloat(type.price);
      const capacitySource = typeof type.capacity !== 'undefined' ? type.capacity : 0;
      const availableSource = typeof type.available !== 'undefined' ? type.available : 0;
      const capacityValue =
        typeof capacitySource === 'number' ? capacitySource : parseInt(capacitySource, 10);
      const availableValue =
        typeof availableSource === 'number' ? availableSource : parseInt(availableSource, 10);

      const capacity = Number.isFinite(capacityValue) ? capacityValue : 0;
      const available = Number.isFinite(availableValue) ? availableValue : 0;

      const isSoldOut = Boolean(type.sold_out) || (capacity > 0 && available <= 0);
      if (isSoldOut) {
        card.classList.add('tapin-ticket-card--soldout');
      }

      card.setAttribute('data-ticket-card', '');
      card.setAttribute('data-type-id', typeId);
      card.setAttribute('data-price', Number.isFinite(priceValue) ? String(priceValue) : '0');
      card.setAttribute('data-available', String(Math.max(0, available)));
      card.setAttribute('data-capacity', String(Math.max(0, capacity)));

      const header = document.createElement('div');
      header.className = 'tapin-ticket-card__header';

      const titles = document.createElement('div');
      titles.className = 'tapin-ticket-card__titles';

      const nameEl = document.createElement('span');
      nameEl.className = 'tapin-ticket-card__name';
      nameEl.textContent = type.name ? String(type.name) : typeId;
      titles.appendChild(nameEl);

      if (type.description) {
        const descEl = document.createElement('span');
        descEl.className = 'tapin-ticket-card__description';
        descEl.textContent = String(type.description);
        titles.appendChild(descEl);
      }

      const priceEl = document.createElement('span');
      priceEl.className = 'tapin-ticket-card__price';
      if (typeof type.price_html === 'string' && type.price_html.trim() !== '') {
        priceEl.innerHTML = type.price_html;
      } else if (Number.isFinite(priceValue) && priceValue > 0) {
        priceEl.textContent = String(priceValue);
      } else {
        priceEl.textContent = messages.ticketStepIncluded || 'Included';
      }

      header.appendChild(titles);
      header.appendChild(priceEl);
      card.appendChild(header);

      const metaEl = document.createElement('div');
      metaEl.className = 'tapin-ticket-card__meta';
      let availabilityText =
        typeof type.availability_label === 'string' && type.availability_label.trim() !== ''
          ? type.availability_label
          : '';
      if (!availabilityText) {
        if (capacity > 0) {
          const template = messages.ticketStepAvailability || 'Available: %s';
          const replacement = String(Math.max(0, available));
          availabilityText = template
            .replace('%1$s', replacement)
            .replace('%s', replacement);
        } else {
          availabilityText = messages.ticketStepNoLimit || 'No limit';
        }
      }
      metaEl.textContent = availabilityText;
      card.appendChild(metaEl);

      const actionsEl = document.createElement('div');
      actionsEl.className = 'tapin-ticket-card__actions';

      const decreaseBtn = document.createElement('button');
      decreaseBtn.type = 'button';
      decreaseBtn.className = 'tapin-ticket-card__btn';
      decreaseBtn.setAttribute('data-ticket-action', 'decrease');
      decreaseBtn.setAttribute('aria-label', messages.ticketStepDecrease || 'Decrease');
      decreaseBtn.textContent = '-';

      const quantityEl = document.createElement('span');
      quantityEl.className = 'tapin-ticket-card__quantity';
      quantityEl.setAttribute('data-ticket-quantity', '');
      quantityEl.textContent = '0';

      const increaseBtn = document.createElement('button');
      increaseBtn.type = 'button';
      increaseBtn.className = 'tapin-ticket-card__btn';
      increaseBtn.setAttribute('data-ticket-action', 'increase');
      increaseBtn.setAttribute('aria-label', messages.ticketStepIncrease || 'Increase');
      increaseBtn.textContent = '+';

      actionsEl.appendChild(decreaseBtn);
      actionsEl.appendChild(quantityEl);
      actionsEl.appendChild(increaseBtn);
      card.appendChild(actionsEl);

      if (isSoldOut) {
        const soldOutEl = document.createElement('div');
        soldOutEl.className = 'tapin-ticket-card__soldout';
        soldOutEl.textContent = messages.ticketStepSoldOut || 'Sold out';
        card.appendChild(soldOutEl);
      }

      return card;
    }
    const ticketTypeLookup = ticketTypesConfig.reduce((acc, item) => {
      if (item && item.id) {
        acc[item.id] = item;
      }
      return acc;
    }, {});

    if (ticketListEl && ticketCards.length === 0 && ticketTypesConfig.length > 0) {
      const fragment = document.createDocumentFragment();
      ticketTypesConfig.forEach((type) => {
        const card = createTicketCard(type);
        if (card) {
          fragment.appendChild(card);
        }
      });
      if (fragment.childNodes.length > 0) {
        ticketListEl.appendChild(fragment);
        ticketCards = Array.from(ticketStepEl.querySelectorAll('[data-ticket-card]'));
      }
    }

    const ticketState = new Map();
    const ticketSelection = {};

    ticketCards.forEach((card) => {
      const typeId = card.getAttribute('data-type-id');
      if (!typeId) {
        return;
      }
      const decreaseBtn = card.querySelector('[data-ticket-action="decrease"]');
      const increaseBtn = card.querySelector('[data-ticket-action="increase"]');
      const quantityEl = card.querySelector('[data-ticket-quantity]');
      const available = parseInt(card.getAttribute('data-available') || '0', 10);
      const capacity = parseInt(card.getAttribute('data-capacity') || '0', 10);
      const limit = capacity > 0 ? Math.max(0, available) : Infinity;

      ticketState.set(typeId, {
        card,
        decreaseBtn,
        increaseBtn,
        quantityEl,
        limit,
        capacity,
      });

      ticketSelection[typeId] = 0;

      if (decreaseBtn) {
        decreaseBtn.addEventListener('click', () => adjustTicketQuantity(typeId, -1));
      }
      if (increaseBtn) {
        increaseBtn.addEventListener('click', () => adjustTicketQuantity(typeId, 1));
      }
      if (limit === 0) {
        if (decreaseBtn) {
          decreaseBtn.disabled = true;
        }
        if (increaseBtn) {
          increaseBtn.disabled = true;
        }
      }
    });

    let allowSubmit = false;
    let phase = 'ticket';
    let totalAttendees = 0;
    let currentIndex = 0;
    let attendees = [];
    let attendeePlan = [];

    function getTypeLabel(typeId) {
      if (ticketTypeLookup[typeId] && ticketTypeLookup[typeId].name) {
        return ticketTypeLookup[typeId].name;
      }
      const state = ticketState.get(typeId);
      if (state && state.card) {
        const nameEl = state.card.querySelector('.tapin-ticket-card__name');
        if (nameEl) {
          return nameEl.textContent.trim();
        }
      }
      return typeId;
    }

    function getTotalTickets() {
      return Object.keys(ticketSelection).reduce((sum, typeId) => {
        const count = Number(ticketSelection[typeId] || 0);
        return sum + (Number.isFinite(count) ? count : 0);
      }, 0);
    }

    function setQtyInputValue(value) {
      if (!qtyInput) {
        return;
      }
      const normalized = Math.max(0, Math.round(value || 0));
      const formatted = String(normalized);
      if (qtyInput.value !== formatted) {
        qtyInput.value = formatted;
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    function updateCardUI(typeId) {
      const state = ticketState.get(typeId);
      if (!state) {
        return;
      }
      const current = ticketSelection[typeId] || 0;
      if (state.quantityEl) {
        state.quantityEl.textContent = String(current);
      }
      if (state.decreaseBtn) {
        state.decreaseBtn.disabled = current <= 0;
      }
      if (state.increaseBtn) {
        const limit = state.limit;
        state.increaseBtn.disabled = Number.isFinite(limit) && current >= limit;
      }
    }

    function updateTicketTotals() {
      const total = getTotalTickets();
      if (ticketTotalLabelEl && messages.ticketTotalLabel) {
        ticketTotalLabelEl.textContent = messages.ticketTotalLabel;
      }
      if (ticketTotalEl) {
        ticketTotalEl.textContent = String(total);
      }
      if (ticketErrorEl) {
        ticketErrorEl.textContent = '';
        ticketErrorEl.hidden = true;
      }
      setQtyInputValue(total);
    }

    function adjustTicketQuantity(typeId, delta) {
      const state = ticketState.get(typeId);
      if (!state) {
        return;
      }
      if (Number.isFinite(state.limit) && state.limit <= 0) {
        return;
      }
      const current = ticketSelection[typeId] || 0;
      let next = current + delta;
      if (next < 0) {
        next = 0;
      }
      if (Number.isFinite(state.limit) && next > state.limit) {
        next = state.limit;
      }
      if (next === current) {
        return;
      }
      ticketSelection[typeId] = next;
      updateCardUI(typeId);
      updateTicketTotals();
    }

    function resetTicketSelection() {
      Object.keys(ticketSelection).forEach((typeId) => {
        ticketSelection[typeId] = 0;
        updateCardUI(typeId);
      });

      let defaultTypeId = null;
      for (const type of ticketTypesConfig) {
        if (!type || !type.id) {
          continue;
        }
        const state = ticketState.get(type.id);
        if (!state) {
          continue;
        }
        if (Number.isFinite(state.limit) && state.limit <= 0) {
          continue;
        }
        defaultTypeId = type.id;
        break;
      }

      if (!defaultTypeId && ticketState.size > 0) {
        defaultTypeId = ticketState.keys().next().value;
      }

      if (defaultTypeId) {
        ticketSelection[defaultTypeId] = 1;
        updateCardUI(defaultTypeId);
      }

      updateTicketTotals();
    }

    function buildAttendeePlan() {
      const plan = [];
      Object.keys(ticketSelection).forEach((typeId) => {
        const qty = ticketSelection[typeId] || 0;
        const label = getTypeLabel(typeId);
        for (let i = 0; i < qty; i += 1) {
          plan.push({ typeId, label });
        }
      });
      return plan;
    }

    function updateTitle() {
      if (!titleEl) {
        return;
      }
      if (phase === 'ticket') {
        titleEl.textContent = messages.ticketStepTitle || messages.title || '';
        return;
      }
      if (currentIndex === 0) {
        titleEl.textContent = messages.payerTitle || messages.title || '';
        return;
      }
      const participantTitle = messages.participantTitle || '';
      if (participantTitle) {
        titleEl.textContent = format(participantTitle, currentIndex + 1);
        return;
      }
      titleEl.textContent = messages.title || '';
    }

    function updateStepIndicator() {
      if (!stepText) {
        return;
      }
      if (phase === 'ticket') {
        stepText.textContent = messages.ticketStepSubtitle || '';
        return;
      }
      stepText.textContent = format(messages.step || '', currentIndex + 1, totalAttendees);
    }

    function updateNextButtonText() {
      if (!nextButton) {
        return;
      }
      if (phase === 'ticket') {
        nextButton.textContent = messages.ticketStepNext || messages.next;
        return;
      }
      nextButton.textContent = currentIndex + 1 === totalAttendees ? messages.finish : messages.next;
    }

    function updateTicketHint(index) {
      if (!ticketHintEl) {
        return;
      }
      if (phase !== 'form') {
        ticketHintEl.hidden = true;
        ticketHintEl.textContent = '';
        return;
      }
      const slot = attendeePlan[index];
      if (!slot || !slot.label) {
        ticketHintEl.hidden = true;
        ticketHintEl.textContent = '';
        return;
      }
      ticketHintEl.textContent = (messages.ticketHintLabel || 'Ticket type:') + ' ' + slot.label;
      ticketHintEl.hidden = false;
    }

    function showTicketPhase() {
      phase = 'ticket';
      if (ticketStepEl) {
        ticketStepEl.removeAttribute('hidden');
      }
      if (formContainer) {
        formContainer.setAttribute('hidden', 'hidden');
      }
      updateTitle();
      updateStepIndicator();
      updateNextButtonText();
      if (ticketHintEl) {
        ticketHintEl.hidden = true;
        ticketHintEl.textContent = '';
      }
    }

    function showAttendeePhase() {
      phase = 'form';
      if (ticketStepEl) {
        ticketStepEl.setAttribute('hidden', 'hidden');
      }
      if (formContainer) {
        formContainer.removeAttribute('hidden');
      }
      updateTitle();
      updateStepIndicator();
      updateNextButtonText();
      populateForm(currentIndex);
      updateTicketHint(currentIndex);
      updateRequiredIndicators();
      const firstInput = formContainer ? formContainer.querySelector('input') : null;
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
      modal.querySelectorAll('.tapin-choice').forEach((choice) => {
        choice.classList.remove('tapin-choice--invalid');
      });
    }

    function updateChoiceState(fieldKey, value) {
      const wrapper = modal.querySelector('[data-field-key=\'' + fieldKey + '\']');
      if (!wrapper) {
        return;
      }
      const group = wrapper.querySelector('[data-choice-group]');
      if (!group) {
        return;
      }
      group.querySelectorAll('.tapin-choice__option').forEach((btn) => {
        btn.classList.toggle('is-selected', btn.getAttribute('data-choice-value') === value);
      });
    }

    function setupChoiceFields() {
      modal.querySelectorAll('.tapin-choice__option').forEach((btn) => {
        btn.addEventListener('click', () => {
          const fieldKey = btn.closest('[data-field-key]')?.getAttribute('data-field-key');
          if (!fieldKey) {
            return;
          }
          const input = modal.querySelector('[data-field=\'' + fieldKey + '\']');
          if (!input) {
            return;
          }
          const choiceValue = btn.getAttribute('data-choice-value') || '';
          input.value = choiceValue;
          updateChoiceState(fieldKey, choiceValue);
        });
      });
    }

    function updateRequiredIndicators() {
      if (!formContainer) {
        return;
      }
      const isPayer = currentIndex === 0;
      formContainer.querySelectorAll('.tapin-field').forEach((fieldEl) => {
        const indicator = fieldEl.querySelector('[data-required-indicator]');
        if (!indicator) {
          return;
        }
        const input = fieldEl.querySelector('[data-field]');
        if (!input) {
          return;
        }
        const required =
          (isPayer && input.dataset.requiredPayer === 'true') ||
          (!isPayer && input.dataset.requiredAttendee === 'true');
        if (required) {
          indicator.removeAttribute('hidden');
        } else {
          indicator.setAttribute('hidden', 'hidden');
        }
      });
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
      const isPayer = currentIndex === 0;

      fieldKeys.forEach((key) => {
        const field = fieldConfig[key] || {};
        const fieldType = field.type || 'text';
        const input = formContainer ? formContainer.querySelector('[data-field=\'' + key + '\']') : null;
        if (!input) {
          return;
        }

        const rawValue = (input.value || '').trim();
        const required =
          (isPayer && input.dataset.requiredPayer === 'true') ||
          (!isPayer && input.dataset.requiredAttendee === 'true');

        let value = rawValue;

        if (value === '' && required) {
          isValid = false;
          markInvalid(input, messages.required || 'Required.', fieldType);
          return;
        }

        if (value === '') {
          result[key] = '';
          return;
        }

        if (key === 'instagram') {
          value = normalizeInstagram(value);
        } else if (key === 'tiktok') {
          value = normalizeTikTok(value);
        }

        if (key === 'email') {
          const email = value.toLowerCase();
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            isValid = false;
            markInvalid(input, messages.invalidEmail || messages.required, fieldType);
            return;
          }
          value = email;
        }

        if (key === 'phone') {
          const digits = value.replace(/\D+/g, '');
          if (digits.length < 10) {
            isValid = false;
            markInvalid(input, messages.invalidPhone || messages.required, fieldType);
            return;
          }
        }

        if (key === 'id_number') {
          const digits = value.replace(/\D+/g, '');
          if (digits.length !== 9) {
            isValid = false;
            markInvalid(input, messages.invalidId || messages.required, fieldType);
            return;
          }
        }

        result[key] = value;
      });

      return isValid ? result : null;
    }

    function populateForm(index) {
      const values = getPrefill(index);
      fieldKeys.forEach((key) => {
        const field = fieldConfig[key] || {};
        const fieldType = field.type || 'text';
        const input = formContainer ? formContainer.querySelector('[data-field=\'' + key + '\']') : null;
        if (!input) {
          return;
        }

        const value = values[key] || '';
        input.value = value;

        if (fieldType === 'choice') {
          updateChoiceState(key, value);
        }
      });
      resetErrors();
      updateRequiredIndicators();
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

    function finalize() {
      hiddenField.value = JSON.stringify(attendees);
      allowSubmit = true;
      closeModal(false);
      setTimeout(() => {
        submitForm();
      }, 0);
    }

    function handleNext() {
      if (phase === 'ticket') {
        const total = getTotalTickets();
        if (total <= 0) {
          if (ticketErrorEl) {
            ticketErrorEl.textContent = messages.ticketStepError || '';
            ticketErrorEl.hidden = false;
          }
          return;
        }

        attendeePlan = buildAttendeePlan();
        totalAttendees = attendeePlan.length;

        if (totalAttendees <= 0) {
          if (ticketErrorEl) {
            ticketErrorEl.textContent = messages.ticketStepError || '';
            ticketErrorEl.hidden = false;
          }
          return;
        }

        if (ticketErrorEl) {
          ticketErrorEl.textContent = '';
          ticketErrorEl.hidden = true;
        }

        setQtyInputValue(totalAttendees);
        attendees = [];
        currentIndex = 0;
        allowSubmit = false;
        hiddenField.value = '';
        showAttendeePhase();
        return;
      }

      const data = collectCurrentStep();
      if (!data) {
        return;
      }

      const slot = attendeePlan[currentIndex];
      if (slot) {
        data.ticket_type = slot.typeId || '';
        data.ticket_type_label = slot.label || '';
      }

      attendees[currentIndex] = data;

      if (currentIndex + 1 < totalAttendees) {
        currentIndex += 1;
        updateStepIndicator();
        updateNextButtonText();
        updateTitle();
        populateForm(currentIndex);
        updateTicketHint(currentIndex);
        return;
      }

      finalize();
    }

    function closeModal(resetData = true) {
      modal.classList.remove('is-open');
      modal.setAttribute('hidden', 'hidden');
      if (resetData) {
        hiddenField.value = '';
        attendees = [];
        attendeePlan = [];
        resetTicketSelection();
        resetErrors();
      }
      if (submitButton) {
        submitButton.focus();
      }
    }

    function openModal() {
      resetTicketSelection();
      phase = 'ticket';
      attendees = [];
      attendeePlan = [];
      totalAttendees = 0;
      currentIndex = 0;
      allowSubmit = false;
      hiddenField.value = '';
      resetErrors();

      if (cancelButton) {
        cancelButton.textContent = messages.cancel || 'Cancel';
      }

      showTicketPhase();
      modal.classList.add('is-open');
      modal.removeAttribute('hidden');

      const focusTarget = ticketStepEl
        ? ticketStepEl.querySelector('[data-ticket-card] [data-ticket-action="increase"]')
        : null;
      if (focusTarget) {
        focusTarget.focus();
      }
    }

    if (cancelButton) {
      cancelButton.addEventListener('click', () => {
        closeModal(true);
      });
    }

    closeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        closeModal(true);
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal(true);
      }
    });

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
    resetTicketSelection();
    updateTicketTotals();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
