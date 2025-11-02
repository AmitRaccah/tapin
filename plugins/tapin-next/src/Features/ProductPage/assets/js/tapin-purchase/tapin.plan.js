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
    ticketTypes.forEach(function (type) {
      if (!type || !type.id) {
        return;
      }
      var typeId = String(type.id);
      var count = Number(selection[typeId] || 0);
      if (!Number.isFinite(count) || count <= 0) {
        return;
      }
      var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(typeId) : typeId;
      for (var i = 0; i < count; i += 1) {
        plan.push({ typeId: typeId, label: label });
      }
    });
    attendeePlan = plan;
    totalAttendees = plan.length;
    currentIndex = 0;
    return plan.slice();
  }

  function getRemainingTypeCounts(excludeIndex) {
    var selection = namespace.Tickets
      ? (typeof namespace.Tickets.getSelectionSnapshot === 'function'
          ? namespace.Tickets.getSelectionSnapshot()
          : namespace.Tickets.selection || {})
      : {};
    var remaining = {};
    Object.keys(selection).forEach(function (typeId) {
      remaining[typeId] = Number(selection[typeId] || 0);
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

  function rebuildRemaining(fromIndex) {
    var selection = namespace.Tickets
      ? (typeof namespace.Tickets.getSelectionSnapshot === 'function'
          ? namespace.Tickets.getSelectionSnapshot()
          : namespace.Tickets.selection || {})
      : {};
    var remaining = {};
    Object.keys(selection).forEach(function (typeId) {
      remaining[typeId] = Number(selection[typeId] || 0);
    });

    var maxIndex = Math.min(fromIndex, attendeePlan.length - 1);
    for (var i = 0; i <= maxIndex; i += 1) {
      var slot = attendeePlan[i];
      if (!slot || !slot.typeId) {
        continue;
      }
      if (typeof remaining[slot.typeId] === 'undefined') {
        continue;
      }
      remaining[slot.typeId] = Math.max(0, remaining[slot.typeId] - 1);
    }

    var queue = [];
    ticketTypes.forEach(function (type) {
      if (!type || !type.id) {
        return;
      }
      var typeId = String(type.id);
      var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(typeId) : typeId;
      var count = remaining[typeId] || 0;
      for (var j = 0; j < count; j += 1) {
        queue.push({ typeId: typeId, label: label });
      }
    });

    for (var k = fromIndex + 1; k < attendeePlan.length; k += 1) {
      attendeePlan[k] = queue.shift() || { typeId: '', label: '' };
    }
  }

  function setSelection(index, typeId) {
    var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(typeId) : typeId;
    attendeePlan[index] = { typeId: typeId, label: label };
    rebuildRemaining(index);
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
      var option = document.createElement('option');
      option.value = typeId;
      var totalForType = namespace.Tickets && namespace.Tickets.selection
        ? Number(namespace.Tickets.selection[typeId] || 0)
        : 0;
      var available = remaining[typeId] || 0;
      var label = namespace.Tickets ? namespace.Tickets.getTypeLabel(typeId) : typeId;
      var isCurrent = currentSelection === typeId;
      option.textContent = totalForType > 1 ? label + ' (' + available + ')' : label;

      if (isCurrent) {
        option.selected = true;
      } else if (totalForType === 0) {
        option.disabled = true;
      }

      ticketTypeSelect.appendChild(option);
    });

    if (currentSelection) {
      ticketTypeSelect.value = currentSelection;
      setSelection(index, currentSelection);
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
    rebuildRemaining: rebuildRemaining,
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
