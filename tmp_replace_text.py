from pathlib import Path
path = Path(r"plugins/tapin-next/src/UI/Components/TicketTypesEditor.php")
text = path.read_text(encoding="utf-8")
text = text.replace("?>?"?~?T?? ?"?'?T??", "General Admission")
text = text.replace("???"?'?T ?>?"?~?T???T?? ?\u07??????T", "Ticket types and availability")
path.write_text(text, encoding="utf-8")
