from pathlib import Path
text_path = Path(r"plugins/tapin-next/src/Features/ProductPage/PurchaseDetailsModal.php")
text = text_path.read_text(encoding="utf-8")
pattern = "        $sanitized = []\n        $errors    = [];\n"
injection = "        $cache = $this->ensureTicketTypeCache($productId);\n        $this->currentTicketTypeIndex = $cache['index'];\n\n        $sanitized = []\n        $errors    = [];\n"
if pattern not in text:
    raise SystemExit('sanitized pattern not found')
text = text.replace(pattern, injection, 1)
block_pattern = "        if ($sanitized === []) {\n            wc_add_notice('?T?c ???????? ???x ???\"?~?T ?\"???c?x?x???T?? ???????T ?\"?"?>?T?c".', 'error');\n            return false;\n        }\n\n"
block_injection = "        if ($sanitized === []) {\n            wc_add_notice('?T?c ???????? ???x ???\"?~?T ?\"???c?x?x???T?? ???????T ?\"?"?>?T?c".', 'error');\n            return false;\n        }\n\n        $typeCounts = [];\n        foreach ($sanitized as $entry) {\n            $typeId = isset($entry['ticket_type']) ? (string) $entry['ticket_type'] : '';\n            if ($typeId === '' || !isset($this->currentTicketTypeIndex[$typeId])) {\n                wc_add_notice('Selected ticket type is not available.', 'error');\n                return false;\n            }\n            $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;\n        }\n\n        foreach ($typeCounts as $typeId => $count) {\n            $context   = $this->currentTicketTypeIndex[$typeId];\n            $capacity  = isset($context['capacity']) ? (int) $context['capacity'] : 0;\n            $available = isset($context['available']) ? (int) $context['available'] : 0;\n            if ($capacity > 0 && $available >= 0 && $count > $available) {\n                $name = (string) ($context['name'] ?? $typeId);\n                wc_add_notice(sprintf('Not enough availability for %s.', $name), 'error');\n                return false;\n            }\n        }\n\n"
if block_pattern not in text:
    raise SystemExit('post-sanitized block not found')
text = text.replace(block_pattern, block_injection, 1)
text_path.write_text(text, encoding="utf-8")
