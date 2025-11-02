(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};

  function capture() {
    var form = document.querySelector('form.cart');
    if (!form) {
      return null;
    }

    var modal = document.getElementById('tapinPurchaseModal');
    if (!modal) {
      return null;
    }

    var hiddenField = document.getElementById('tapinAttendeesField');
    var submitButton = form.querySelector('.single_add_to_cart_button');
    if (!hiddenField || !submitButton) {
      return null;
    }

    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }

    var qtyInput = form.querySelector('input.qty, input[name="quantity"]');
    var formContainer = modal.querySelector('[data-form-container]');
    var ticketStep = modal.querySelector('[data-ticket-step]');
    var ticketList = ticketStep
      ? ticketStep.querySelector('[data-ticket-list]') || ticketStep.querySelector('.tapin-ticket-step__list')
      : null;
    var ticketTotal = modal.querySelector('[data-ticket-total-count]');
    var ticketTotalLabel = modal.querySelector('[data-ticket-total-label]');
    var ticketError = modal.querySelector('[data-ticket-error]');
    var ticketHint = modal.querySelector('[data-ticket-hint]');
    var title = modal.querySelector('.tapin-purchase-modal__title');
    var stepText = modal.querySelector('[data-step-text]');
    var nextButton = modal.querySelector('[data-modal-action="next"]');
    var backButton = modal.querySelector('[data-modal-action="back"]');
    var cancelButton = modal.querySelector('[data-modal-role="cancel"]');
    var closeButtons = modal.querySelectorAll('[data-modal-dismiss]');
    var ticketTypeField = formContainer ? formContainer.querySelector('[data-ticket-type-field]') : null;
    var ticketTypeSelect = formContainer ? formContainer.querySelector('[data-ticket-type-select]') : null;
    var ticketTypeError = formContainer ? formContainer.querySelector('[data-ticket-type-error]') : null;

    return {
      form: form,
      modal: modal,
      hiddenField: hiddenField,
      submitButton: submitButton,
      qtyInput: qtyInput,
      formContainer: formContainer,
      ticketStep: ticketStep,
      ticketList: ticketList,
      ticketTotal: ticketTotal,
      ticketTotalLabel: ticketTotalLabel,
      ticketError: ticketError,
      ticketHint: ticketHint,
      title: title,
      stepText: stepText,
      nextButton: nextButton,
      backButton: backButton,
      cancelButton: cancelButton,
      closeButtons: closeButtons,
      ticketTypeField: ticketTypeField,
      ticketTypeSelect: ticketTypeSelect,
      ticketTypeError: ticketTypeError,
    };
  }

  namespace.DOM = {
    capture: capture,
  };

  return namespace.DOM;
});
