(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    factory(root);
  }
})(typeof self !== 'undefined' ? self : this, function (root) {
  var namespace = root.TapinPurchase = root.TapinPurchase || {};
  var data = root.TapinPurchaseModalData || {};
  var overrides = data.messages || {};

  var defaults = {
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
  };

  var messages = {};
  Object.keys(defaults).forEach(function (key) {
    messages[key] = typeof overrides[key] !== 'undefined' ? overrides[key] : defaults[key];
  });

  namespace.Messages = {
    all: messages,
    get: function (key) {
      return messages[key];
    },
  };

  return namespace.Messages;
});
