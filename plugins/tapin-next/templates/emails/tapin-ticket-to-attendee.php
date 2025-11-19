<?php
/**
 * Ticket email sent to Tapin attendees.
 *
 * @var WC_Order                                                 $order
 * @var string                                                   $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_TicketToAttendee $email
 * @var array<string,mixed>                                      $ticket
 * @var string                                                   $qr_image_url
 */

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);

$site_name = trim((string) $email->get_blogname());
if ($site_name === '') {
    $site_name = get_bloginfo('name', 'display');
}
if ($site_name === '') {
    $site_name = get_bloginfo('name');
}

$site_url = home_url('/');
$login_url = wc_get_page_permalink('myaccount');
if (empty($login_url)) {
    $login_url = $site_url;
}

$full_name = trim((string) ($ticket['full_name'] ?? ''));
$display_name = $full_name !== '' ? $full_name : esc_html__( 'לקוח שלנו', 'tapin' );

$attendee_email = trim((string) ($ticket['email'] ?? ''));

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf( esc_html__( 'הזמנה #%s', 'tapin' ),
        (string) ($ticket['order_id'] ?? '')
    );
}

$additional_content = $email->get_additional_content();
?>
<table dir="rtl" lang="he" style="margin: 0; padding: 0; border-collapse: collapse;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
    <tbody>
        <tr>
            <td style="padding: 24px 12px;" align="center">
                <div style="opacity: 0; color: transparent; max-height: 0; overflow: hidden; line-height: 1px;">
                    <?php
                    printf(
                        esc_html__( 'ברוך/ה הבא/ה ל%s. הכרטיס שלך מוכן לסריקה בכניסה.', 'tapin' ),
                        esc_html($site_name)
                    );
                    ?>
                </div>
                <table style="max-width: 620px; border-radius: 16px; overflow: hidden; background: #121212;" role="presentation" border="0" width="620" cellspacing="0" cellpadding="0">
                    <tbody>
                        <tr>
                            <td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
                                <?php esc_html_e('ברוך/ה הבא/ה ל', 'tapin'); ?>
                                <span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
                                <?php esc_html_e('היי,', 'tapin'); ?> <strong><?php echo esc_html($display_name); ?></strong>.<br />
                                <?php esc_html_e('הכרטיס שלך לאירוע', 'tapin'); ?>
                                <strong><?php echo esc_html($label); ?></strong>
                                <?php esc_html_e('מוכן. מצורף בהמשך ברקוד לסריקה בכניסה.', 'tapin'); ?>
                            </td>
                        </tr>
                        <?php if ($qr_image_url !== '') : ?>
                        <tr>
                            <td style="padding: 24px 24px 8px 24px; background: #121212;" align="center">
                                <img src="<?php echo esc_url($qr_image_url); ?>" alt="<?php esc_attr_e( 'ברקוד הכרטיס שלך', 'tapin' ); ?>" style="max-width:260px;height:auto;" />
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 10px 24px 20px 24px; background: #121212;" align="center">
                                <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($login_url); ?>">
                                    <?php esc_html_e('לצפייה בהזמנה', 'tapin'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php if ($attendee_email !== '') : ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <strong><?php esc_html_e('אימייל:', 'tapin'); ?></strong>
                                <?php echo esc_html($attendee_email); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($additional_content) : ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <?php esc_html_e('צריך עזרה? אפשר להשיב למייל זה או לכתוב לנו ב-', 'tapin'); ?>
                                <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>.
                            </td>
                        </tr>
                        <tr>
                            <td style="background: #151515; padding: 16px 24px; font-family: Arial,Helvetica,sans-serif; color: #bbbbbb; font-size: 12px; line-height: 1.7;">
                                <?php
                                printf(
                                    esc_html__( 'הודעה זו נשלחה אותומתית בעקבות הזמנה באתר %s.', 'tapin' ),
                                    esc_html($site_name)
                                );
                                ?>
                                <?php esc_html_e('עיון ב', 'tapin'); ?>
                                <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('אתר', 'tapin'); ?></a>,
                                <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('מדיניות הפרטיות', 'tapin'); ?></a>
                                <?php esc_html_e('ו-', 'tapin'); ?>
                                <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('תנאי השימוש', 'tapin'); ?></a>.
                                <?php esc_html_e('כתובת קשר:', 'tapin'); ?>
                                <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<?php do_action('woocommerce_email_footer', $email); ?>