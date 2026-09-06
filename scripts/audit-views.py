#!/usr/bin/env python3
"""Inventory Blade UI primitives. Findings require semantic review, not blind replacement."""
import csv
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
output = root / 'audits/view-inventory.csv'
with output.open('w', newline='') as target:
    writer = csv.writer(target)
    writer.writerow(['view', 'context', 'shared_components', 'raw_checkboxes', 'raw_tables', 'raw_buttons', 'raw_visible_inputs', 'raw_selects', 'raw_textareas', 'pill_candidates', 'fixed_column_candidates'])
    for path in sorted((root / 'resources/views').rglob('*.blade.php')):
        source = path.read_text()
        relative = path.relative_to(root / 'resources/views')
        context = 'browser'
        if relative.parts[0] in ('emails', 'pdf', 'vendor', 'components'):
            context = relative.parts[0]
        writer.writerow([
            str(relative), context, len(re.findall(r'<x-[\w.-]+', source)),
            len(re.findall(r'<input\b[^>]*type=[\"\']checkbox[\"\']', source)),
            len(re.findall(r'<table\b', source)), len(re.findall(r'<button\b', source)),
            len(re.findall(r'<input\b(?![^>]*type=[\"\'](?:hidden|checkbox)[\"\'])', source)),
            len(re.findall(r'<select\b', source)), len(re.findall(r'<textarea\b', source)),
            len(re.findall(r'<(?:span|div|a)\b[^>]*rounded-full', source)),
            len(re.findall(r'(?<![:\w-])grid-cols-[2-9]\b', source)),
        ])
print(output.relative_to(root))
