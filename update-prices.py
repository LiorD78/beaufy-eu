#!/usr/bin/env python3
"""
update-prices.py — propíše doporučené ceny z ceníku v Atlasu do stránek webu.

Cena se na stránce značí takto (číslo je APA produktu):
  Doporučená cena <span data-atlas-apa="5080057">89</span> Kč

Skript přepíše všechny takové značky a ve stejné stránce i lowPrice/highPrice
ve strukturovaných datech. Spouští ho .github/workflows/update-prices.yml.
Když něco nesedí (produkt v Atlasu chybí, podezřelá cena), skončí chybou a
nezmění nic; GitHub pak pošle e-mail o selhání.

  python3 update-prices.py              načte ceny z Atlasu
  python3 update-prices.py ceny.json    načte ceny ze souboru (test)
"""
import datetime
import json
import os
import re
import sys
import urllib.request

ROOT = os.path.dirname(os.path.abspath(__file__))
URL = 'https://vcjbhkxuctbwmtdphprm.supabase.co/functions/v1/beaufy-web-prices'
SKIP = {'.git', '.github', 'node_modules', '_originals', '_partials'}
MARK = re.compile(r'(<span data-atlas-apa="(\d+)">)(\d+)(</span>)')
MAX_CHANGE = 0.5  # větší skok než ±50 % se nepropíše bez kontroly člověkem


def load_prices():
    if len(sys.argv) > 1:
        raw = open(sys.argv[1], encoding='utf-8').read()
    else:
        req = urllib.request.Request(URL, headers={'User-Agent': 'beaufy-web-prices'})
        with urllib.request.urlopen(req, timeout=30) as r:
            raw = r.read().decode('utf-8')
    prices = {}
    for row in json.loads(raw)['prices']:
        p = float(row['dmc_czk'])
        if p != int(p) or not 0 < p < 10000:
            sys.exit(f"Podezřelá cena v Atlasu: APA {row['apa']} = {row['dmc_czk']}")
        prices[str(row['apa'])] = int(p)
    if not prices:
        sys.exit('Atlas nevrátil žádné ceny')
    return prices


def pages():
    for d, dirs, files in os.walk(ROOT):
        dirs[:] = [x for x in dirs if x not in SKIP and not x.startswith('.')]
        for f in files:
            if f.endswith('.html'):
                yield os.path.join(d, f)


def main():
    prices = load_prices()
    new_pages, changes = {}, []
    for path in pages():
        s = open(path, encoding='utf-8', newline='').read()
        found = MARK.findall(s)
        if not found:
            continue
        rel = os.path.relpath(path, ROOT)

        def swap(m):
            apa, old = m.group(2), int(m.group(3))
            if apa not in prices:
                sys.exit(f'{rel}: APA {apa} není v ceníku Atlasu')
            new = prices[apa]
            if abs(new - old) / old > MAX_CHANGE:
                sys.exit(f'{rel}: APA {apa} skok ceny {old} → {new} Kč, zkontrolujte ceník')
            if new != old:
                changes.append(f'{rel}: APA {apa} {old} → {new} Kč')
            return f'{m.group(1)}{new}{m.group(4)}'

        out = MARK.sub(swap, s)
        vals = [prices[f[1]] for f in found]
        out = re.sub(r'("lowPrice":\s*")\d+(")', rf'\g<1>{min(vals)}\g<2>', out)
        out = re.sub(r'("highPrice":\s*")\d+(")', rf'\g<1>{max(vals)}\g<2>', out)
        if out != s:
            new_pages[path] = out

    for path, out in new_pages.items():
        open(path, 'w', encoding='utf-8', newline='').write(out)

    if new_pages:
        sm = os.path.join(ROOT, 'sitemap.xml')
        x = open(sm, encoding='utf-8', newline='').read()
        today = datetime.date.today().isoformat()
        for path in new_pages:
            u = os.path.relpath(os.path.dirname(path), ROOT)
            x = re.sub(r'(<loc>https://www\.beaufy\.eu/' + re.escape(u) + r'/</loc>\s*<lastmod>)[^<]+',
                       rf'\g<1>{today}', x)
        open(sm, 'w', encoding='utf-8', newline='').write(x)
    print('\n'.join(changes) if changes or new_pages else 'Ceny souhlasí, beze změny.')


if __name__ == '__main__':
    main()
