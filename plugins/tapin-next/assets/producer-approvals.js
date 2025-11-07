(function () {
  var form = document.getElementById('tapinBulkForm');
  var approveAllButton = document.getElementById('tapinApproveAll');
  var approveAllField = document.getElementById('tapinApproveAllField');
  if (approveAllButton && approveAllField && form) {
    approveAllButton.addEventListener('click', function () {
      approveAllField.value = '1';
      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'bulk_approve';
      hidden.value = '1';
      form.appendChild(hidden);
      form.submit();
    });
  }

  var selectAllButton = document.getElementById('tapinPaSelectAll');
  if (selectAllButton && form) {
    selectAllButton.addEventListener('click', function () {
      var orderCheckboxes = Array.prototype.slice.call(form.querySelectorAll('.tapin-pa-order__checkbox[data-pending="1"]:not(:disabled)'));
      if (orderCheckboxes.length) {
        var hasUncheckedOrders = orderCheckboxes.some(function (cb) { return !cb.checked; });
        orderCheckboxes.forEach(function (cb) { cb.checked = hasUncheckedOrders; });
      }

      var attendeeBoxes = Array.prototype.slice.call(document.querySelectorAll('.tapin-pa-attendee__approve:not(:disabled)')).filter(function (cb) {
        return cb.offsetParent !== null;
      });
      if (attendeeBoxes.length) {
        var hasUncheckedAttendees = attendeeBoxes.some(function (cb) { return !cb.checked; });
        attendeeBoxes.forEach(function (cb) { cb.checked = hasUncheckedAttendees; });
      }
    });
  }

  var partialSaveButton = document.getElementById('tapinPaPartialSave');
  if (partialSaveButton && form) {
    partialSaveButton.addEventListener('click', function () {
      var existing = form.querySelector('input[name="bulk_partial"]');
      if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
      }
      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'bulk_partial';
      hidden.value = '1';
      form.appendChild(hidden);
      form.submit();
    });
  }

  Array.prototype.slice.call(document.querySelectorAll('[data-event-toggle]')).forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var wrapper = toggle.closest('.tapin-pa-event');
      if (!wrapper) {
        return;
      }
      var panel = wrapper.querySelector('.tapin-pa-event__panel');
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        toggle.setAttribute('aria-expanded', 'false');
        wrapper.classList.remove('is-open');
        if (panel) {
          panel.hidden = true;
        }
      } else {
        toggle.setAttribute('aria-expanded', 'true');
        wrapper.classList.add('is-open');
        if (panel) {
          panel.hidden = false;
        }
      }
    });
  });

  var searchInput = document.getElementById('tapinPaSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var term = searchInput.value.trim().toLowerCase();
      Array.prototype.slice.call(document.querySelectorAll('.tapin-pa-event')).forEach(function (eventEl) {
        var eventMatch = term === '' || (eventEl.getAttribute('data-search') || '').indexOf(term) !== -1;
        var orders = Array.prototype.slice.call(eventEl.querySelectorAll('.tapin-pa-order'));
        var orderMatch = false;
        orders.forEach(function (orderEl) {
          var matches = term === '' || (orderEl.getAttribute('data-search') || '').indexOf(term) !== -1;
          orderEl.style.display = matches ? '' : 'none';
          if (matches) {
            orderMatch = true;
          }
        });
        var visible = term === '' ? true : (eventMatch || orderMatch);
        eventEl.style.display = visible ? '' : 'none';
        if (visible && term !== '') {
          eventEl.classList.add('is-open');
          var toggle = eventEl.querySelector('[data-event-toggle]');
          var panel = eventEl.querySelector('.tapin-pa-event__panel');
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
          }
          if (panel) {
            panel.hidden = false;
          }
        }
      });
    });
  }
})();

