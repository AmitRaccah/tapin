(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};
  var messages = (namespace.Messages && namespace.Messages.all) || {};
  var utils = namespace.Utils || {};

  var refs = {};
  var fieldConfig = {};
  var fieldKeys = [];
  var allowSubmit = false;
  var attendees = [];

  function setRefs(newRefs) {
    refs = newRefs || {};
  }

  function setFields(cfg) {
    fieldConfig = cfg || {};
    fieldKeys = Object.keys(fieldConfig);
  }

  function resetErrors() {
    if (!refs.modal) {
      return;
    }
    refs.modal.querySelectorAll('.tapin-field__error').forEach(function (el) {
      el.textContent = '';
    });
    refs.modal.querySelectorAll('.tapin-field input, .tapin-field select').forEach(function (input) {
      input.classList.remove('tapin-field--invalid');
    });
    refs.modal.querySelectorAll('.tapin-choice').forEach(function (choice) {
      choice.classList.remove('tapin-choice--invalid');
    });
    if (refs.ticketTypeError) {
      refs.ticketTypeError.textContent = '';
    }
  }

  function updateChoiceState(fieldKey, value) {
    if (!refs.modal) {
      return;
    }
    var wrapper = refs.modal.querySelector('[data-field-key="' + fieldKey + '"]');
    if (!wrapper) {
      return;
    }
    var group = wrapper.querySelector('[data-choice-group]');
    if (!group) {
      return;
    }
    group.querySelectorAll('.tapin-choice__option').forEach(function (btn) {
      btn.classList.toggle('is-selected', btn.getAttribute('data-choice-value') === value);
    });
  }

  var setupChoiceFields = utils.once(function () {
    if (!refs.modal) {
      return;
    }
    refs.modal.querySelectorAll('.tapin-choice__option').forEach(function (btn) {
      if (btn.dataset.tapinChoiceInit === 'true') {
        return;
      }
      btn.dataset.tapinChoiceInit = 'true';
      btn.addEventListener('click', function () {
        var fieldWrapper = btn.closest('[data-field-key]');
        if (!fieldWrapper) {
          return;
        }
        var fieldKey = fieldWrapper.getAttribute('data-field-key');
        if (!fieldKey) {
          return;
        }
        var input = fieldWrapper.querySelector('[data-field="' + fieldKey + '"]');
        if (!input) {
          return;
        }
        var choiceValue = btn.getAttribute('data-choice-value') || '';
        input.value = choiceValue;
        updateChoiceState(fieldKey, choiceValue);
      });
    });
  });

  function updateRequiredIndicators(isPayer) {
    if (!refs.formContainer) {
      return;
    }
    refs.formContainer.querySelectorAll('.tapin-field').forEach(function (fieldEl) {
      var indicator = fieldEl.querySelector('[data-required-indicator]');
      if (!indicator) {
        return;
      }
      var input = fieldEl.querySelector('[data-field]');
      if (!input) {
        return;
      }
      var required = (isPayer && input.dataset.requiredPayer === 'true') || (!isPayer && input.dataset.requiredAttendee === 'true');
      if (required) {
        indicator.removeAttribute('hidden');
      } else {
        indicator.setAttribute('hidden', 'hidden');
      }
    });
  }

  function markInvalid(input, message, fieldType) {
    input.classList.add('tapin-field--invalid');
    var wrapper = input.closest('.tapin-field');
    if (wrapper && fieldType === 'choice') {
      var group = wrapper.querySelector('[data-choice-group]');
      if (group) {
        group.classList.add('tapin-choice--invalid');
      }
    }
    if (!wrapper) {
      return;
    }
    var errorEl = wrapper.querySelector('.tapin-field__error');
    if (errorEl) {
      errorEl.textContent = message;
    }
  }

  function collectCurrentStep(isPayer, currentIndex, onTicketTypeChosen) {
    resetErrors();
    var result = {};
    var isValid = true;

    fieldKeys.forEach(function (key) {
      var field = fieldConfig[key] || {};
      var fieldType = field.type || 'text';
      var input = refs.formContainer ? refs.formContainer.querySelector('[data-field="' + key + '"]') : null;
      if (!input) {
        return;
      }

      var rawValue = String(input.value || '').trim();
      var required = (isPayer && input.dataset.requiredPayer === 'true') || (!isPayer && input.dataset.requiredAttendee === 'true');
      var value = rawValue;

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
        value = utils.normalizeInstagram(value);
      } else if (key === 'tiktok') {
        value = utils.normalizeTikTok(value);
      }

      if (key === 'email') {
        var email = value.toLowerCase();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          isValid = false;
          markInvalid(input, messages.invalidEmail || messages.required || 'שדה חובה.', fieldType);
          return;
        }
        value = email;
      }

      if (key === 'phone') {
        var digits = value.replace(/\D+/g, '');
        if (digits.length < 10) {
          isValid = false;
          markInvalid(input, messages.invalidPhone || messages.required || 'שדה חובה.', fieldType);
          return;
        }
      }

      if (key === 'id_number') {
        var idDigits = value.replace(/\D+/g, '');
        if (idDigits.length !== 9) {
          isValid = false;
          markInvalid(input, messages.invalidId || messages.required || 'שדה חובה.', fieldType);
          return;
        }
      }

      result[key] = value;
    });

    if (refs.ticketTypeSelect) {
      var selectedTypeId = refs.ticketTypeSelect.value;
      if (!selectedTypeId) {
        isValid = false;
        refs.ticketTypeSelect.classList.add('tapin-field--invalid');
        if (refs.ticketTypeError) {
          refs.ticketTypeError.textContent = messages.ticketSelectError || messages.required || '';
        }
      } else {
        refs.ticketTypeSelect.classList.remove('tapin-field--invalid');
        if (refs.ticketTypeError) {
          refs.ticketTypeError.textContent = '';
        }
        if (typeof onTicketTypeChosen === 'function') {
          onTicketTypeChosen(selectedTypeId);
        }
      }
    }

    return isValid ? result : null;
  }

  function prefill(index, cfgPrefill, existingData) {
    if (!refs.formContainer) {
      return;
    }
    var values = {};
    fieldKeys.forEach(function (key) {
      values[key] = '';
    });
    if (index === 0 && cfgPrefill) {
      Object.keys(cfgPrefill).forEach(function (key) {
        values[key] = cfgPrefill[key];
      });
    }
    if (existingData) {
      Object.keys(existingData).forEach(function (key) {
        if (typeof existingData[key] !== 'undefined') {
          values[key] = existingData[key];
        }
      });
    }

    fieldKeys.forEach(function (key) {
      var input = refs.formContainer.querySelector('[data-field="' + key + '"]');
      if (!input) {
        return;
      }
      var value = values[key] || '';
      input.value = value;
      updateChoiceState(key, value);
    });

    if (refs.ticketTypeSelect) {
      refs.ticketTypeSelect.value = '';
      refs.ticketTypeSelect.classList.remove('tapin-field--invalid');
    }
    if (refs.ticketTypeError) {
      refs.ticketTypeError.textContent = '';
    }
  }

  function setQtyInputValue(value) {
    if (!refs.qtyInput) {
      return;
    }
    var normalized = Math.max(0, Math.round(value || 0));
    var formatted = String(normalized);
    if (refs.qtyInput.value !== formatted) {
      refs.qtyInput.value = formatted;
      refs.qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      refs.qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function submitForm() {
    if (!refs.form) {
      return;
    }
    if (typeof refs.form.requestSubmit === 'function') {
      refs.form.requestSubmit(refs.submitButton || undefined);
      return;
    }
    if (refs.submitButton && typeof refs.submitButton.click === 'function') {
      refs.submitButton.click();
      return;
    }
    refs.form.submit();
  }

  function validateBeforeFinalize(attendeeList, ticketSelection) {
    var totalTickets = Object.keys(ticketSelection || {}).reduce(function (sum, typeId) {
      var count = Number(ticketSelection[typeId] || 0);
      return sum + (Number.isFinite(count) ? count : 0);
    }, 0);
    if (attendeeList.length !== totalTickets) {
      return false;
    }
    var multipleTypes = Object.keys(ticketSelection || {}).filter(function (typeId) {
      return Number(ticketSelection[typeId] || 0) > 0;
    }).length > 1;
    if (!multipleTypes) {
      return true;
    }
    for (var i = 0; i < attendeeList.length; i += 1) {
      if (!attendeeList[i] || !attendeeList[i].ticket_type) {
        return false;
      }
    }
    return true;
  }

  function finalize(attendeeList, attendeePlan, ticketSelection) {
    if (!refs.hiddenField) {
      return;
    }
    attendees = attendeeList.slice();
    var plan = attendeePlan || [];
    attendees.forEach(function (attendee, index) {
      var slot = plan[index] || null;
      var selectedTypeName = slot && slot.label ? slot.label : '';
      if (!attendee.ticket_type && slot && slot.typeId) {
        attendee.ticket_type = slot.typeId;
      }
      if (!attendee.ticket_type_label && selectedTypeName) {
        attendee.ticket_type_label = selectedTypeName;
      }
    });

    if (typeof window !== 'undefined' && window.TAPIN_TICKET_DEBUG) {
      try {
        var dbg = attendees.map(function (a) {
          return {
            type: a && a.ticket_type ? String(a.ticket_type) : '',
            label: a && a.ticket_type_label ? String(a.ticket_type_label) : '',
            price: (a && typeof a.ticket_price !== 'undefined') ? a.ticket_price : null,
          };
        });
        console.debug('[tapin_tickets] finalize attendees', { count: attendees.length, attendees: dbg });
      } catch (e) {}
    }

    refs.hiddenField.value = JSON.stringify(attendees);
    setQtyInputValue(attendees.length);
    allowSubmit = true;
    if (namespace.Modal && typeof namespace.Modal.close === 'function') {
      namespace.Modal.close(false);
    }
    setTimeout(function () {
      submitForm();
    }, 0);
    return true;
  }

  function shouldAllowSubmit() {
    return allowSubmit;
  }

  function consumeSubmitAllowance() {
    allowSubmit = false;
  }

  namespace.Form = {
    setRefs: setRefs,
    setFields: setFields,
    resetErrors: resetErrors,
    updateChoiceState: updateChoiceState,
    setupChoiceFields: setupChoiceFields,
    updateRequiredIndicators: updateRequiredIndicators,
    markInvalid: markInvalid,
    collectCurrentStep: collectCurrentStep,
    prefill: prefill,
    setQtyInputValue: setQtyInputValue,
    submitForm: submitForm,
    validateBeforeFinalize: validateBeforeFinalize,
    finalize: finalize,
    shouldAllowSubmit: shouldAllowSubmit,
    consumeSubmitAllowance: consumeSubmitAllowance,
    getAttendees: function () {
      return attendees.slice();
    },
  };

  return namespace.Form;
});
