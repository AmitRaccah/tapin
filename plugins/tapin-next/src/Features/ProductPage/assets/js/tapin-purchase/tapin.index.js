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

  var refs = null;
  var attendees = [];
  var attendeePlan = [];
  var totalAttendees = 0;
  var currentIndex = 0;
  var initialized = false;

  function assignRefs() {
    if (!refs) {
      return;
    }
    var sharedRefs = {
      form: refs.form,
      modal: refs.modal,
      hiddenField: refs.hiddenField,
      submitButton: refs.submitButton,
      qtyInput: refs.qtyInput,
      formContainer: refs.formContainer,
      ticketStep: refs.ticketStep,
      ticketList: refs.ticketList,
      ticketTotal: refs.ticketTotal,
      ticketTotalLabel: refs.ticketTotalLabel,
      ticketError: refs.ticketError,
      ticketHint: refs.ticketHint,
      title: refs.title,
      stepText: refs.stepText,
      nextButton: refs.nextButton,
      cancelButton: refs.cancelButton,
      closeButtons: refs.closeButtons,
      ticketTypeSelect: refs.ticketTypeSelect,
      ticketTypeError: refs.ticketTypeError,
    };
    namespace.Form.setRefs(sharedRefs);
    namespace.Plan.setRefs(sharedRefs);
    namespace.Modal.setRefs(sharedRefs);
  }

  function updateTicketTotals() {
    var total = namespace.Tickets ? namespace.Tickets.getTotal() : 0;
    if (refs.ticketTotalLabel) {
      refs.ticketTotalLabel.textContent = messages.ticketTotalLabel || '';
    }
    if (refs.ticketTotal) {
      refs.ticketTotal.textContent = String(total);
    }
    if (refs.ticketError) {
      refs.ticketError.textContent = '';
      refs.ticketError.hidden = true;
    }
    namespace.Form.setQtyInputValue(total);
  }

  function resetAll() {
    attendees = [];
    attendeePlan = [];
    totalAttendees = 0;
    currentIndex = 0;
    if (refs.hiddenField) {
      refs.hiddenField.value = '';
    }
    if (namespace.Tickets) {
      namespace.Tickets.resetAndSelectDefault();
    }
    namespace.Form.consumeSubmitAllowance();
    namespace.Form.resetErrors();
    namespace.Form.prefill(0, data.prefill || null, attendees[0] || null);
    namespace.Form.updateRequiredIndicators(true);
    if (refs.ticketHint) {
      refs.ticketHint.hidden = true;
      refs.ticketHint.textContent = '';
    }
    updateTicketTotals();
    namespace.Plan.setCurrentIndex(0);
    namespace.Modal.showTicketPhase();
    syncHeader();
  }

  function syncHeader() {
    namespace.Modal.updateTitle(currentIndex);
    namespace.Modal.updateStepIndicator(currentIndex, totalAttendees);
    namespace.Modal.updateNextButtonText(currentIndex, totalAttendees);
  }

  function openModal() {
    resetAll();
    namespace.Modal.open();
    syncHeader();
  }

  function handleFormSubmit(event) {
    if (namespace.Form.shouldAllowSubmit()) {
      namespace.Form.consumeSubmitAllowance();
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }
    openModal();
  }

  function handleSubmitButton(event) {
    if (namespace.Form.shouldAllowSubmit()) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }
    openModal();
  }

  function handleEsc(event) {
    if (event.key !== 'Escape') {
      return;
    }
    if (!refs.modal || !refs.modal.classList.contains('is-open')) {
      return;
    }
    namespace.Modal.close(true);
  }

  function ensureTicketSelectionValid(total) {
    if (total > 0) {
      return true;
    }
    if (refs.ticketError) {
      refs.ticketError.textContent = messages.ticketStepError || '';
      refs.ticketError.hidden = false;
    }
    return false;
  }

  function beginAttendeePhase() {
    attendeePlan = namespace.Plan.buildFromSelection();
    totalAttendees = attendeePlan.length;
    if (!totalAttendees) {
      ensureTicketSelectionValid(0);
      return false;
    }
    namespace.Form.setQtyInputValue(totalAttendees);
    attendees = [];
    currentIndex = 0;
    namespace.Plan.setCurrentIndex(0);
    namespace.Form.resetErrors();
    namespace.Form.prefill(0, data.prefill || null, attendees[0] || null);
    namespace.Form.updateRequiredIndicators(true);
    namespace.Plan.populateSelect(0);
    namespace.Plan.updateHint(0);
    namespace.Modal.showAttendeePhase();
    syncHeader();
    return true;
  }

  function goToNextAttendee() {
    currentIndex += 1;
    namespace.Plan.setCurrentIndex(currentIndex);
    namespace.Form.resetErrors();
    namespace.Form.prefill(currentIndex, data.prefill || null, attendees[currentIndex] || null);
    namespace.Form.updateRequiredIndicators(currentIndex === 0);
    namespace.Plan.populateSelect(currentIndex);
    namespace.Plan.updateHint(currentIndex);
    syncHeader();
  }

  function handleNext() {
    var phase = namespace.Modal.getPhase ? namespace.Modal.getPhase() : 'ticket';
    if (phase === 'ticket') {
      var total = namespace.Tickets ? namespace.Tickets.getTotal() : 0;
      if (!ensureTicketSelectionValid(total)) {
        return;
      }
      if (refs.ticketError) {
        refs.ticketError.textContent = '';
        refs.ticketError.hidden = true;
      }
      beginAttendeePhase();
      return;
    }

    var isPayer = currentIndex === 0;
    var dataStep = namespace.Form.collectCurrentStep(isPayer, currentIndex, function (typeId) {
      namespace.Plan.setSelection(currentIndex, typeId);
      namespace.Plan.populateSelect(currentIndex);
    });
    if (!dataStep) {
      return;
    }
    var slot = namespace.Plan.getSlot(currentIndex);
    if (slot) {
      dataStep.ticket_type = slot.typeId || '';
      dataStep.ticket_type_label = slot.label || '';
    }
    attendees[currentIndex] = dataStep;

    if (currentIndex + 1 < totalAttendees) {
      goToNextAttendee();
      return;
    }

    attendeePlan = namespace.Plan.attendeePlan();
    var selectionSnapshot = namespace.Tickets && typeof namespace.Tickets.getSelectionSnapshot === 'function'
      ? namespace.Tickets.getSelectionSnapshot()
      : (namespace.Tickets ? namespace.Tickets.selection : {});
    if (!namespace.Form.validateBeforeFinalize(attendees, selectionSnapshot)) {
      return;
    }
    namespace.Form.finalize(attendees, attendeePlan, selectionSnapshot);
  }

  function bindEvents() {
    if (refs.form) {
      refs.form.addEventListener('submit', handleFormSubmit);
    }
    if (refs.submitButton) {
      refs.submitButton.addEventListener('click', handleSubmitButton);
    }
    if (refs.nextButton) {
      refs.nextButton.addEventListener('click', handleNext);
    }
    if (refs.cancelButton) {
      refs.cancelButton.addEventListener('click', function () {
        namespace.Modal.close(true);
      });
    }
    if (refs.closeButtons && refs.closeButtons.length) {
      Array.prototype.forEach.call(refs.closeButtons, function (btn) {
        btn.addEventListener('click', function () {
          namespace.Modal.close(true);
        });
      });
    }
    document.addEventListener('keydown', handleEsc);
    if (refs.ticketTypeSelect) {
      refs.ticketTypeSelect.addEventListener('change', function () {
        var value = refs.ticketTypeSelect.value;
        if (!value) {
          return;
        }
        namespace.Plan.setSelection(currentIndex, value);
        namespace.Plan.populateSelect(currentIndex);
        namespace.Plan.updateHint(currentIndex);
        refs.ticketTypeSelect.classList.remove('tapin-field--invalid');
        if (refs.ticketTypeError) {
          refs.ticketTypeError.textContent = '';
        }
      });
    }
  }

  function init() {
    if (initialized) {
      return;
    }
    refs = namespace.DOM ? namespace.DOM.capture() : null;
    if (!refs) {
      return;
    }
    assignRefs();
    namespace.Form.setFields(data.fields || {});
    namespace.Tickets.mount(refs.ticketList);
    namespace.Form.setupChoiceFields();
    updateTicketTotals();
    resetAll();
    bindEvents();
    initialized = true;
  }

  namespace.Controller = {
    init: init,
    resetAll: resetAll,
    updateTicketTotals: updateTicketTotals,
    syncHeader: syncHeader,
    handleNext: handleNext,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return namespace.Controller;
});
