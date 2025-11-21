# -*- coding: utf-8 -*-
from pathlib import Path
path = Path("plugins/tapin-next/src/Features/Orders/Email/Email_ProducerAwaitingApproval.php")
text = path.read_text(encoding="utf-8")
needle = """            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'producer_id'   => $this->producerId,
            ],"""
if needle not in text:
    raise SystemExit('needle not found')
replacement = """            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'producer_id'   => $this->producerId,
                'event_context' => $this->object instanceof \WC_Order
                    ? EmailEventContext::fromOrder($this->object)
                    : [],
            ],"""
text = text.replace(needle, replacement, 1)
needle_plain = needle
text = text.replace(needle_plain, replacement, 1)
path.write_text(text, encoding="utf-8")
