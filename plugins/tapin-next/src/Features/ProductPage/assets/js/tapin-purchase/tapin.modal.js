(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};
  var messages = (namespace.Messages && namespace.Messages.all) || {};
  var format = namespace.Utils ? namespace.Utils.format : function (str) { return str; };

  var refs = {};
  var phase = 'ticket';

  function setRefs(newRefs) {
    refs = newRefs || {};
  }

  function updateTitle(currentIndex) {
    if (!refs.title) {
      return;
    }
    if (phase === 'ticket') {
      refs.title.textContent = messages.ticketStepTitle || messages.title || '';
      return;
    }
    if (typeof currentIndex === 'number' && currentIndex === 0) {
      refs.title.textContent = messages.payerTitle || messages.title || '';
      return;
    }
    if (messages.participantTitle) {
      refs.title.textContent = format(messages.participantTitle, (currentIndex || 0) + 1);
      return;
    }
    refs.title.textContent = messages.title || '';
  }

  function updateStepIndicator(currentIndex, totalAttendees) {
    if (!refs.stepText) {
      return;
    }
    if (phase === 'ticket') {
      refs.stepText.textContent = messages.ticketStepSubtitle || '';
      return;
    }
    refs.stepText.textContent = format(messages.step || '', (currentIndex || 0) + 1, totalAttendees || 0);
  }

  function updateNextButtonText(currentIndex, totalAttendees) {
    if (!refs.nextButton) {
      return;
    }
    if (phase === 'ticket') {
      refs.nextButton.textContent = messages.ticketStepNext || messages.next || '';
      return;
    }
    var isLast = typeof currentIndex === 'number' && typeof totalAttendees === 'number' && currentIndex + 1 >= totalAttendees;
    refs.nextButton.textContent = isLast ? messages.finish || messages.next || '' : messages.next || '';
  }

  function showTicketPhase() {
    phase = 'ticket';
    if (refs.ticketStep) {
      refs.ticketStep.removeAttribute('hidden');
    }
    if (refs.formContainer) {
      refs.formContainer.setAttribute('hidden', 'hidden');
    }
    if (refs.ticketHint) {
      refs.ticketHint.hidden = true;
      refs.ticketHint.textContent = '';
    }
  }

  function showAttendeePhase() {
    phase = 'form';
    if (refs.ticketStep) {
      refs.ticketStep.setAttribute('hidden', 'hidden');
    }
    if (refs.formContainer) {
      refs.formContainer.removeAttribute('hidden');
    }
    if (refs.formContainer) {
      var firstInput = refs.formContainer.querySelector('input');
      if (firstInput) {
        firstInput.focus();
      }
    }
  }

  function open() {
    phase = 'ticket';
    showTicketPhase();
    if (refs.cancelButton) {
      refs.cancelButton.textContent = messages.cancel || '';
    }
    if (refs.modal) {
      refs.modal.classList.add('is-open');
      refs.modal.removeAttribute('hidden');
    }
    if (refs.ticketStep) {
      var focusTarget = refs.ticketStep.querySelector('[data-ticket-card] [data-ticket-action="increase"]');
      if (focusTarget) {
        focusTarget.focus();
      }
    }
  }

  function close(resetData) {
    if (resetData === undefined) {
      resetData = true;
    }
    if (refs.modal) {
      refs.modal.classList.remove('is-open');
      refs.modal.setAttribute('hidden', 'hidden');
    }
    if (resetData && namespace.Controller && typeof namespace.Controller.resetAll === 'function') {
      namespace.Controller.resetAll();
    }
    if (refs.submitButton) {
      refs.submitButton.focus();
    }
  }

  namespace.Modal = {
    setRefs: setRefs,
    updateTitle: updateTitle,
    updateStepIndicator: updateStepIndicator,
    updateNextButtonText: updateNextButtonText,
    showTicketPhase: showTicketPhase,
    showAttendeePhase: showAttendeePhase,
    open: open,
    close: close,
    getPhase: function () {
      return phase;
    },
  };

  return namespace.Modal;
});
