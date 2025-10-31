(function () {
  const config = window.TapinPurchaseModalData || {};
  const messages = Object.assign(
    {
      title: 'פרטי ההזמנה',
      ticketStepTitle: 'בחרו את הכרטיסים שלכם',
      ticketStepSubtitle: 'בחרו כמה כרטיסים תרצו לרכוש מכל סוג זמין.',
      ticketStepNext: 'המשך',
      ticketStepError: 'בחרו לפחות כרטיס אחד כדי להמשיך.',
      ticketStepSoldOut: 'אזל המלאי',
      ticketStepIncluded: 'כלול',
      ticketStepAvailability: 'זמין: %s',
      ticketStepNoLimit: 'ללא הגבלה',
      ticketStepDecrease: 'הפחת כרטיס',
      ticketStepIncrease: 'הוסף כרטיס',
      ticketTotalLabel: 'סה״כ כרטיסים:',
      ticketHintLabel: 'סוג הכרטיס:',
      ticketSelectPlaceholder: 'בחרו סוג כרטיס',
      ticketSelectError: 'בחרו סוג כרטיס עבור משתתף זה.',
      payerTitle: 'פרטי המזמין',
      participantTitle: 'משתתף %1$s',
      step: 'משתתף %1$s מתוך %2$s',
      next: 'הבא',
      finish: 'סיום והמשך לתשלום',
      cancel: 'ביטול',
      required: 'שדה חובה.',
      invalidEmail: 'הזינו כתובת דוא״ל תקינה.',
      invalidInstagram: 'הזינו שם משתמש אינסטגרם תקין.',
      invalidTikTok: 'הזינו שם משתמש טיקטוק תקין.',
      invalidFacebook: 'הזינו כתובת פייסבוק תקינה.',
      invalidPhone: 'הזינו מספר טלפון תקין (10 ספרות).',
      invalidId: 'הזינו מספר זהות תקין (9 ספרות).',
    },
    config.messages || {}
  );
  const fieldConfig = config.fields || {};
  const fieldKeys = Object.keys(fieldConfig);

  const heMessages = {
    title: String.fromCharCode(0x05E4, 0x05E8, 0x05D8, 0x05D9, 0x0020, 0x05D4, 0x05D4, 0x05D6, 0x05DE, 0x05E0, 0x05D4),
    ticketStepTitle: String.fromCharCode(
      0x05D1, 0x05D7, 0x05E8, 0x05D5, 0x0020, 0x05D0, 0x05EA, 0x0020, 0x05D4, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05DD, 0x0020, 0x05E9, 0x05DC, 0x05DB, 0x05DD
    ),
    ticketStepSubtitle: String.fromCharCode(
      0x05D1, 0x05D7, 0x05E8, 0x05D5, 0x0020, 0x05DB, 0x05DE, 0x05D4, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1, 0x05D9, 0x05DD, 0x0020, 0x05EA, 0x05E8,
      0x05E6, 0x05D5, 0x0020, 0x05DC, 0x05E8, 0x05DB, 0x05D5, 0x05E9, 0x0020, 0x05DE, 0x05DB, 0x05DC, 0x0020, 0x05E1, 0x05D5, 0x05D2, 0x0020, 0x05D6, 0x05DE,
      0x05D9, 0x05DF, 0x002E
    ),
    ticketStepNext: String.fromCharCode(0x05D4, 0x05DE, 0x05E9, 0x05DA),
    ticketStepError: String.fromCharCode(
      0x05D1, 0x05D7, 0x05E8, 0x05D5, 0x0020, 0x05DC, 0x05E4, 0x05D7, 0x05D5, 0x05EA, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1, 0x0020, 0x05D0, 0x05D7,
      0x05D3, 0x0020, 0x05DB, 0x05D3, 0x05D9, 0x0020, 0x05DC, 0x05D4, 0x05DE, 0x05E9, 0x05D9, 0x05DA, 0x002E
    ),
    ticketStepSoldOut: String.fromCharCode(0x05D0, 0x05D6, 0x05DC, 0x0020, 0x05D4, 0x05DE, 0x05DC, 0x05D0, 0x05D9),
    ticketStepIncluded: String.fromCharCode(0x05DB, 0x05DC, 0x05D5, 0x05DC),
    ticketStepAvailability: String.fromCharCode(0x05D6, 0x05DE, 0x05D9, 0x05DF) + ': %s',
    ticketStepNoLimit: String.fromCharCode(0x05DC, 0x05DC, 0x05D0, 0x0020, 0x05D4, 0x05D2, 0x05D1, 0x05DC, 0x05D4),
    ticketStepDecrease: String.fromCharCode(0x05D4, 0x05E4, 0x05D7, 0x05EA, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1),
    ticketStepIncrease: String.fromCharCode(0x05D4, 0x05D5, 0x05E1, 0x05E3, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1),
    ticketTotalLabel: String.fromCharCode(0x05E1, 0x05D4, 0x0022, 0x05DB, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1, 0x05D9, 0x05DD) + ':',
    ticketHintLabel: String.fromCharCode(0x05E1, 0x05D5, 0x05D2, 0x0020, 0x05D4, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1) + ':',
    ticketSelectPlaceholder: String.fromCharCode(0x05D1, 0x05D7, 0x05E8, 0x05D5, 0x0020, 0x05E1, 0x05D5, 0x05D2, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1),
    ticketSelectError: String.fromCharCode(
      0x05D1, 0x05D7, 0x05E8, 0x05D5, 0x0020, 0x05E1, 0x05D5, 0x05D2, 0x0020, 0x05DB, 0x05E8, 0x05D8, 0x05D9, 0x05E1, 0x0020, 0x05E2, 0x05D1, 0x05D5, 0x05E8,
      0x0020, 0x05DE, 0x05E9, 0x05EA, 0x05EA, 0x05E3, 0x0020, 0x05D6, 0x05D4, 0x002E
    ),
    payerTitle: String.fromCharCode(0x05E4, 0x05E8, 0x05D8, 0x05D9, 0x0020, 0x05D4, 0x05DE, 0x05D6, 0x05DE, 0x05D9, 0x05DF),
    participantTitle: String.fromCharCode(0x05DE, 0x05E9, 0x05EA, 0x05EA, 0x05E3, 0x0020) + '%1$s',
    step: String.fromCharCode(0x05DE, 0x05E9, 0x05EA, 0x05EA, 0x05E3, 0x0020) + '%1$s' + String.fromCharCode(0x0020, 0x05DE, 0x05EA, 0x05D5, 0x05DA, 0x0020) + '%2$s',
    next: String.fromCharCode(0x05D4, 0x05D1, 0x05D0),
    finish: String.fromCharCode(0x05E1, 0x05D9, 0x05D5, 0x05DD, 0x0020, 0x05D5, 0x05D4, 0x05DE, 0x05E9, 0x05DA, 0x0020, 0x05DC, 0x05EA, 0x05E9, 0x05DC, 0x05D5, 0x05DD),
    cancel: String.fromCharCode(0x05D1, 0x05D9, 0x05D8, 0x05D5, 0x05DC),
    required: String.fromCharCode(0x05E9, 0x05D3, 0x05D4, 0x0020, 0x05D7, 0x05D5, 0x05D1, 0x05D4) + '.',
    invalidEmail: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05DB, 0x05EA, 0x05D5, 0x05D1, 0x05EA, 0x0020, 0x05D3, 0x05D5, 0x05D0, 0x0022, 0x05DC, 0x0020, 0x05EA,
      0x05E7, 0x05D9, 0x05E0, 0x05D4
    ) + '.',
    invalidInstagram: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05E9, 0x05DD, 0x0020, 0x05DE, 0x05E9, 0x05EA, 0x05DE, 0x05E9, 0x0020, 0x05D0, 0x05D9, 0x05E0, 0x05E1,
      0x05D8, 0x05D2, 0x05E8, 0x05DD, 0x0020, 0x05EA, 0x05E7, 0x05D9, 0x05DF
    ) + '.',
    invalidTikTok: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05E9, 0x05DD, 0x0020, 0x05DE, 0x05E9, 0x05EA, 0x05DE, 0x05E9, 0x0020, 0x05D8, 0x05D9, 0x05E7, 0x05D8,
      0x05D5, 0x05E7, 0x0020, 0x05EA, 0x05E7, 0x05D9, 0x05DF
    ) + '.',
    invalidFacebook: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05DB, 0x05EA, 0x05D5, 0x05D1, 0x05EA, 0x0020, 0x05E4, 0x05D9, 0x05D9, 0x05E1, 0x05D1, 0x05D5, 0x05E7,
      0x0020, 0x05EA, 0x05E7, 0x05D9, 0x05E0, 0x05D4
    ) + '.',
    invalidPhone: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05DE, 0x05E1, 0x05E4, 0x05E8, 0x0020, 0x05D8, 0x05DC, 0x05E4, 0x05D5, 0x05DF, 0x0020, 0x05EA, 0x05E7, 0x05D9,
      0x05DF
    ) + ' (10 ' + String.fromCharCode(0x05E1, 0x05E4, 0x05E8, 0x05D5, 0x05EA) + ').',
    invalidId: String.fromCharCode(
      0x05D4, 0x05D6, 0x05D9, 0x05E0, 0x05D5, 0x0020, 0x05DE, 0x05E1, 0x05E4, 0x05E8, 0x0020, 0x05D6, 0x05D4, 0x05D5, 0x05EA, 0x0020, 0x05EA, 0x05E7, 0x05D9, 0x05DF
    ) + ' (9 ' + String.fromCharCode(0x05E1, 0x05E4, 0x05E8, 0x05D5, 0x05EA) + ').',
  };
  Object.assign(messages, heMessages);

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
    const ticketTypeFieldEl = formContainer ? formContainer.querySelector('[data-ticket-type-field]') : null;
    const ticketTypeSelect = formContainer ? formContainer.querySelector('[data-ticket-type-select]') : null;
    const ticketTypeErrorEl = formContainer ? formContainer.querySelector('[data-ticket-type-error]') : null;
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
        priceEl.textContent = messages.ticketStepIncluded || 'כלול';
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
          const template = messages.ticketStepAvailability || 'זמין: %s';
          const replacement = String(Math.max(0, available));
          availabilityText = template
            .replace('%1$s', replacement)
            .replace('%s', replacement);
        } else {
          availabilityText = messages.ticketStepNoLimit || 'ללא הגבלה';
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
      decreaseBtn.setAttribute('aria-label', messages.ticketStepDecrease || 'הפחת כרטיס');
      decreaseBtn.textContent = '-';

      const quantityEl = document.createElement('span');
      quantityEl.className = 'tapin-ticket-card__quantity';
      quantityEl.setAttribute('data-ticket-quantity', '');
      quantityEl.textContent = '0';

      const increaseBtn = document.createElement('button');
      increaseBtn.type = 'button';
      increaseBtn.className = 'tapin-ticket-card__btn';
      increaseBtn.setAttribute('data-ticket-action', 'increase');
      increaseBtn.setAttribute('aria-label', messages.ticketStepIncrease || 'הוסף כרטיס');
      increaseBtn.textContent = '+';

      actionsEl.appendChild(decreaseBtn);
      actionsEl.appendChild(quantityEl);
      actionsEl.appendChild(increaseBtn);
      card.appendChild(actionsEl);

      if (isSoldOut) {
        const soldOutEl = document.createElement('div');
        soldOutEl.className = 'tapin-ticket-card__soldout';
        soldOutEl.textContent = messages.ticketStepSoldOut || 'אזל המלאי';
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
      if (ticketTypeSelect) {
        ticketTypeSelect.value = '';
      }
      if (ticketTypeErrorEl) {
        ticketTypeErrorEl.textContent = '';
      }
    }

    function buildAttendeePlan() {
      const plan = [];
      ticketTypesConfig.forEach((type) => {
        if (!type || !type.id) {
          return;
        }
        const typeId = type.id;
        const qty = ticketSelection[typeId] || 0;
        const label = getTypeLabel(typeId);
        for (let i = 0; i < qty; i += 1) {
          plan.push({ typeId, label });
        }
      });
      return plan;
    }

    function getRemainingTypeCounts(excludeIndex = null) {
      const remaining = {};
      Object.keys(ticketSelection).forEach((typeId) => {
        remaining[typeId] = ticketSelection[typeId] || 0;
      });

      attendeePlan.forEach((slot, index) => {
        if (!slot || !slot.typeId) {
          return;
        }
        if (excludeIndex !== null && index === excludeIndex) {
          return;
        }
        if (typeof remaining[slot.typeId] === 'undefined') {
          return;
        }
        if (remaining[slot.typeId] > 0) {
          remaining[slot.typeId] -= 1;
        }
      });

      return remaining;
    }

    function rebuildRemainingPlan(fromIndex) {
      const remaining = {};
      Object.keys(ticketSelection).forEach((typeId) => {
        remaining[typeId] = ticketSelection[typeId] || 0;
      });

      for (let i = 0; i <= fromIndex; i += 1) {
        const slot = attendeePlan[i];
        if (slot && slot.typeId && typeof remaining[slot.typeId] !== 'undefined') {
          remaining[slot.typeId] = Math.max(0, remaining[slot.typeId] - 1);
        }
      }

      const queue = [];
      ticketTypesConfig.forEach((type) => {
        if (!type || !type.id) {
          return;
        }
        const typeId = type.id;
        const label = getTypeLabel(typeId);
        const count = remaining[typeId] || 0;
        for (let i = 0; i < count; i += 1) {
          queue.push({ typeId, label });
        }
      });

      for (let i = fromIndex + 1; i < attendeePlan.length; i += 1) {
        attendeePlan[i] = queue.shift() || { typeId: '', label: '' };
      }
    }

    function setPlanSelection(index, typeId) {
      const label = getTypeLabel(typeId);
      attendeePlan[index] = { typeId, label };
      rebuildRemainingPlan(index);
      updateTicketHint(index);
    }

    function populateTicketTypeSelect(index) {
      if (!ticketTypeSelect) {
        return;
      }

      const currentSelection = attendeePlan[index]?.typeId || '';
      const remaining = getRemainingTypeCounts(index);

      ticketTypeSelect.innerHTML = '';

      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = messages.ticketSelectPlaceholder || '';
      placeholder.disabled = true;
      placeholder.hidden = true;
      ticketTypeSelect.appendChild(placeholder);

      ticketTypesConfig.forEach((type) => {
        if (!type || !type.id) {
          return;
        }
        const typeId = type.id;
        const option = document.createElement('option');
        option.value = typeId;
        const available = remaining[typeId] || 0;
        const totalForType = ticketSelection[typeId] || 0;
        const label = getTypeLabel(typeId);
        const isCurrent = currentSelection === typeId;

        option.textContent =
          totalForType > 1 ? `${label} (${available})` : label;

        if (isCurrent) {
          option.selected = true;
        } else if (totalForType === 0) {
          option.disabled = true;
        }

        ticketTypeSelect.appendChild(option);
      });

      if (currentSelection) {
        ticketTypeSelect.value = currentSelection;
        setPlanSelection(index, currentSelection);
      } else {
        ticketTypeSelect.value = '';
      }

      // Auto-select when there is a single available option
      const enabledOptions = Array.from(ticketTypeSelect.options).filter(
        (option) => option.value && !option.disabled
      );
      if (!currentSelection && enabledOptions.length === 1) {
        ticketTypeSelect.value = enabledOptions[0].value;
        setPlanSelection(index, enabledOptions[0].value);
      }
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
      ticketHintEl.textContent = (messages.ticketHintLabel || 'סוג הכרטיס:') + ' ' + slot.label;
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
      modal.querySelectorAll('.tapin-field select').forEach((select) => {
        select.classList.remove('tapin-field--invalid');
      });
      modal.querySelectorAll('.tapin-choice').forEach((choice) => {
        choice.classList.remove('tapin-choice--invalid');
      });
      if (ticketTypeErrorEl) {
        ticketTypeErrorEl.textContent = '';
      }
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
          markInvalid(input, messages.required || 'שדה חובה.', fieldType);
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

      if (ticketTypeSelect) {
        const selectedTypeId = ticketTypeSelect.value;
        if (!selectedTypeId) {
          isValid = false;
          ticketTypeSelect.classList.add('tapin-field--invalid');
          if (ticketTypeErrorEl) {
            ticketTypeErrorEl.textContent =
              messages.ticketSelectError || messages.required || 'חובה לבחור סוג כרטיס.';
          }
        } else {
          ticketTypeSelect.classList.remove('tapin-field--invalid');
          if (ticketTypeErrorEl) {
            ticketTypeErrorEl.textContent = '';
          }
          setPlanSelection(currentIndex, selectedTypeId);
          populateTicketTypeSelect(currentIndex);
        }
      }

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
      populateTicketTypeSelect(index);
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
        cancelButton.textContent = messages.cancel || 'ביטול';
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
    if (ticketTypeSelect) {
      ticketTypeSelect.addEventListener('change', () => {
        const selectedTypeId = ticketTypeSelect.value;
        if (!selectedTypeId) {
          return;
        }
        setPlanSelection(currentIndex, selectedTypeId);
        ticketTypeSelect.classList.remove('tapin-field--invalid');
        if (ticketTypeErrorEl) {
          ticketTypeErrorEl.textContent = '';
        }
        populateTicketTypeSelect(currentIndex);
      });
    }
    resetTicketSelection();
    updateTicketTotals();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
