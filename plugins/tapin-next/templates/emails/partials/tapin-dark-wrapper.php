<?php
/**
 * Dark Tapin email wrapper layout.
 *
 * Variables expected to be provided by the parent template.
 *
 * @var string $site_name
 * @var string $site_url
 * @var string $preheader_text
 * @var string $header_title
 * @var string $body_html
 * @var string $qr_image_url
 * @var string $qr_image_alt
 * @var string $button_label
 * @var string $button_url
 * @var array<string,string> $meta_rows
 * @var string $additional_html
 * @var string $support_html
 * @var string $footer_html
 */

defined('ABSPATH') || exit;

$site_name       = $site_name ?? ($args['site_name'] ?? '');
$site_url        = $site_url ?? ($args['site_url'] ?? '');
$preheader_text  = $preheader_text ?? ($args['preheader_text'] ?? '');
$header_title    = $header_title ?? ($args['header_title'] ?? '');
$body_html       = $body_html ?? ($args['body_html'] ?? '');
$qr_image_url    = $qr_image_url ?? ($args['qr_image_url'] ?? '');
$qr_image_alt    = $qr_image_alt ?? ($args['qr_image_alt'] ?? '');
$button_label    = $button_label ?? ($args['button_label'] ?? '');
$button_url      = $button_url ?? ($args['button_url'] ?? '');
$meta_rows       = $meta_rows ?? ($args['meta_rows'] ?? []);
$additional_html = $additional_html ?? ($args['additional_html'] ?? '');
$support_html    = $support_html ?? ($args['support_html'] ?? '');
$footer_html     = $footer_html ?? ($args['footer_html'] ?? '');

if (!is_array($meta_rows)) {
    $meta_rows = [];
}
?>

<table dir="rtl" lang="he" style="margin: 0; padding: 0; border-collapse: collapse;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
    <tbody>
        <tr>
            <td style="padding: 24px 12px;" align="center">
                <?php if ($preheader_text !== '') : ?>
                <div style="opacity: 0; color: transparent; max-height: 0; overflow: hidden; line-height: 1px;">
                    <?php echo $preheader_text; ?>
                </div>
                <?php endif; ?>
                <table style="max-width: 620px; border-radius: 16px; overflow: hidden; background: #121212;" role="presentation" border="0" width="620" cellspacing="0" cellpadding="0">
                    <tbody>
                        <tr>
                            <td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
                                <?php echo $header_title; ?>
                            </td>
                        </tr>
                        <?php if ($body_html !== '') : ?>
                        <tr>
                            <td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
                                <?php echo $body_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($qr_image_url !== '') : ?>
                        <tr>
                            <td style="padding: 24px 24px 8px 24px; background: #121212;" align="center">
                                <img src="<?php echo esc_url($qr_image_url); ?>" alt="<?php echo esc_attr($qr_image_alt); ?>" style="max-width:260px;height:auto;" />
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($button_label !== '' && $button_url !== '') : ?>
                        <tr>
                            <td style="padding: 10px 24px 20px 24px; background: #121212;" align="center">
                                <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($button_url); ?>">
                                    <?php echo esc_html($button_label); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($meta_rows)) : ?>
                            <?php foreach ($meta_rows as $meta_label => $meta_value) : ?>
                                <?php
                                $meta_label_text = (string) $meta_label;
                                $meta_value_text = (string) $meta_value;

                                if ($meta_label_text === '' && $meta_value_text === '') {
                                    continue;
                                }
                                ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <?php if ($meta_label_text !== '') : ?>
                                <strong><?php echo esc_html($meta_label_text); ?></strong>
                                <?php endif; ?>
                                <?php if ($meta_value_text !== '') : ?>
                                <?php echo esc_html($meta_value_text); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($additional_html !== '') : ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <?php echo $additional_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($support_html !== '') : ?>
                        <tr>
                            <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
                                <?php echo $support_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="background: #151515; padding: 16px 24px; font-family: Arial,Helvetica,sans-serif; color: #bbbbbb; font-size: 12px; line-height: 1.7;">
                                <?php echo $footer_html; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>