#!/usr/bin/env python3
"""
build-includes.py — inlines _partials/*.html into every HTML page at deploy time.

Usage:
  python3 build-includes.py [--dry-run]

Markers in HTML files look like:
  <!-- @include _partials/nav.html -->

The marker line is replaced with the file contents (verbatim). This script is
idempotent: running it twice on already-inlined HTML does nothing because the
markers are gone after the first run.

For deployment, this is run inside .github/workflows/deploy-wedos.yml BEFORE
the FTP-Deploy step, so the artifacts that ship are fully inlined HTML.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.abspath(__file__))
INCLUDE_RE = re.compile(r'<!--\s*@include\s+([^\s>]+)\s*-->')

EXCLUDE_DIRS = {'.git', '.github', 'node_modules', '_originals', '_partials'}

def find_html_files():
    for dirpath, dirnames, filenames in os.walk(ROOT):
        # prune excluded dirs in-place
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIRS and not d.startswith('.')]
        for fn in filenames:
            if fn.endswith('.html'):
                yield os.path.join(dirpath, fn)

def inline_includes(html, page_path):
    """Replace @include markers with partial file contents."""
    def replace(match):
        rel = match.group(1)
        partial_path = os.path.join(ROOT, rel)
        if not os.path.exists(partial_path):
            print(f"  ⚠  {page_path}: partial not found: {rel}", file=sys.stderr)
            return match.group(0)  # leave marker intact, don't break
        with open(partial_path, 'r', encoding='utf-8') as f:
            return f.read().rstrip('\n')
    return INCLUDE_RE.sub(replace, html)

def main():
    dry_run = '--dry-run' in sys.argv
    changed = 0
    scanned = 0
    for path in find_html_files():
        scanned += 1
        with open(path, 'r', encoding='utf-8') as f:
            html = f.read()
        if not INCLUDE_RE.search(html):
            continue
        new_html = inline_includes(html, path)
        if new_html != html:
            changed += 1
            rel_path = os.path.relpath(path, ROOT)
            print(f"  ✓ {rel_path}")
            if not dry_run:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new_html)
    print(f"\n{scanned} HTML files scanned, {changed} inlined" + (' (dry run, nothing written)' if dry_run else ''))

if __name__ == '__main__':
    main()
