# beaufy-eu

Statický web pro **BEAUFY s.r.o.** — distributora zdravotnických prostředků EU původu (Laxík, Glycerol New.Fa.Dem., DISPO GEL, DISPO ICE Spray, Ženšen, Instantní termoterapie).

**Live:** [www.beaufy.eu](https://www.beaufy.eu)

---

## TL;DR — jak to funguje

1. Edituješ HTML/CSS přímo v repu (žádný build framework, žádný npm)
2. `git push` na `main`
3. GitHub Actions automaticky:
   - Inline partials přes `build-includes.py`
   - FTP sync na Wedos hosting
   - Pingne IndexNow API (Bing/Yandex/Seznam)
   - Refreshuje Facebook OG cache pro 12 URL
4. Změny live na `www.beaufy.eu` do **2–3 minut**

Žádné manuální klikání v search consolích ani sharing debuggerech.

---

## Quick start — drobná editace

```bash
# 1. naklonuj
git clone https://github.com/LiorD78/beaufy-eu.git
cd beaufy-eu

# 2. otevři jakýkoli .html soubor v editoru, uprav text
# 3. commit + push
git add .
git commit -m "fix(laxik): typo v hero textu"
git push

# 4. počkej ~2 min, pak ověř na www.beaufy.eu (Ctrl+Shift+R)
```

Nepotřebuješ Node.js, npm, ani lokální server. Pro náhled změn s inlinovanými partials:

```bash
python3 build-includes.py        # inline @include markery
python3 -m http.server 8000      # lokální preview na :8000
git checkout -- .                # vrať @include zpět (build-includes je destruktivní)
```

---

## Architecture

### Adresářová struktura

```
beaufy-eu/
├── index.html                      # homepage
├── laxik/index.html                # produktové stránky
├── glycerol/index.html
├── zensen/index.html
├── dispogel/index.html
├── dispo-ice/index.html
├── instantni-termoterapie/index.html
├── blog/
│   ├── index.html                  # blog listing
│   ├── glycerol-zacpa-u-dospelych/index.html
│   ├── mikroklystyr-lecba-zacpy/index.html
│   └── zacpa-u-deti/index.html
├── zasady-ochrany-osobnich-udaju.html  # privacy (legal-page-body)
├── assets/
│   └── css/
│       └── beaufy.css              # sdílený design system
├── _partials/                      # NEPOSÍLÁ se na FTP
│   ├── nav.html                    # sdílený navbar
│   ├── footer.html                 # sdílený footer
│   └── README.md
├── _originals/                     # NEPOSÍLÁ se — historické verze
├── docs/                           # PDF dokumenty (POS, certifikáty)
├── logos/                          # SVG/PNG loga lékáren
├── *.png, *.jpg, *.webp            # všechny obrázky v rootu
├── build-includes.py               # inline @include markerů
├── sitemap.xml
├── robots.txt
├── d2aef38ffc...txt                # IndexNow verifikační soubor
└── .github/workflows/
    └── deploy-wedos.yml            # CI/CD pipeline
```

### Build pipeline

Web je čisté statické HTML — **žádný framework, žádný build tool**, kromě jediného Python scriptu:

**`build-includes.py`** — primitivní template engine (~70 řádků). Najde `<!-- @include _partials/X.html -->` markery v HTML souborech a nahradí je obsahem partialu. Spouští se v GitHub Actions PŘED FTP uploadem.

```html
<!-- v laxik/index.html: -->
<body>
  <!-- @include _partials/nav.html -->   ← marker
  <main>...</main>
  <!-- @include _partials/footer.html -->
</body>
```

Po build-includes:

```html
<body>
  <nav id="nav">...</nav>            ← inlined
  <main>...</main>
  <footer>...</footer>
</body>
```

Markery zůstávají v repu — inlining je jen v deploy artefaktech. Když změníš `_partials/nav.html`, propíše se to do **všech 12 stránek** najednou.

### Design system

Všechny styly žijí v jediném souboru: **`assets/css/beaufy.css`** (~22 KB, ~450 řádků).

**Klíčové design tokeny** (v `:root`):

```css
:root {
  --navy: #0F172A;          /* základní tmavá */
  --blue: #0070F3;
  --blue-lt: #1f5fa6;
  --teal: #F59E0B;          /* HISTORICKY teal, dnes amber */
  --green: #F59E0B;          /* CTA amber (NE zelená!) */
  --bg: #F1F5F9;
  --white: #ffffff;
  --text: #1a2533;
  --muted: #5a6a7e;
  --h2-gradient: linear-gradient(90deg, #0b2c4a, #134a8e, #1f5fa6);
}
```

**Cache-busting:** `assets/css/beaufy.css?v=20260425c`. Při velké změně CSS bumpuj suffix (`c` → `d`) a aplikuj **napříč všemi 12 HTML soubory** (jinak browsery serírají starý CSS).

**Font:** `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif` (system fonts, žádné Google Fonts kvůli GDPR).

**Nav variants:**
- `<nav id="nav">` — default, white-on-dark, použij na stránkách s tmavým hero (laxik, glycerol, …). JS toggluje `class="scrolled"` při `scrollY > 60`.
- `<body class="legal-page-body">` — pro stránky bez tmavého hero (privacy, terms). Forcuje "scrolled" stav natvrdo přes CSS, nav vypadá jako po scrollu (navy text, glass blur, amber CTA).

### Copy pravidla

- **V češtině NIKDY znak `&`** — vždy "a". (Anglické názvy produktů jako "EASY ICE & DISPO HOT" jsou OK.)
- **Nikdy nezmiňovat "Easy Lax"** — to je konkurenční produkt.
- **IČO `06867031`** vždy zabal do `<span style="color:inherit">06867031</span>` (Safari má bug s underline detekcí).

---

## Automatizace — co se děje při deploy

`.github/workflows/deploy-wedos.yml` má 4 kroky po checkoutu:

### 1. Inline partials

```bash
python3 build-includes.py
```

Nahradí `@include` markery obsahem `_partials/*.html`.

### 2. FTP sync

`SamKirkland/FTP-Deploy-Action@v4.3.5` porovná soubory v repu vs. Wedos a uploadne jen změněné. State soubor `.ftp-deploy-sync-state.json` zrychluje další deploye.

Excludes: `.git*`, `.github/`, `_originals/`, `_partials/`, `build-includes.py`, `*.zip`, `index_backup_original.html`.

### 3. IndexNow ping

POST na `api.indexnow.org/indexnow` se seznamem 12 URL.

```json
{
  "host": "www.beaufy.eu",
  "key": "d2aef38ffc3aff99319c9ab844ebfba6",
  "keyLocation": "https://www.beaufy.eu/d2aef38ffc3aff99319c9ab844ebfba6.txt",
  "urlList": ["https://www.beaufy.eu/", ...]
}
```

API vrací `HTTP 202 Accepted`. **Bing, Yandex i Seznam** pak rychle stáhnou aktualizovaný obsah. Bez tohoto kroku Bing crawler chodí ~1× za den.

API klíč je veřejný — jeho jediný účel je dokázat, že vlastníme doménu (verifikační soubor `/{key}.txt` musí na webu být a obsahovat ten samý key).

### 4. Facebook Open Graph cache refresh

Pro každou z 12 URL volá:

```
POST https://graph.facebook.com/?id=URL&scrape=true&access_token=$FB_TOKEN
```

`$FB_TOKEN` je v GitHub Secret `FB_APP_ACCESS_TOKEN`, ve formátu `{App ID}|{App Secret}`. Token je dlouhodobý (neexpiruje, dokud appku nikdo neresetuje).

App: **BEAUFY OG Refresh** (App ID `936185702530232`), Meta Developer dashboard:
https://developers.facebook.com/apps/936185702530232/dashboard/

FB stáhne stránku znovu a aktualizuje cache (title, description, og:image). Bez tohoto kroku FB cache visí ~7 dní a sdílení staré verze ukazuje staré preview.

---

## Jak přidat novou stránku

Příklad: chci přidat blogový článek **"Záněty hltanu — kdy k lékaři"** na URL `/blog/zanety-hltanu/`.

### 1. Vytvoř HTML soubor

```bash
mkdir -p blog/zanety-hltanu
# Zkopíruj nejbližší existující článek jako template
cp blog/zacpa-u-deti/index.html blog/zanety-hltanu/index.html
```

### 2. Edit head (canonical, OG, JSON-LD)

V `blog/zanety-hltanu/index.html` přepiš:

- `<title>` → "Záněty hltanu — kdy k lékaři | Blog BEAUFY"
- `<meta name="description">` (≥100 znaků pro LinkedIn)
- `<meta property="og:title">`, `og:description`, `og:image`
- `<meta name="twitter:title">`, `twitter:description`
- `<link rel="canonical" href="https://www.beaufy.eu/blog/zanety-hltanu/">`
- `<link rel="alternate" hreflang="cs-CZ" href="...">` (a pro `cs`, `sk`, `x-default`)
- JSON-LD `Article` + `BreadcrumbList`

**Vždy zachovej `<meta property="fb:app_id" content="936185702530232">`** před `og:type`.

### 3. Edit body (obsah článku)

Použij stejnou strukturu jako existující články — `<article>`, `<h1>`, `<h2>`, `<p>`. Pro inline obrázky:

```html
<img src="/img-zanety-hltanu.jpg" alt="..." width="1200" height="630" loading="lazy">
```

(Width/height povinné kvůli CLS / Core Web Vitals.)

### 4. Crosslink z relevantních stránek

V `blog/index.html` přidej do `<section class="article-grid">` novou kartu. Případně i z related-articles sekce v relevantních produktových stránkách.

### 5. Sitemap

V `sitemap.xml`:

```xml
<url>
  <loc>https://www.beaufy.eu/blog/zanety-hltanu/</loc>
  <lastmod>2026-04-25</lastmod>
  <changefreq>monthly</changefreq>
  <priority>0.6</priority>
</url>
```

### 6. IndexNow + FB seznamy

V `.github/workflows/deploy-wedos.yml` přidej novou URL **na 2 místa**:
- `urlList` v IndexNow stepu
- `URLS` array v FB OG stepu

Bez toho nová stránka nedostane auto-refresh.

### 7. Commit + push

```bash
git add .
git commit -m "feat(blog): nový článek o zánětech hltanu"
git push
```

GitHub Actions deployne, IndexNow + FB cache se postará o discovery. Hotovo.

---

## Common tasks

### Bump cache-bust pro CSS

Po větší změně `assets/css/beaufy.css`:

```bash
# Najdi všechna místa
grep -rn "beaufy.css?v=" --include="*.html" .

# Nahraď napříč všemi soubory (Linux/macOS)
find . -name "*.html" -not -path "./_originals/*" -exec \
  sed -i 's/beaufy\.css?v=20260425c/beaufy.css?v=20260425d/g' {} \;
```

Commit + push. Browsery teď donutí fresh fetch CSS.

### Editovat nav nebo footer napříč webem

```bash
vim _partials/nav.html
git commit -am "feat(nav): nová položka v menu"
git push
```

Propíše se do všech 12 stránek automaticky (build-includes při deploy).

### Spustit deploy ručně (bez commit)

GitHub Actions tab → "Deploy to Wedos" → "Run workflow" → branch `main` → "Run workflow".

Užitečné pokud chceš jen forcnout IndexNow / FB OG refresh bez code change.

### Zkontrolovat že FB cache je fresh

https://developers.facebook.com/tools/debug/?q=https%3A%2F%2Fwww.beaufy.eu%2F

"Time Scraped" by mělo být **~minuty po posledním deploy**.

### Rotovat App Secret (FB Graph API)

1. Meta Developer → BEAUFY OG Refresh → App settings → Basic
2. App Secret → klik **"Reset"** → potvrď heslem
3. Zkopíruj nový secret
4. GitHub → Settings → Secrets → `FB_APP_ACCESS_TOKEN` → Update
5. Hodnota: `936185702530232|<NOVÝ_SECRET>`

Doporučeno udělat každý 3–6 měsíců.

---

## Závislosti / kontakty

| Co | Kde | Účet |
|---|---|---|
| Repo | [github.com/LiorD78/beaufy-eu](https://github.com/LiorD78/beaufy-eu) | LiorD78 |
| Hosting | Wedos, FTP `/www/` | Webhosting ID 299481 |
| DNS | Wedos | doména beaufy.eu |
| Bing WT | webmaster.bing.com | beaufy.eu submitted |
| Google Search Console | search.google.com/search-console | verified |
| Facebook App | App ID 936185702530232 | libor.dospel@gmail.com |
| IndexNow API | api.indexnow.org | klíč `d2aef38ffc3aff99319c9ab844ebfba6` |

### GitHub Secrets (pro CI/CD)

| Name | Účel |
|---|---|
| `WEDOS_FTP_SERVER` | FTP server pro deploy |
| `WEDOS_FTP_USERNAME` | FTP user |
| `WEDOS_FTP_PASSWORD` | FTP heslo |
| `FB_APP_ACCESS_TOKEN` | `936185702530232\|<App Secret>` pro Graph API |

Edit: https://github.com/LiorD78/beaufy-eu/settings/secrets/actions

---

## History — milestone z 25. 4. 2026

V jediném dni jsme udělali kompletní SEO/UX/A11Y/DevOps overhaul (16 commitů):

| # | SHA | Téma |
|---|---|---|
| 1 | `fcbbff74` | a11y: form labels with for/id, add Instantní termoterapie option |
| 2 | `7a52af61` | a11y: heading h4→h3 v info-cards (5 produktovek, 55 nadpisů) |
| 3 | `3156f751` | a11y: aria-hidden=true na 80 dekorativních SVG |
| 4 | `58671eda` | perf(CWV): img dimensions, lazy/eager loading, hero preload |
| 5 | `c32064c0` | linking: termo family crosssell — 2 cards |
| 6 | `19a3e80b` | linking: contextual anchor links v 3 blog článcích |
| 7 | `db1f1b1b` | linking: Related Articles section na laxik a glycerol |
| 8 | `1cc680b8` | fix: add dispo-gel.png to repo |
| 9 | `6ba0832d` | seo: extend blog description to 130 chars |
| 10 | `9e62e8e4` | feat(privacy): unified design with shared beaufy.css |
| 11 | `ae513c63` | fix(privacy): inline nav with scrolled class (hack) |
| 12 | `ad9b84c7` | feat(indexnow): add API key verification file |
| 13 | `c1f458aa` | feat(indexnow): ping Bing IndexNow API after each deploy |
| 14 | `05a9bb67` | refactor(privacy): use shared @include + body.legal-page-body CSS |
| 15 | `28b7f589` | feat(fb-og): refresh Facebook OG cache via Graph API |
| 16 | `131d9248` | feat(og): add fb:app_id meta tag to all 12 pages |

**Výsledek:**
- Plně sjednocený design (privacy stránka už není outsider)
- A11Y: form labels, heading hierarchy, aria-hidden na decorative SVG
- CWV: width/height u všech img, lazy loading, hero preload
- Internal linking: blog ↔ produkty, related articles
- 2 plně automatizované pipelines: IndexNow + FB Graph API
- 0 manual ticků v Bing WT / FB Sharing Debugger / LinkedIn Post Inspector po každém deploy

---

## Licence

Proprietary. © 2026 BEAUFY s.r.o. Veškerý kód, obsah a obrázky vlastníky autorů.
