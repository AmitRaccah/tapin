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

  var ticketTypes = Array.isArray(data.ticketTypes) ? data.ticketTypes : [];
  var attendeePlan = [];
  var totalAttendees = 0;
  var currentIndex = 0;
  var ticketTypeSelect = null;
  var ticketHintEl = null;
  var selectionTotals = {};

  function setRefs(refs) {
    ticketTypeSelect = refs.ticketTypeSelect || null;
    ticketHintEl = refs.ticketHint || null;
  }

  function buildFromSelection() {
    var selection = namespace.Tickets
      ? (typeof namespace.Tickets.getSelectionSnapshot === 'function'
          ? namespace.Tickets.getSelectionSnapshot()
          : namespace.Tickets.selection || {})
      : {};
    var plan = [];
    selectionTotals = {};

    Object.keys(selection).forEach(function (typeId) {
      var count = Number(selection[typeId] || 0);
      if (!Number.isFinite(count) || count <= 0) {
        return;
      }
      selectionTotals[typeId] = count;
      for (var i = 0; i < count; i += 1) {
        plan.push({ typeId: '', label: '' });
      }
    });

    ticketTypes.forEach(function (type) {
      if (!type || !type.id) {
        return;
      }
      var typeId = String(type.id);
      if (typeof selectionTotals[typeId] === 'undefined') {
        selectionTotals[typeId] = 0;
      }
    });

    attendeePlan = plan;
    totalAttendees = plan.length;
    currentIndex = 0;
    return plan.slice();
  }

  function getRemainingTypeCounts(excludeIndex) {
    var remaining = {};
    Object.keys(selectionTotals).forEach(function (typeId) {
      remaining[typeId] = Number(selectionTotals[typeId] || 0);
    });

    attendeePlan.forEach(function (slot, index) {
      if (!slot || !slot.typeId) {
        return;
      }
      if (typeof excludeIndex !== 'undefined' && excludeIndex !== null && index === excludeIndex) {
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

  function setSelection(index, typeId) {
    if (index < 0 || index >= attendeePlan.length) {
      return;
    }
    var normalized = typeId ? String(typeId) : '';
    if (!normalized) {
      attendeePlan[index] = { typeId: '', label: '' };
      updateHint(index);
      return;
    }
    var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(normalized) : normalized;
    attendeePlan[index] = { typeId: normalized, label: label };
    updateHint(index);
  }

  function populateSelect(index) {
    if (!ticketTypeSelect) {
      return;
    }
    currentIndex = index;
    var currentSelection = attendeePlan[index] ? attendeePlan[index].typeId : '';
    var remaining = getRemainingTypeCounts(index);

    ticketTypeSelect.innerHTML = '';

    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = messages.ticketSelectPlaceholder || '';
    placeholder.disabled = true;
    placeholder.hidden = true;
    ticketTypeSelect.appendChild(placeholder);

    ticketTypes.forEach(function (type) {
      if (!type || !type.id) {
        return;
      }
      var typeId = String(type.id);
      var totalForType = Number(selectionTotals[typeId] || 0);
      var isCurrent = currentSelection === typeId;
      if (!isCurrent && totalForType <= 0) {
        return;
      }
      var option = document.createElement('option');
      option.value = typeId;
      var available = remaining[typeId] || 0;
      var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(typeId) : typeId;
      option.textContent = totalForType > 1 ? label + ' (' + available + ')' : label;

      if (isCurrent) {
        option.selected = true;
      } else if (available <= 0) {
        option.disabled = true;
      }

      ticketTypeSelect.appendChild(option);
    });

    if (currentSelection) {
      ticketTypeSelect.value = currentSelection;
    } else {
      ticketTypeSelect.value = '';
    }

    var enabledOptions = Array.prototype.filter.call(ticketTypeSelect.options, function (option) {
      return option.value && !option.disabled;
    });

    if (!currentSelection && enabledOptions.length === 1) {
      var autoValue = enabledOptions[0].value;
      ticketTypeSelect.value = autoValue;
      setSelection(index, autoValue);
    }

    updateHint(index);
  }

  function updateHint(index) {
    if (!ticketHintEl) {
      return;
    }
    if (!attendeePlan[index] || !attendeePlan[index].label) {
      ticketHintEl.hidden = true;
      ticketHintEl.textContent = '';
      return;
    }
    ticketHintEl.textContent = (messages.ticketHintLabel || 'סוג הכרטיס:') + ' ' + attendeePlan[index].label;
    ticketHintEl.hidden = false;
  }

  namespace.Plan = {
    setRefs: setRefs,
    buildFromSelection: buildFromSelection,
    getRemainingTypeCounts: getRemainingTypeCounts,
    setSelection: setSelection,
    populateSelect: populateSelect,
    updateHint: updateHint,
    attendeePlan: function () {
      return attendeePlan.slice();
    },
    getSlot: function (index) {
      return attendeePlan[index] || null;
    },
    getTotal: function () {
      return totalAttendees;
    },
    setCurrentIndex: function (index) {
      currentIndex = index;
    },
    getCurrentIndex: function () {
      return currentIndex;
    },
  };

  return namespace.Plan;
});
