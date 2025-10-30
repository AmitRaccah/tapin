import re
from pathlib import Path
path = Path(r"plugins/tapin-next/src/Features/ProductPage/SaleWindowsCards.php")
text = path.read_text(encoding="utf-8")
text = re.sub(r"echo '<style>' .*?<div class=\"tapin-pw__grid\">", "echo '<style>' . Assets::saleWindowsCss() . '</style>';
echo '<div class=\"tapin-pw\"><div class=\"tapin-pw__title\">' . esc_html__('Ticket price windows', 'tapin') . '</div><div class=\"tapin-pw__grid\">';", text, count=1, flags=re.S)
text = re.sub(r"\$startStr = .*?;", "$startStr = $start ? Time::fmtLocal($start) : esc_html__('Start date not set', 'tapin');", text, count=1)
text = re.sub(r"\$endStr   = .*?;", "$endStr   = $end ? Time::fmtLocal($end) : esc_html__('Until event date', 'tapin');", text, count=1)
text = text.replace("$badge    = match ($state) {\n            'current'  => '?-???\u07?? ???\u07??T??',\n            'upcoming' => '?`?\u07??\u07??',\n            default    => '??\"?\u07??x?T?T??',\n        };", "$badge    = match ($state) {\n            'current'  => esc_html__('On sale', 'tapin'),\n            'upcoming' => esc_html__('Upcoming', 'tapin'),\n            default    => esc_html__('Ended', 'tapin'),\n        };")
text = text.replace("$priceHtml = $price > 0 ? wc_price($price) : '??"";", "$priceHtml = $price > 0 ? wc_price($price) : '&mdash;';")
text = text.replace("($lowest > 0 ? wc_price($lowest) : '??"')", "($lowest > 0 ? wc_price($lowest) : '&mdash;')")
text = text.replace("echo '<div class=\"tapin-pw-card__dates\">???x?-?T??: ' . esc_html($startStr) . '<br>?????x?T?T??: ' . esc_html($endStr) . '</div>';", "echo '<div class=\"tapin-pw-card__dates\">' . esc_html__('Starts:', 'tapin') . ' ' . esc_html($startStr) . '<br>' . esc_html__('Ends:', 'tapin') . ' ' . esc_html($endStr) . '</div>';")
text = re.sub(r"echo '</div><div class=\"tapin-pw__hint\">.*?</div></div>';", "echo '</div><div class=\"tapin-pw__hint\">' . esc_html__('Ticket prices change automatically according to the active window. Choose the ticket type and timing that works best for you.', 'tapin') . '</div></div>';", text, count=1)
path.write_text(text, encoding="utf-8")
