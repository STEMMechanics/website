#!/usr/bin/env python3
"""Report script sources and inline script/handler locations without script contents."""
from pathlib import Path
import re
for root in ['resources/views', 'resources/js']:
    for path in sorted(Path(root).rglob('*')):
        if not path.is_file():
            continue
        for number, line in enumerate(path.read_text(errors='replace').splitlines(), 1):
            kinds = []
            if re.search(r'<script\b', line): kinds.append('script element')
            if re.search(r'\bon[a-z]+\s*=|x-on:|@click', line): kinds.append('inline handler')
            if re.search(r'\beval\(|new Function\(', line): kinds.append('dynamic evaluation')
            if kinds: print(f'{path}:{number}: {", ".join(kinds)}')

print('\nExternal origins referenced by templates:')
origins = set()
for path in Path('resources/views').rglob('*.blade.php'):
    for origin in re.findall(r'(?:src|href)=[\"\'](https?://[^/\"\'\s]+)', path.read_text()):
        origins.add(origin)
print('\n'.join(sorted(origins)))
