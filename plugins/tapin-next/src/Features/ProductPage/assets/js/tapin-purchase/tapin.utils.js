(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};

  function format(str) {
    var args = Array.prototype.slice.call(arguments, 1);
    return String(str || '').replace(/%(\d+)\$s/g, function (match, index) {
      var i = parseInt(index, 10) - 1;
      return typeof args[i] !== 'undefined' ? args[i] : match;
    });
  }

  function normalizeInstagram(value) {
    var trimmed = String(value || '').trim();
    if (!trimmed) {
      return '';
    }
    var match = trimmed.match(/instagram\.com\/(@?[^/?#]+)/i);
    var handle = match ? match[1] : trimmed.replace(/^@+/, '').replace(/^\/+/, '');
    var normalized = handle.replace(/\/+$/, '').toLowerCase();
    return /^[a-z0-9._]{1,30}$/.test(normalized) ? normalized : '';
  }

  function normalizeTikTok(value) {
    var trimmed = String(value || '').trim();
    if (!trimmed) {
      return '';
    }
    var match = trimmed.match(/tiktok\.com\/@([^/?#]+)/i);
    var handle = match ? match[1] : trimmed.replace(/^@+/, '').replace(/^\/+/, '');
    var normalized = handle.replace(/\/+$/, '').toLowerCase();
    return /^[a-z0-9._]{1,24}$/.test(normalized) ? normalized : '';
  }

  function num(value) {
    var parsed = typeof value === 'number' ? value : parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function int(value) {
    var parsed = typeof value === 'number' ? value : parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function once(fn) {
    var called = false;
    var result;
    return function () {
      if (!called) {
        called = true;
        result = fn.apply(this, arguments);
      }
      return result;
    };
  }

  namespace.Utils = {
    format: format,
    normalizeInstagram: normalizeInstagram,
    normalizeTikTok: normalizeTikTok,
    num: num,
    int: int,
    once: once,
  };

  return namespace.Utils;
});
