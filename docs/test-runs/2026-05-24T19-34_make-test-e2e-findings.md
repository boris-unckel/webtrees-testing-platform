<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Befunde — `make test-e2e` 2026-05-24T19-34

**Startuhrzeit:** 2026-05-24T19:34:05+02:00
**Endzeit:** 2026-05-24T19:49:03+02:00
**Laufzeit gesamt:** ≈ 14 min 58 s (Playwright-eigene Test-Zeit: 14,0 min)
**Vorbedingung:** Stack frisch nach `make clean && make up && make setup` (Stand 19:30, vom User
außerhalb dieser Session ausgeführt). Vor diesem Lauf **kein** L3-Lauf, Fixtures
unangetastet.
**`TEST_RUN_ID`:** `d634ee7b-a1e1-4238-85a9-bfea7cf57b93`
**Exit-Code:** 0

Snapshot der Roh-Artefakte: [2026-05-24T19-34_run/](2026-05-24T19-34_run/).

## Quick-Bilanz

| Stufe | Tool                                       | Tests | Passed | Flaky | Failed | Skipped | Suite-Laufzeit | Exit |
|-------|--------------------------------------------|------:|-------:|------:|-------:|--------:|----------------|-----:|
| 4     | Playwright (Chromium headless, 1 worker, retry=1) | 533 |    532 |     1 |      0 |       0 | 14,0 min       | 0    |

Auf frisch aufgesetztem Stack (`make clean && make up && make setup`, ohne
vorangehenden L3-Lauf) ist L4 sauber grün — bis auf einen einzigen flakigen
Test (siehe unten).

## Einziger Befund: 1 flaky in `contact-form.spec.ts` (Theme `fab`)

**Test:** `K01 — Kontaktformular zeigt Pflichtfelder [fab]`
**Datei:** `tests/contact-form.spec.ts:27:9`
**Status:** flaky — schlug im 1. Lauf fehl, bestand im 2. Anlauf (retry=1)

### Fehlerbild im 1. Lauf

```
Error: expect(received).toBeGreaterThan(expected)
  Expected: > 0
  Received:   0
  at tests/contact-form.spec.ts:35
```

Code-Stelle:

```ts
test(`K01 — Kontaktformular zeigt Pflichtfelder [${theme}]`, async ({ page }) => {
  await page.goto('/tree/demo/contact?to=admin');
  const form = page.locator('form');
  await expect(form.first()).toBeVisible();
  const fields = page.locator(
    'input[name="subject"], textarea[name="body"], input[name="from_name"], input[name="from_email"]'
  );
  const count = await fields.count();
  expect(count).toBeGreaterThan(0);   // <-- 0 statt > 0
});
```

`form` war sichtbar (Assertion davor passt), aber die Felder hatten den Count
0. Im Retry zählte derselbe Locator > 0. Klassisches Race zwischen
`form.first().toBeVisible()` (passt sobald das `<form>` im DOM ist) und der
nachfolgenden `fields.count()` (zählt sofort, ohne auf Inputs zu warten).

### Hypothese (nicht verifiziert)

Im **`fab`-Theme** wird die `<form>` evtl. mit verzögerter Hydration / nach
einem zusätzlichen XHR gerendert. Die anderen vier Themen (`webtrees`,
`xenea`, `colors`, `clouds`) waren in beiden Läufen grün.

### Was nicht zu fixen ist (Direktive)

Per User-Vorgabe „Findings erstmal nur prüfen, noch nicht fixen". Notiere für
spätere Iteration:

- Test-seitiger Fix wäre `await expect(fields.first()).toBeVisible();` **vor**
  `count()` — wartet bis mindestens 1 Feld da ist.
- Alternativ `await page.waitForLoadState('networkidle')` nach `goto`.
- Vorher klären, ob das **Theme `fab`** tatsächlich später hydriert oder ob
  hier ein echter Render-Bug im Kontaktformular sichtbar wird.

### Artefakte aus dem 1. Anlauf

- `artifacts/layer4/test-results/contact-form-Theme-fab-K01-849ca-ar-zeigt-Pflichtfelder-fab--chromium/test-failed-1.png` (Screenshot beim Fail)
- `artifacts/layer4/test-results/contact-form-Theme-fab-K01-849ca-ar-zeigt-Pflichtfelder-fab--chromium/error-context.md` (Playwright-Diagnostik)
- `artifacts/layer4/test-results/contact-form-Theme-fab-K01-849ca-ar-zeigt-Pflichtfelder-fab--chromium-retry1/trace.zip` (Retry-Trace)

Alle drei sind im Snapshot unter
[`2026-05-24T19-34_run/layer4/test-results/`](2026-05-24T19-34_run/layer4/test-results/)
mitgesichert.

## PerfSchema-Beobachtungen

Auszug aus `artifacts/layer4/perfschema/summary.txt`:

- **Top-Tabellen nach I/O-Wait:** `wt_dates` (3 622 reads, 1,86 ms) ·
  `wt_module` (1 278 reads, 0,55 ms) · `wt_site_setting` (48 reads) ·
  `wt_gedcom` (20 reads) · `wt_user_setting` (62 reads).
- **0 Full Table Scans, 0 No-Index Queries, 0 Disk-Temp-Tabellen** — sauber.
- Top-Latenz-Queries auf MySQL-Setup-Statements (`SET NAMES`, `START
  TRANSACTION`, `USE`, `COMMIT`) — keine echten Anwendungs-Queries auffällig.

Volle Detailblöcke pro Test unter `artifacts/layer4/perfschema/per-test/`
(525 Dateien, 6,3 MB) — nicht in den Snapshot übernommen, da sehr granular
und vom Folgelauf überschreibbar.

## Trace-Report

- **JSON:** `artifacts/layer4/trace-report.json` (419 MB) — wegen Größe **nicht**
  im Snapshot. Nach `make clean` weg; bei Bedarf vorher manuell sichern.
- **TXT-Variante:** seit 2026-04-13 im `run.sh` temporär deaktiviert (Kommentar
  im Log: `# --output-text artifacts/layer4/trace-report.txt — temporär
  deaktiviert (2026-04-13)`).

## Nicht in diesem Findings-Doc

- **Strukturanalyse der „L3 zerstört L4-Fixtures"-Ursache** — gehört in eine
  eigene Diagnose-Session. Memory-Eintrag dazu: noch zu erstellen.
- **Andere Layer** — dieser Lauf war reines L4 (`make test-e2e`), kein L3/L5.

## Reproduktion

```
make clean && make up && make setup    # zwingend frische Fixtures
date -Iseconds                          # Startuhrzeit dokumentieren
make test-e2e                           # ≈ 15 min auf dieser Maschine
```

Erwartet: 532 passed / 1 flaky (`K01 fab`) / 0 failed.
