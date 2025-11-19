<?php
/**
 * Shared dark Tapin email wrapper.
 *
 * @var array<string,mixed> $args Template arguments supplied via wc_get_template().
 */

defined('ABSPATH') || exit;

$preheader_text  = $args['preheader_text']  ?? '';
$header_html     = $args['header_html']     ?? '';
$body_html       = $args['body_html']       ?? '';
$qr_image_html   = $args['qr_image_html']   ?? '';
$button_html     = $args['button_html']     ?? '';
$meta_rows_html  = $args['meta_rows_html']  ?? '';
$additional_html = $args['additional_html'] ?? '';
$footer_html     = $args['footer_html']     ?? '';
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
                        <?php if ($header_html !== '') : ?>
                        <tr>
                            <?php echo $header_html; ?>
                        </tr>
                        <?php endif; ?>
                        <?php if ($body_html !== '') : ?>
                        <tr>
                            <?php echo $body_html; ?>
                        </tr>
                        <?php endif; ?>
                        <?php if ($qr_image_html !== '') : ?>
                        <tr>
                            <td style="padding: 24px 24px 8px 24px; background: #121212;" align="center">
                                <?php echo $qr_image_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($button_html !== '') : ?>
                        <tr>
                            <td style="padding: 10px 24px 20px 24px; background: #121212;" align="center">
                                <?php echo $button_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($meta_rows_html !== '') : ?>
                        <?php echo $meta_rows_html; ?>
                        <?php endif; ?>
                        <?php if ($additional_html !== '') : ?>
                        <tr>
                            <?php echo $additional_html; ?>
                        </tr>
                        <?php endif; ?>
                        <?php if ($footer_html !== '') : ?>
                        <tr>
                            <td style="background: #151515; padding: 16px 24px; font-family: Arial,Helvetica,sans-serif; color: #bbbbbb; font-size: 12px; line-height: 1.7;">
                                <?php echo $footer_html; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
