# Build-time partials

This directory holds shared HTML fragments that are inlined into every page
**at deploy time** by `build-includes.py` (run automatically by the
GitHub Actions workflow before FTP upload to Wedos).

## How it works

Pages reference a partial via a marker comment:

```html
<!-- @include _partials/nav.html -->
<!-- @include _partials/footer.html -->
```

When the deploy workflow runs, `build-includes.py` walks every `.html` file
(except those under `_originals/`) and replaces each marker with the file
contents. The processed HTML is what gets uploaded to Wedos.

The committed `.html` files in the repo keep the marker — the inlining
is non-destructive. To preview locally with the partials inlined, run
`python3 build-includes.py` once.

## Editing nav or footer

Edit the partial. That's it. Every page picks it up on next deploy.

## Adding a new partial

1. Drop a file into `_partials/`
2. Reference it with `<!-- @include _partials/yourfile.html -->` in any page
3. Commit and push — GitHub Actions handles the rest

## Partials excluded from FTP upload

The `_partials/` directory is in the workflow's `exclude` list so the raw
markers and partial files never reach the production server.
