<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Messaging;

final class MessagesProvider
{
    /** @var array<string,string>|null */
    private ?array $messages = null;

    /**
     * @return array<string,string>
     */
    public function getModalMessages(): array
    {
        if ($this->messages !== null) {
            return $this->messages;
        }

        $this->messages = [
            'title'                  => __('פרטי ההזמנה', 'tapin'),
            'ticketStepTitle'        => __('בחרו את הכרטיסים שלכם', 'tapin'),
            'ticketStepSubtitle'     => __('בחרו כמה כרטיסים ברצונכם לרכוש מכל סוג זמין.', 'tapin'),
            'ticketStepNext'         => __('המשך', 'tapin'),
            'ticketStepError'        => __('בחרו לפחות כרטיס אחד כדי להמשיך.', 'tapin'),
            'ticketStepSoldOut'      => __('אזל המלאי', 'tapin'),
            'ticketStepIncluded'     => __('כלול', 'tapin'),
            'ticketStepAvailability' => __('זמין: %s', 'tapin'),
            'ticketStepNoLimit'      => __('ללא הגבלה', 'tapin'),
            'ticketStepDecrease'     => __('הפחת כרטיס', 'tapin'),
            'ticketStepIncrease'     => __('הוסף כרטיס', 'tapin'),
            'ticketTotalLabel'       => __('סה״כ כרטיסים:', 'tapin'),
            'ticketHintLabel'        => __('סוג הכרטיס:', 'tapin'),
            'ticketSelectPlaceholder'=> __('בחרו סוג כרטיס', 'tapin'),
            'ticketSelectError'      => __('בחרו סוג כרטיס עבור משתתף זה.', 'tapin'),
            'payerTitle'             => __('פרטי המזמין', 'tapin'),
            'participantTitle'       => __('משתתף %1$s', 'tapin'),
            'step'                   => __('משתתף %1$s מתוך %2$s', 'tapin'),
            'next'                   => __('הבא', 'tapin'),
            'finish'                 => __('סיום והמשך לתשלום', 'tapin'),
            'cancel'                 => __('ביטול', 'tapin'),
            'back'                   => __('חזור', 'tapin'),
            'close'                  => __('סגירת חלון', 'tapin'),
            'required'               => __('שדה חובה.', 'tapin'),
            'invalidEmail'           => __('הזינו כתובת דוא״ל תקינה.', 'tapin'),
            'invalidInstagram'       => __('הזינו שם משתמש אינסטגרם תקין.', 'tapin'),
            'invalidTikTok'          => __('הזינו שם משתמש טיקטוק תקין.', 'tapin'),
            'invalidFacebook'        => __('הזינו כתובת פייסבוק תקינה.', 'tapin'),
            'invalidPhone'           => __('הזינו מספר טלפון תקין (10 ספרות).', 'tapin'),
            'invalidId'              => __('הזינו מספר זהות תקין (9 ספרות).', 'tapin'),
        ];

        return $this->messages;
    }
}
