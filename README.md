# BLADE — Incident Dashboard (Demo)

A small security-operations dashboard that lists detection incidents and lets an
analyst filter them by severity. It is a teaching/demo project: no framework, no
build step, no package manager. Clone it, run one command, and it works.

The UI is in Serbian; the code and this document are in English.

## What it does

The backend exposes a single read-only JSON endpoint. The frontend fetches it and
renders a table of incidents — timestamp (UTC), severity, host, process, title and
status. Changing the severity dropdown re-queries the server rather than filtering
in the browser, so the row count always reflects the full dataset, not just the
page being shown.

## Stack

| Layer | Choice | Why |
|---|---|---|
| Frontend | Vanilla JS (ES2020), no build | Nothing to install; open the file and read it |
| Styling | Plain CSS with custom properties | Light/dark themes and the mobile layout live in two media queries |
| Backend | PHP 8 + PDO | Runs on the built-in dev server, no web server config |
| Database | SQLite | A file, not a service — the project is portable as a folder |

SQLite is a deliberate demo choice. In a real deployment this would be MySQL for
application data, with raw log volume kept elsewhere.

## Requirements

PHP 8.0 or newer with `pdo_sqlite` (bundled by default). Nothing else.

```bash
php -v && php -m | grep pdo_sqlite
```

## Running it

From the project directory:

```bash
php -S localhost:8000
```

Open <http://localhost:8000/>. Stop the server with `Ctrl+C`.

There is no separate database process to start. On the first request the app
notices `blade.sqlite` is missing, creates the schema and seeds it. Subsequent
runs reuse the existing file.

## Project layout

```
blade/
├── index.html          Markup: header, severity filter, table shell
├── style.css           Custom properties, dark mode, mobile card layout
├── app.js              Fetch, state, DOM rendering, abortable requests
├── db.php              Connection, schema, seed data, shared helpers
├── api/
│   └── incidents.php   GET endpoint: filter, paginate, count
└── blade.sqlite        Generated on first run — not source, safe to delete
```

## API

### `GET /api/incidents.php`

| Parameter | Type | Default | Notes |
|---|---|---|---|
| `severity` | string | — | One value or a comma-separated list. Unknown values are dropped, not rejected. |
| `limit` | int | 25 | Clamped to 1–100. |

Response:

```json
{
  "data":  [ { "id": 999, "created_at": "...", "severity": "critical", "...": "..." } ],
  "total": 241,
  "as_of": "2026-09-22T15:27:08+00:00"
}
```

`total` is the number of rows matching the filter, ignoring `limit` — that is what
lets the UI say "showing 25 of 241".

Severity values are filtered against an allowlist before they reach SQL, and the
remaining values are bound as parameters. A request like
`?severity=' OR 1=1--` returns the unfiltered set rather than an error, because
nothing in it survives the allowlist.

## Data model

`incidents` is the only table the API reads. The others are seeded to sketch the
shape of the wider product and have no endpoints yet:

- **`users`** — analysts and their roles (`viewer`, `analyst_l1`, `analyst_l2`, `admin`)
- **`agents`** — endpoint agents, their OS, version and online status
- **`actions`** — response commands issued against a host
- **`log_flow`** — per-incident timing through the processing pipeline, from the
  agent through collectors and denoising to alert generation

Seeding is deterministic (`mt_srand(42)`), so every fresh database contains the
same 241 incidents. Useful when a bug report needs to point at a specific row.

## Note on rendering untrusted values

Row 999 is seeded on purpose with HTML in two fields:

```
process_name: <img src=x onerror="window.__xss=1">
title:        Proces sa neuobicajenim imenom <script>alert(1)</script>
```

An attacker already on an endpoint controls what their process is called, and that
name travels through the whole pipeline onto an analyst's screen. The renderer in
`app.js` therefore assigns every cell with `textContent` and never `innerHTML`, so
the value is displayed as characters instead of parsed as markup.

If you change the rendering code, load the page and confirm that row still shows
the literal text — and that `window.__xss` is `undefined`.

## Working on it

The dev server reads source files on every request, so edits to PHP, JS, CSS or
HTML need only a browser refresh (use a hard reload if a stale script is cached).

The one exception is the schema. `napraviSemu()` and `napuniPodacima()` run only
when `blade.sqlite` does not exist, so changing a `CREATE TABLE` appears to do
nothing until the file is removed:

```bash
rm -f blade.sqlite
```

This works because the data here is generated. Once a database holds anything
worth keeping, replace the delete-and-recreate habit with versioned migration
files applied in order.

## Accessibility

The result counter is an `aria-live` region and the error box is `role="alert"`,
so filter results and failures are announced rather than silently swapped in.
Below 760px the table becomes a card layout while the header cells stay in the
accessibility tree. A `prefers-reduced-motion` query drops transitions, and the
palette is defined once in `:root` and redefined for `prefers-color-scheme: dark`.
