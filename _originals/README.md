# Originály obrázků — NEDEPLOY

Tato složka obsahuje **původní vysokorozlišné zdrojové soubory**, ze kterých
jsou generovány optimalizované verze používané na webu.

## Pravidla

- **Tato složka se NEDEPLOYUJE na produkci** (vyloučena ve workflow).
- Soubory jsou pouze v Git repu jako záloha pro re-optimalizace.
- Když se obrázek na webu znovu kompresuje (jiná kvalita, jiné rozměry),
  vždy vycházet z těchto originálů, ne z webových (zmenšených) verzí.

## Struktura

Stejná jako web, jen v `_originals/`:

```
_originals/
└── story-bg.jpg            # 5504×3072, 7.0 MB — zdroj pro:
                            #   /story-bg.webp (1920×1072, 149 kB)
                            #   /story-bg.jpg  (1920×1072, 143 kB)
                            #   /story-mobile-inline.webp (1200×670, 42 kB)
                            #   /story-mobile-inline.jpg  (1200×670, 63 kB)
```

## Workflow při re-optimalizaci

1. Zdroj: `_originals/<file>.jpg`
2. Vygenerovat WebP + JPG fallback v cílovém rozlišení a kvalitě
3. Uložit do rootu webu (přepíše předchozí web verzi)
4. Originál v `_originals/` zůstává nedotčený

## Velikost repa

Originály zvětšují Git repo. Je to záměr — server tyto soubory nikdy nestahuje
(GitHub Actions sice repo cloneuje, ale workflow je `exclude: _originals/**`).
