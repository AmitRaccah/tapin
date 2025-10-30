from pathlib import Path
path = Path(r"plugins/tapin-next/src/Domain/TicketTypesRepository.php")
text = path.read_text(encoding="utf-8")
if "General Admission" not in text:
    text = text.replace("'name'        => '?>?'\"?~?T?? ?"?'?T??',", "'name'        => 'General Admission',", 1)
path.write_text(text, encoding="utf-8")
