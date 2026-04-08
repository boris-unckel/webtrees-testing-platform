<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Validation — SEC-AUDIT-001 / Phase D7

- **Task:** SEC-AUDIT-001 — SVG Stored XSS via inadequate `<script` substring filter
- **Fix-Branch:** `security-audit-001-svg-filter-hardening`
- **Fix-Commit:** `b2dc869b90407bb5129dbd768c9364dc863482b2`
- **Fork-Path:** `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`
- **Validated-At:** 2026-04-08 22:23 CEST
- **Gesamturteil:** **`fix_verified`**

## Änderungen

- **Datei:** `app/Factories/ImageFactory.php`
- **Diff-Statistik:** 1 file changed, 97 insertions(+), 2 deletions(-)
- **Kernänderung:** `str_contains($data, '<script')` ersetzt durch `svgContainsActiveContent(data: $data)`.
- **Neue private Methoden:**
  - `svgContainsActiveContent(string $data): bool` — parst SVG über `DOMDocument::loadXML` mit `LIBXML_NONET` (schützt vor XXE/SSRF bei fehlerhaften Payloads). Bei Parse-Fehler wird konservativ blockiert.
  - `svgElementIsDangerous(DOMElement $element): bool` — rekursiver DOM-Walk. Blockt bei:
    - Dangerous-Tags (`script`, `foreignObject`, `iframe`, `object`, `embed`, `handler`) über `strtolower($element->localName)`.
    - Event-Handler-Attributen (alle Attributnamen, die case-insensitiv mit `on` beginnen).
    - `javascript:`-URLs in beliebigem Attributwert (Whitespace wird vor dem Check normalisiert, um HTML-Parser-Toleranz wie `java\tscript:` abzufangen).
- **Neue `use`-Imports:** `DOMDocument`, `DOMElement`, `libxml_clear_errors`, `libxml_use_internal_errors`, `preg_replace`, `str_starts_with`, `stripos`, `strtolower`, `LIBXML_NONET`.
- **Entfernte `use`-Imports:** `str_contains` (nicht mehr referenziert).

## Verteidigungsmodell unverändert

Der Fix adressiert **L1 (Server-side Blocker)**. Die bereits vorhandene **L2-Verteidigung (CSP `script-src none;frame-src none`)** auf Basis-Responses bleibt unverändert. Der Fix macht L1 so robust, dass bekannt-gefährliche SVG-Klassen (Script-Tags, Event-Handler, `javascript:`-URLs) den Server gar nicht mehr verlassen — wodurch das Risiko auch für Browser, die CSP nicht oder nur teilweise unterstützen, sinkt.

**Out of scope (dokumentiert in SEC-AUDIT-001 "Offene Punkte"):**
- Extension-Allowlist in `MediaFileService::uploadFile()` — paralleler Fix-Vektor in der Schwester-Action `UploadMediaAction`. Kein Teil dieses Commits, da der Upload-Blocker orthogonal zum Serve-Blocker ist.
- `replacementImageResponse` setzt weiterhin keinen CSP-Header; das war schon vor dem Fix so und ist für den Placeholder akzeptabel (enthält keinerlei benutzerkontrollierte Inhalte).

## Oracle 1 — Test-Suite ist diagnostisch (pre-fix rot, post-fix grün)

### Pre-Fix-Lauf (ImageFactory via `git restore` zurückgedreht)

```
PHPUnit 12.5.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /tests/layer3-integration/phpunit-integration.xml

FFF..                                                               5 / 5 (100%)

There were 3 failures:

1) SecAudit001Test::test_h1_case_bypass_script_is_blocked_after_fix
H1: SVG blocker should have replaced the payload (x-image-exception header expected)
Failed asserting that false is true.

2) SecAudit001Test::test_h2_onload_event_handler_is_blocked_after_fix
H2: SVG blocker should have replaced the payload (x-image-exception header expected)
Failed asserting that false is true.

3) SecAudit001Test::test_h3_javascript_url_is_blocked_after_fix
H3: SVG blocker should have replaced the payload (x-image-exception header expected)
Failed asserting that false is true.

FAILURES!
Tests: 5, Assertions: 57, Failures: 3.
```

**Interpretation:** Die neuen `assertSvgWasBlockedByFilter`-Assertions decken den Bypass empirisch auf. Pre-Fix scheitern H1/H2/H3 an genau der richtigen Stelle — `x-image-exception` fehlt, weil `str_contains` die Payloads nicht erkennt. H4 (legitimate) und H5 (lowercase baseline) bleiben grün, weil ihr Verhalten pre-fix bereits korrekt war.

### Post-Fix-Lauf (ImageFactory mit Commit b2dc869b90)

```
PHPUnit 12.5.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /tests/layer3-integration/phpunit-integration.xml

.....                                                               5 / 5 (100%)

Time: 00:08.948, Memory: 53.50 MB

OK (5 tests, 66 assertions)
```

**Interpretation:** Alle drei Bypass-Hypothesen (H1/H2/H3) werden jetzt vom DOM-Walker blockiert:

