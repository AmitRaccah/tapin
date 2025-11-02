(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};
  var data = root.TapinPurchaseModalData || {};
  var messages = (namespace.Messages && namespace.Messages.all) || {};
  var utils = namespace.Utils || {};

  var ticketTypes = Array.isArray(data.ticketTypes) ? data.ticketTypes : [];
  var state = new Map();
  var selection = {};
  var listEl = null;
  var cards = [];
  var typeLookup = {};

  function renderCard(type) {
    if (!type || !type.id) {
      return null;
    }

    var typeId = String(type.id);
    var card = document.createElement('div');
    card.className = 'tapin-ticket-card';
    card.setAttribute('data-ticket-card', '');
    card.setAttribute('data-type-id', typeId);

    var priceValue = utils.num(type.price);
    var capacitySource = typeof type.capacity !== 'undefined' ? type.capacity : 0;
    var availableSource = typeof type.available !== 'undefined' ? type.available : 0;
    var capacity = utils.int(capacitySource);
    var available = utils.int(availableSource);
    var isSoldOut = Boolean(type.sold_out) || (capacity > 0 && available <= 0);

    if (isSoldOut) {
      card.classList.add('tapin-ticket-card--soldout');
    }

    card.setAttribute('data-price', Number.isFinite(priceValue) ? String(priceValue) : '0');
    card.setAttribute('data-available', String(Math.max(0, available)));
    card.setAttribute('data-capacity', String(Math.max(0, capacity)));

    var header = document.createElement('div');
    header.className = 'tapin-ticket-card__header';

    var titles = document.createElement('div');
    titles.className = 'tapin-ticket-card__titles';

    var nameEl = document.createElement('span');
    nameEl.className = 'tapin-ticket-card__name';
    nameEl.textContent = type.name ? String(type.name) : typeId;
    titles.appendChild(nameEl);

    if (type.description) {
      var descEl = document.createElement('span');
      descEl.className = 'tapin-ticket-card__description';
      descEl.textContent = String(type.description);
      titles.appendChild(descEl);
    }

    var priceEl = document.createElement('span');
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

    var metaEl = document.createElement('div');
    metaEl.className = 'tapin-ticket-card__meta';
    var availabilityText = '';
    if (typeof type.availability_label === 'string' && type.availability_label.trim() !== '') {
      availabilityText = type.availability_label;
    } else if (capacity > 0) {
      var template = messages.ticketStepAvailability || 'זמין: %s';
      var replacement = String(Math.max(0, available));
      availabilityText = template.replace('%1$s', replacement).replace('%s', replacement);
    } else {
      availabilityText = messages.ticketStepNoLimit || 'ללא הגבלה';
    }
    metaEl.textContent = availabilityText;
    card.appendChild(metaEl);

    var actionsEl = document.createElement('div');
    actionsEl.className = 'tapin-ticket-card__actions';

    var decreaseBtn = document.createElement('button');
    decreaseBtn.type = 'button';
    decreaseBtn.className = 'tapin-ticket-card__btn';
    decreaseBtn.setAttribute('data-ticket-action', 'decrease');
    decreaseBtn.setAttribute('aria-label', messages.ticketStepDecrease || 'הפחת כרטיס');
    decreaseBtn.textContent = '-';

    var quantityEl = document.createElement('span');
    quantityEl.className = 'tapin-ticket-card__quantity';
    quantityEl.setAttribute('data-ticket-quantity', '');
    quantityEl.textContent = '0';

    var increaseBtn = document.createElement('button');
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
      var soldOutEl = document.createElement('div');
      soldOutEl.className = 'tapin-ticket-card__soldout';
      soldOutEl.textContent = messages.ticketStepSoldOut || 'אזל המלאי';
      card.appendChild(soldOutEl);
    }

    return card;
  }

  function ensureCards() {
    if (!listEl) {
      return;
    }

    var stepEl = listEl.closest('[data-ticket-step]') || listEl;
    cards = Array.prototype.slice.call(stepEl.querySelectorAll('[data-ticket-card]'));

    if (cards.length || !ticketTypes.length) {
      return;
    }

    var fragment = document.createDocumentFragment();
    ticketTypes.forEach(function (type) {
      var card = renderCard(type);
      if (card) {
        fragment.appendChild(card);
      }
    });
    if (fragment.childNodes.length) {
      listEl.appendChild(fragment);
      cards = Array.prototype.slice.call(stepEl.querySelectorAll('[data-ticket-card]'));
    }
  }

  function hydrateState() {
    var previousKeys = Object.keys(selection);
    state.clear();
    previousKeys.forEach(function (key) {
      delete selection[key];
    });

    cards.forEach(function (card) {
      var typeId = card.getAttribute('data-type-id');
      if (!typeId) {
        return;
      }
      var decreaseBtn = card.querySelector('[data-ticket-action="decrease"]');
      var increaseBtn = card.querySelector('[data-ticket-action="increase"]');
      var quantityEl = card.querySelector('[data-ticket-quantity]');
      var available = utils.int(card.getAttribute('data-available'));
      var capacity = utils.int(card.getAttribute('data-capacity'));
      var limit = capacity > 0 ? Math.max(0, available) : Infinity;

      state.set(typeId, {
        card: card,
        decreaseBtn: decreaseBtn,
        increaseBtn: increaseBtn,
        quantityEl: quantityEl,
        limit: limit,
        capacity: capacity,
      });

      selection[typeId] = 0;

      if (decreaseBtn) {
        decreaseBtn.addEventListener('click', function () {
          adjust(typeId, -1);
        });
      }
      if (increaseBtn) {
        increaseBtn.addEventListener('click', function () {
          adjust(typeId, 1);
        });
      }

      if (Number.isFinite(limit) && limit <= 0) {
        if (decreaseBtn) {
          decreaseBtn.disabled = true;
        }
        if (increaseBtn) {
          increaseBtn.disabled = true;
        }
      }
    });

    typeLookup = ticketTypes.reduce(function (acc, item) {
      if (item && item.id) {
        acc[item.id] = item;
      }
      return acc;
    }, {});
  }

  function updateCardUI(typeId) {
    var entry = state.get(typeId);
    if (!entry) {
      return;
    }
    var current = selection[typeId] || 0;
    if (entry.quantityEl) {
      entry.quantityEl.textContent = String(current);
    }
    if (entry.decreaseBtn) {
      entry.decreaseBtn.disabled = current <= 0;
    }
    if (entry.increaseBtn) {
      var limit = entry.limit;
      entry.increaseBtn.disabled = Number.isFinite(limit) && current >= limit;
    }
  }

  function notifyTotals() {
    if (namespace.Controller && typeof namespace.Controller.updateTicketTotals === 'function') {
      namespace.Controller.updateTicketTotals();
    }
  }

  function adjust(typeId, delta) {
    var entry = state.get(typeId);
    if (!entry) {
      return;
    }
    if (Number.isFinite(entry.limit) && entry.limit <= 0) {
      return;
    }
    var current = selection[typeId] || 0;
    var next = current + delta;
    if (next < 0) {
      next = 0;
    }
    if (Number.isFinite(entry.limit) && next > entry.limit) {
      next = entry.limit;
    }
    if (next === current) {
      return;
    }
    selection[typeId] = next;
    updateCardUI(typeId);
    notifyTotals();
  }

  function resetAndSelectDefault() {
    Object.keys(selection).forEach(function (typeId) {
      selection[typeId] = 0;
      updateCardUI(typeId);
    });

    var defaultTypeId = null;
    for (var i = 0; i < ticketTypes.length; i += 1) {
      var type = ticketTypes[i];
      if (!type || !type.id) {
        continue;
      }
      var entry = state.get(String(type.id));
      if (!entry) {
        continue;
      }
      if (Number.isFinite(entry.limit) && entry.limit <= 0) {
        continue;
      }
      defaultTypeId = String(type.id);
      break;
    }

    if (!defaultTypeId && state.size) {
      defaultTypeId = state.keys().next().value;
    }

    if (defaultTypeId) {
      selection[defaultTypeId] = 1;
      updateCardUI(defaultTypeId);
    }

    notifyTotals();
    return defaultTypeId;
  }

  function getTypeLabel(typeId) {
    var type = typeLookup[typeId];
    if (type && type.name) {
      return type.name;
    }
    var entry = state.get(typeId);
    if (entry && entry.card) {
      var nameEl = entry.card.querySelector('.tapin-ticket-card__name');
      if (nameEl) {
        return nameEl.textContent.trim();
      }
    }
    return typeId;
  }

  function getTotal() {
    return Object.keys(selection).reduce(function (sum, typeId) {
      var count = Number(selection[typeId] || 0);
      return sum + (Number.isFinite(count) ? count : 0);
    }, 0);
  }

  function mount(listElement) {
    listEl = listElement || null;
    ensureCards();
    hydrateState();
  }

  namespace.Tickets = {
    mount: mount,
    getTypeLabel: getTypeLabel,
    getTotal: getTotal,
    adjust: adjust,
    resetAndSelectDefault: resetAndSelectDefault,
    state: state,
    selection: selection,
    listEl: function () {
      return listEl;
    },
    cards: function () {
      return cards.slice();
    },
    getSelectionSnapshot: function () {
      var snapshot = {};
      Object.keys(selection).forEach(function (key) {
        snapshot[key] = selection[key];
      });
      return snapshot;
    },
  };

  return namespace.Tickets;
});