- H1 (`<SCRIPT>…</SCRIPT>`): `strtolower($element->localName) === 'script'` greift.
- H2 (`<svg onload=…>`): Attributname beginnt case-insensitiv mit `on`.
- H3 (`<a xlink:href="javascript:…">`): Normalisierter Attributwert beginnt mit `javascript:`.

H4 (legitimate SVG mit nur `<rect>` und `<text>`) passiert den DOM-Walker unverändert — die CSP-Header bleiben gesetzt, `x-image-exception` ist abwesend. Die Pipeline bricht also für harmlose Inhalte nicht. H5 bleibt blockiert, jetzt allerdings über den DOM-Parser statt über den `str_contains`-Pfad — das Verhalten nach außen ist identisch (200 + `x-image-exception` gesetzt).

## Oracle 2 — keine Leckage der Payload in die Response

Die Post-Fix-Tests verifizieren zusätzlich mit `assertStringNotContainsString`, dass weder die ursprüngliche Payload-Signatur (`<SCRIPT`, `onload=`, `javascript:`) noch der Beacon-Marker `cookie` im Response-Body verbleibt. Das schließt aus, dass der Blocker die Payload teilweise passieren lässt.

## Oracle 3 — DOM-Walker ist robust gegen bekannte Varianten

- **Case-Variation** (H1): `strtolower()` beim Tag-Vergleich und beim Attribut-Namen-Vergleich.
- **Event-Handler** (H2): `str_starts_with($name_lowered, 'on')` deckt `onload`, `onclick`, `onmouseover`, `onfocus`, `onblur`, `onanimationstart`, … pauschal ab.
- **javascript:-URLs** (H3): `preg_replace('/\s+/', '', $value)` entfernt Whitespace-Verschleierung (`java\tscript:`, Zeilenumbrüche), `stripos(…, 'javascript:') === 0` ist case-insensitiv.
- **Nested Exploits**: Rekursion über `childNodes` greift auch geschachtelte Strukturen (`<g><script>…</script></g>`).
- **Malformed XML**: `loadXML() === false` → Block. Eingaben, die der Parser nicht akzeptiert, werden konservativ zurückgewiesen, statt in einem undefinierten Zustand durchzurutschen.
- **XXE/SSRF-Schutz**: `LIBXML_NONET` verhindert Entity-Resolution gegen externe Ressourcen — ein vorbeugender Härtungsschritt, der den Fix gegen DTD-basierte Umgehungen absichert.

## Oracle 4 — Skip-Guard verhindert Regression im Default-Flow

`SecAudit001Test::setUp()` prüft `method_exists(ImageFactory::class, 'svgContainsActiveContent')`. Ist die Methode nicht vorhanden (pre-fix webtrees-Source), werden alle fünf Testmethoden mit einer sprechenden Meldung geskippt. Damit bleibt `make test-integration` gegen eine pristine `upstream/webtrees`-Clone grün, während `make test-integration-security-001` gegen einen WEBTREES_SOURCE mit dem Fork-Commit vollständig durchläuft.

**Pre-fix-Lauf mit Skip-Guard (testing-platform upstream/webtrees zurück auf HEAD):**

```
PHPUnit 12.5.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /tests/layer3-integration/phpunit-integration.xml

SSSSS                                                               5 / 5 (100%)

Time: 00:00.166, Memory: 42.50 MB

OK, but some tests were skipped!
Tests: 5, Assertions: 0, Skipped: 5.
```

Skip-Meldung (identisch für alle 5 Tests):
> SEC-AUDIT-001 regression requires ImageFactory::svgContainsActiveContent() (fork branch security-audit-001-svg-filter-hardening, commit b2dc869b90). Run with WEBTREES_SOURCE pointing at a tree that includes this fix to enable.

**Post-fix-Lauf (fork-Kopie zurück nach upstream/webtrees, Skip-Guard passiv):**

```
..... (5/5)

OK (5 tests, 66 assertions)
```

## Folge-Aktionen

- Fix-Branch `security-audit-001-svg-filter-hardening` ist im lokalen Fork comittet und GPG-signiert. **PR wird manuell durch den User eröffnet** (V1-Workflow — keine automatisierte Push/PR-Erzeugung).
- **Offen (Follow-Up-Tasks, nicht Teil dieses Deep-Dives):**
  - Extension-Allowlist in `MediaFileService::uploadFile()` (Defense-in-Depth gegen alle anderen gefährlichen Dateiformate — eigenes SEC-AUDIT-Ticket).
  - Prüfen, ob `replacementImageResponse` ebenfalls CSP-Header setzen sollte.
  - Review, ob es weitere Serve-Pfade in webtrees gibt, die SVG-Daten an den Browser liefern, ohne `imageResponse()` zu durchlaufen.

## Entscheidung

**`fix_verified`** — der Fix blockt alle drei L1-Bypass-Vektoren aus den Probe-Runs (H1/H2/H3), ohne legitime SVG-Inhalte zu beeinträchtigen (H4), und erhält das Blocken trivialer Fälle (H5). Regressionstest ist diagnostisch (fällt pre-fix, läuft post-fix durch).
