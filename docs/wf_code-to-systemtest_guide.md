<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Workflow: Code-Analyse → Systemtest-Konzept → Implementierung

Dieser Leitfaden beschreibt, wie Systemtests (Teststufe 3 / Layer 4, Playwright) durch
systematische Analyse der Upstream-Anwendung, vorhandener Komponentenintegrationstests
und bestehender Systemtests entworfen und umgesetzt werden.

Für Methodik (EP/BVA, Patterns), den 5-Phasen-Arbeitsablauf und
die Abschlussschritte: → [Gemeinsamer Workflow](wf_test-iteration_guide.md)

---

## 1 Übersicht

### Abgrenzung zum Komponentenintegrationstest-Guide

| Aspekt | [Code → Test](wf_code-to-test_guide.md) (L3) | Dieser Guide (L4) |
|---|---|---|
| ISTQB-Teststufe | 2 — Komponentenintegrationstest | 3 — Systemtest |
| Technologie | PHPUnit + MySQL (PHP) | Playwright + Chromium (TypeScript) |
| Testzugang | White-Box: DI, Services, DB-Assertions | Black-Box: HTTP/Browser, DOM-Selektoren |
| Testinfrastruktur | `MysqlTestCase`, DB-Fixtures | Playwright-Fixture, Helper (auth, themes, roles) |
| Verzeichnis | `layer3-integration/tests/` | `layer4-e2e/tests/` |
| Ausführung | `make test-integration` | `make test-e2e` / `make test-e2e-quick` |

### Methodik

| Prinzip | Umsetzung |
|---|---|
| Testziel-Auswahl | Nutzerinteraktion / Seitenverhalten / Rollenbasierte Sichtbarkeit |
| Abbruchkriterium | Alle Feature-Matrix-Szenarien (Theme × Rolle) abgedeckt |
| Testtiefe | Happy-Path + Rollen-Guards + Theme-Rendering + Fehlermeldungen |
| Verifikation | DOM-Assertions (Selektoren, Textinhalt), HTTP-Status, URL-Prüfung |

### Eingangsmaterial je Iteration

- **Scope-Definition:** Feature-Bereich oder Seiten, für die Systemtests erstellt werden sollen.
- **Feature-Matrix** (`docs/tds_conditions_ref.md`): Feature-Referenz-IDs, Teststufe-3-Einträge.
- **Abdeckungsmatrix** (`docs/tds_coverage_ref.md`): Bestehende L4-Testklassen und -anzahlen.
- **Bestehende L3-Tests**: Falls für das Feature Komponentenintegrationstests existieren — als Analyse-Quelle für fachliches Verhalten, EP/BVA und Guards.
- **Upstream-Quellcode**: Anwendungsimplementierung (Handler, Views, Templates).
- **Bestehende L4-Tests**: Muster-Specs für Playwright-Patterns und Helper-Nutzung.

---

## 2 Ablauf

| Schritt | Zweck | Ausführung |
|---|---|---|
| 1 | Kontext lesen: `docs/tp_overview_spec.md` für Doku-/Code-Vorgaben | Einmalig pro Iterations-Serie |
| 2 | Feature-Analyse: Upstream-Code lesen, Seiten/Routen/Views identifizieren | Einmalig pro Iterations-Serie |
| 3 | L3-Referenz analysieren: Bestehende KIT-Tests lesen, EP/BVA-Muster übernehmen | Nach Schritt 2, falls L3-Tests vorhanden |
| 4 | L4-Muster analysieren: Bestehende Playwright-Specs lesen, Patterns identifizieren | Nach Schritt 2 |
| 5 | Spezifikation erstellen (→ Vorlage Abschnitt 5) | Nach Schritten 2–4 |
| 6 | Tests implementieren: → [5-Phasen-Arbeitsablauf](wf_test-iteration_guide.md#7-arbeitsablauf-je-feature-5-phasen) | Nach Schritt 5 |
| 7 | Doku-Update: Abdeckungsmatrix, Methodik, Ratchet, Feature-Matrix aktualisieren | Nach Schritt 6 |
| 8 | Abschluss: → [Voll-Lauf, Ratchet, Konsistenzprüfung, Kein Commit, wird manuell gemacht](wf_test-iteration_guide.md#10-abschluss-voll-lauf-ratchet-konsistenzprüfung-commit) | Nach Schritt 7 |

---

## 3 Vorlage: Analyse-Prompt

Der folgende Prompt wird zu Beginn jeder neuen Systemtest-Iterations-Serie verwendet, um die
Analyse für neue Features systematisch durchzuführen. Er ist als Eingabe für ein AI-gestütztes
Analysewerkzeug gedacht.

---

### Prompt-Template

```
## Aufgabe

Führe eine systematische Systemtest-Analyse für alle <ANZAHL> Features durch, die in der
Feature-Matrix (`docs/tds_conditions_ref.md`) für Teststufe 3 identifiziert und in der
Abdeckungsmatrix (`docs/tds_coverage_ref.md`) noch nicht in der L4-Spalte abgedeckt sind.
Ergebnis sind drei Ausgabetypen:
1. Übergreifende-Konzepte-Datei (nur neue, iterationsspezifische Patterns)
2. Umsetzungsplan-Datei (Gesamtstatus, Reihenfolge)
3. Je Feature eine Spezifikations-Datei unter `docs/systemtest/testspezi/<FEATURE_ID>_systemtest_spezi.md`

**Noch kein Test-Code schreiben.** Ausschließlich Analyse und Planung (Phasen P1 + P2 je Feature).

## Eingabedaten — zuerst lesen

| Datei | Zweck |
|---|---|
| `docs/tp_overview_spec.md` | Einstiegspunkt: Doku- und Code-Vorgaben, Verlinkung aller Subdokumente |
| `docs/tds_conditions_ref.md` | Feature-Beschreibungen, Teststufen, Prioritäten — Teststufe-3-Einträge filtern |
| `docs/tds_coverage_ref.md` | Bestehende L4-Abdeckung — Gap-Analyse für L4-Spalte |
| `docs/tds_methodik_spec.md` | ISTQB-Verfahren pro Feature — Anwendungsfall-Test für L4 leitend |
| `docs/wf_test-iteration_guide.md` | Bestehende Methodiken — nicht duplizieren |
| `upstream/webtrees/app/Http/RequestHandlers/` | SUT-Quellcode — Handler-Klassen für Routen und Verhalten |
| `upstream/webtrees/resources/views/` | View-Templates — DOM-Struktur, Selektoren, Formularfelder |
| `layer3-integration/tests/` | L3-Tests — EP/BVA-Logik, fachliche Guards als Referenz |
| `layer4-e2e/tests/` | Bestehende L4-Specs — Playwright-Patterns, Helper-Nutzung |
| `layer4-e2e/helpers/` | Helper-Infrastruktur: auth, themes, roles, otel, perfschema |

## Features im Scope

[Auflistung der Feature-Referenz-IDs und deren Seiten/Handler]

## Ausgabedateien

### 1. Übergreifende Konzepte (iterationsspezifisch)

Neue Konzepte, die in `wf_test-iteration_guide.md` noch nicht vorkommen. Nur neue Konzepte —
Bestehendes wird referenziert, nicht kopiert.

### 2. Umsetzungsplan

Gesamtplan mit Status-Tabelle und empfohlener Reihenfolge (Runden).

### 3. Feature-Spezifikationen

Pro Feature eine Datei im Format der Spezifikations-Vorlage
(→ wf_code-to-systemtest_guide.md, Abschnitt 5).

## Analyse-Leitfaden

### L3-Referenz-Analyse
1. Für jedes Feature prüfen: Existiert ein L3-Test?
2. Falls ja: EP/BVA-Logik, Guard-Branches und Fixtures extrahieren
3. Bestimmen: Welche L3-Aspekte lassen sich als Black-Box-Test im Browser nachweisen?
4. L3-Test ≠ L4-Test: L3 prüft DB-Postconditions, L4 prüft DOM und HTTP-Verhalten

### Upstream-Analyse
1. Route identifizieren (WebRoutes.php → Handler → View)
2. View-Templates lesen: relevante Selektoren, Formularfelder, Buttons
3. Auth-Anforderungen des Handlers prüfen (welche Rolle benötigt?)
4. Theme-Abhängigkeit bestimmen: Rendert die Seite theme-spezifisch?

### Pattern-Entscheidung
Für jedes Feature bestimmen, welches der 5 Playwright-Patterns passt:

| Pattern | Wann | Fixture | Beispiel-Spec |
|---|---|---|---|
| Theme-Loop | Seiten-Rendering über alle Themes | `perfschema-fixture` + `theme-switch` | `records.spec.ts`, `search-forms.spec.ts` |
| Privacy-Role | Rollenbasierte Sichtbarkeit | `perfschema-fixture` + `privacy-roles` | `privacy-visibility.spec.ts`, `access-control.spec.ts` |
| Admin-Only | Admin-geschützte Seiten/Aktionen | `perfschema-fixture` + `auth` (Admin) | `upload-validation.spec.ts` |
| API-Only | Kein Browser nötig (Header, Status) | `otel-fixture` + `request` | `security-headers.spec.ts` |
| Security-Audit | Sicherheitstests mit Probe-Header | `otel-fixture` + `security-audit` | `setup-lock.spec.ts` |

### Priorisierungs-Kriterien für Reihenfolge
- Priorität aus Feature-Matrix (Hoch > Mittel > Niedrig)
- Aufwand (Niedrig/Mittel vor Hoch)
- Verfügbarkeit von L3-Referenztests (erleichtert die Analyse)
- Gemeinsames Pattern gruppieren (z. B. alle Theme-Loop-Tests in einer Runde)

## Formale Anforderungen

- SPDX-Header auf jede neue .md-Datei: `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->`
- Referenz-IDs exakt wie in der Feature-Matrix
- Aufwandskategorien: Niedrig / Mittel / Hoch (→ wf_test-iteration_guide.md Abschnitt 6)
- Keine Code-Dateien erstellen — ausschließlich .md-Dokumente
- Keine Änderungen an bestehenden Dokumenten oder Testklassen
- Keine Analyse bereits abgeschlossener Features
```

---

## 4 Playwright-Patterns — Referenz

Dieses Kapitel dokumentiert die in `layer4-e2e/` etablierten Patterns. Neue Systemtests
folgen diesen Mustern, sofern die Spezifikation nichts anderes vorgibt.

### 4.1 Datei- und Namenskonventionen

| Aspekt | Konvention |
|---|---|
| **Dateiname** | `<feature>.spec.ts` (kebab-case) |
| **Ablage regulär** | `layer4-e2e/tests/` |
| **Ablage Sicherheit** | `layer4-e2e/tests/security/` |
| **SPDX-Header** | `// SPDX-License-Identifier: AGPL-3.0-or-later` (erste Zeile) |
| **Verfolgbarkeit** | `@see docs/tds_conditions_ref.md <IDs>` im JSDoc-Block |
| **Testname** | `<FEATURE_ID> — <Beschreibung> [${theme}]` (Theme-Suffix nur bei Theme-Loop) |
| **Sprache Code** | TypeScript — Kommentare auf Deutsch (Repo-Locale `de_DE`) |

### 4.2 Fixture-Wahl

| Fixture | Import | Funktion |
|---|---|---|
| `perfschema-fixture` | `import { test, expect } from '../helpers/perfschema-fixture'` | Standard: OTel-Tracing + PerfSchema-Extraktion pro Test |
| `otel-fixture` | `import { test, expect } from '../helpers/otel-fixture'` | Leichtgewichtig: nur OTel-Tracing, kein PerfSchema (Security-Tests) |

**Regel:** `perfschema-fixture` als Default. `otel-fixture` nur für Security-Tests
(`tests/security/`) oder Tests ohne MySQL-Interaktion.

### 4.3 Theme-Loop-Pattern

Für Seiten, deren Rendering theme-abhängig ist (Navigation, Charts, Listen, Formulare).

```typescript
// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: <Feature-Beschreibung>
 *
 * @see docs/tds_conditions_ref.md <FEATURE_IDs>
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto('/login/demo');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('input[name="password"]', ADMIN_PASSWORD);
      await page.locator('button[type="submit"]').last().click();
      await page.waitForLoadState('networkidle');
    });

    test(`<ID> — <beschreibung> [${theme}]`, async ({ page }) => {
      const response = await page.goto('<route>');
      expect(response?.status()).toBeLessThan(500);
      // DOM-Assertions ...
    });
  });
}
```

**Multiplikator:** 5 Themes × N Tests = 5N Testläufe.

### 4.4 Privacy-Role-Pattern

Für Features mit rollenabhängiger Sichtbarkeit (P-Domäne, Zugriffskontrolle).

```typescript
import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';

test.describe('<Feature-Beschreibung>', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  test('<ID> — visitor <verhalten>', async ({ page }) => {
    await logoutRole(page);
    await page.goto('<route>');
    // Visitor-Assertions ...
  });

  test('<ID> — member <verhalten>', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('<route>');
    // Member-Assertions ...
  });
});
```

**Rollen:** `visitor`, `member`, `editor`, `moderator`, `manager` (→ `privacy-roles.ts`).
**Baum:** Privacy-Tests nutzen den `privacy`-Baum mit vorbereiteten GEDCOM-Daten.

### 4.5 Admin-Only-Pattern

Für Admin-geschützte Seiten (A-Domäne, Imports, Uploads).

```typescript
import { test, expect } from '../helpers/perfschema-fixture';
import { ADMIN_PASSWORD } from '../helpers/auth';

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test('<ID> — <beschreibung>', async ({ page }) => {
  // Admin-spezifische Seite/Aktion ...
});
```

### 4.6 API-Only-Pattern

Für Tests ohne Browser-Interaktion (Header-Prüfungen, Status-Codes).

```typescript
import { test, expect } from '../../helpers/otel-fixture';

test('<ID>: <beschreibung>', async ({ request }) => {
  const response = await request.get('<route>');
  expect(response.headers()['<header>']).toBe('<wert>');
});
```

### 4.7 Assertion-Idiome

| Idiom | Zweck | Beispiel |
|---|---|---|
| `expect(response?.status()).toBeLessThan(500)` | Kein Server-Fehler | Smoke-Basisprüfung |
| `await expect(page.locator('...')).toBeVisible()` | Element sichtbar | Formular-Rendering |
| `expect(content).toContain('...')` | Textinhalt vorhanden | Personenname, Überschrift |
| `expect(content).not.toContain('...')` | Textinhalt fehlt | Datum nicht sichtbar (Privacy) |
| `await expect(page).not.toHaveURL(/pattern/)` | URL-Prüfung | Nach Login nicht mehr auf /login |
| `expect(editCount).toBeGreaterThan(0)` | Mindestens ein Element | Edit-Links für Editor |

### 4.8 Konfigurationskontrahenten

| Parameter | Wert | Quelle |
|---|---|---|
| `timeout` | 30.000 ms | `playwright.config.ts` |
| `retries` | 1 | `playwright.config.ts` |
| `workers` | 1 (sequentiell) | Shared State: Login-Session, DB |
| `baseURL` | `http://webtrees:80` | Container-Netzwerk |
| `browser` | Chromium (headless) | `playwright.config.ts` |
| `testIgnore` | `**/security/**` | Security-Tests haben eigene Config |

### 4.9 Error-Page-Verification (Provozierte Fehler)

Pattern für das gezielte Testen von Fehlerseiten — im Gegensatz zur üblichen
Schutzklausel `expect(status).toBeLessThan(500)` werden hier absichtlich Fehler provoziert:

| Fehlercode | Provokation |
|---|---|
| 404 Not Found | Nicht existierende URL aufrufen |
| 403 Forbidden | Geschützte Seite ohne Login aufrufen |
| 410 Gone | Gelöschten Record aufrufen |
| 405 Method Not Allowed | POST auf GET-only-Route |

```typescript
const response = await page.goto('/tree/demo/individual/NONEXISTENT_XREF');
expect(response?.status()).toBe(404);
await expect(page.locator('.alert.alert-danger')).toBeVisible();
```

Error-Pages nutzen Bootstrap-Klassen (`.alert.alert-danger`) und sind nicht theme-spezifisch —
ein Theme-Loop ist in der Regel nicht nötig.

### 4.10 DataTable-Wait-Pattern (AJAX-Reload)

Für Seiten mit jQuery DataTables und asynchronem Nachladen nach Formular-Interaktion:

```typescript
// Formular-Änderung → AJAX-Reload abwarten
await page.check('input[name="filter"][value="option"]');
await page.waitForLoadState('networkidle');

// DataTable-Container prüfen
await expect(page.locator('table.datatable-selector')).toBeVisible();
```

Für AJAX-Endpoints mit Button-basierten Aktionen zusätzlich `waitForResponse` nutzen:

```typescript
const [response] = await Promise.all([
    page.waitForResponse(resp => resp.url().includes('/api/endpoint')),
    page.click('button[data-action="reload"]'),
]);
```

### 4.11 API-basierte Redirect-Verification

Für Handler, die mit HTTP-Redirects (301/302) antworten und keine HTML-Seite rendern —
klassische DOM-Assertions greifen nicht.

**Redirect-Follow-Kontrolle:** Playwright folgt Redirects standardmäßig. Für Status-Code-Prüfungen
`maxRedirects: 0` setzen:

```typescript
const response = await request.get('/legacy-route?param=value', {
    maxRedirects: 0,
});
expect(response.status()).toBe(301);
expect(response.headers()['location']).toMatch(/\/expected\/target/);
```

**Stichproben-Ansatz:** Wenn viele Handler dem gleichen Redirect-Pattern folgen, werden
stichprobenartig 5–8 repräsentative Handler getestet. Die Auswahl deckt verschiedene
Parameter-Varianten ab (einfache IDs, zusammengesetzte Parameter, Sonderfälle).

---

## 5 Vorlage: Systemtest-Spezifikation

Für jedes Feature wird eine Spezifikationsdatei erstellt.
Ablage: `docs/systemtest/testspezi/<FEATURE_ID>_systemtest_spezi.md`

### Template für Teststufe-3-Features

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — <REF>: <Feature-Name>

**Referenz:** <REF> | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `<URL-Pfad>` → `<RequestHandler-Klasse>`
**L3-Referenztest:** <L3-Testklassenname falls vorhanden, sonst „keiner">
**Übergreifende Konzepte:** → [wf_test-iteration_guide.md](wf_test-iteration_guide.md)

---

## Status quo

[Gibt es bereits L4-Tests? L3-Tests als Referenz? Welche Aspekte sind abgedeckt?]

---

## Upstream-Analyse

### Route und Handler

[Route(n) aus WebRoutes.php, Handler-Klasse(n), Auth-Anforderung (Rolle)]

### View-Analyse

[Relevante View-Templates, DOM-Selektoren, Formularfelder, Buttons]

### Theme-Abhängigkeit

[Rendert die Seite theme-spezifisch? → Theme-Loop ja/nein]

---

## L3-Referenz-Analyse

[Falls L3-Tests existieren: welche EP/BVA-Muster, welche Guards, welche Fixtures?
Was davon ist als Black-Box im Browser nachweisbar?]

---

## Bestehende L4-Muster-Analyse

[Welche bestehenden Specs verwenden ein ähnliches Pattern? Referenz-Spec angeben.]

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | <Beschreibung> | <Rolle> | <Erwartetes Verhalten> | Ja/Nein |
| T2 | ... | ... | ... | ... |

---

## Playwright-Pattern

**Gewähltes Pattern:** <Theme-Loop / Privacy-Role / Admin-Only / API-Only / Security-Audit>

**Begründung:** [Warum dieses Pattern? Welche Fixture? Welche Helper?]

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `<feature>.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` oder `layer4-e2e/tests/security/` |
| **Fixture** | `perfschema-fixture` oder `otel-fixture` |
| **Helper** | [auth, theme-switch, privacy-roles — nach Bedarf] |
| **Theme-Loop** | Ja (5 Themes) / Nein |
| **Login-Strategie** | Admin / Role / Kein Login (Visitor) |
| **Baum** | `demo` / `privacy` / `muster` / eigener |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte aktualisieren: `<spec-dateiname> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `3` oder `2, 3` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 3 prüfen (Feature in Liste?) |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren und Orakel ergänzen, falls neues Verfahren |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
```

### Template für EXCLUDED-Features

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — <REF>: <Feature-Name>

**Referenz:** <REF> | **Status:** EXCLUDED — Teststufe 3 nicht anwendbar
**Übergreifende Konzepte:** → [wf_test-iteration_guide.md](wf_test-iteration_guide.md)

## Ausschlussgrund

[1–3 Sätze: Warum ist Teststufe 3 nicht sinnvoll/möglich? Welche Teststufe deckt es ab?]

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | EXCLUDED | [Teststufe X only] |
```

---

## 6 Dokumentations-Update — Checkliste

Nach Abschluss der Testimplementierung sind folgende Dokumente zu aktualisieren:

### 6.1 Abdeckungsmatrix (`docs/tds_coverage_ref.md`)

L4-Spalte der betroffenen Features aktualisieren:

```
| <REF> | <Feature> | ... | ... | `<dateiname>.spec.ts` [<Siegel>] ✅ *(N Tests)* | OK | — |
```

**Siegel-Regeln für L4:**

| Siegel | Kriterium |
|---|---|
| `[EP]` | DataProvider-ähnliche Parametrisierung oder ≥3 Szenarien pro EP |
| `[Spec-B]` | Testmethoden 1:1 einer externen Spezifikation folgend |
| `[Spec-C]` | Fachliche Assertions, pragmatisch |
| `[Smoke]` | 1–3 Assertions, nur Rendering-Prüfung (kein fachlicher Pfad) |

### 6.2 Feature-Matrix (`docs/tds_conditions_ref.md`)

- Teststufe-Spalte prüfen: Feature muss `3` oder Kombination mit `3` enthalten.
- Falls ein neues Feature hinzukommt: Zeile in der Feature-Matrix ergänzen.

### 6.3 Überdeckungsstrategie (`docs/tp_ratchet_spec.md`)

- Endekriterien Teststufe 3: Feature-IDs in der Auflistung ergänzen.
- Sicherheitstest-Track: Falls Security-Features → Prüfpunkt-Abdeckung aktualisieren.

### 6.4 Testentwurfsverfahren (`docs/tds_methodik_spec.md`)

- Testfall-Verteilung nach Teststufe: Zähler Teststufe 3 aktualisieren.
- Testentwurfsverfahren pro Domäne: Falls neues Verfahren → Zeile ergänzen.
- Testorakel: Falls neuer Orakeltyp (z. B. neue GEDCOM-Fixture) → Zeile ergänzen.
- Prioritätsverteilung: Zähler bei Verschiebungen aktualisieren.

### 6.5 Verfolgbarkeit

- `@see docs/tds_conditions_ref.md <IDs>` in der Spec-Datei (JSDoc-Block).
- Feature-ID im Testnamen: `test('<ID> — <Beschreibung>', ...)`.

---

## 7 Qualitätsstufen (L4-spezifisch)

| Stufe | L4-Interpretation | Typische Tests |
|---|---|---|
| **Smoke** | Seite lädt ohne 5xx, Body sichtbar | `expect(status).toBeLessThan(500)` |
| **Spec-C (pragmatisch)** | Fachliche DOM-Assertions: Textinhalt, Elemente, Formulare | `expect(content).toContain(...)` |
| **Spec-B (strikt)** | Verhalten 1:1 aus Spezifikation (RFC, GEDCOM, OWASP) | Header-Werte, Security-Checks |
| **EP-complete** | ≥3 Szenarien systematisch (Rollen × Zustände) | Privacy-Matrizen, Upload-Varianten |

**Faustregel für L4-Qualitätsstufe:**

- Navigationsseiten ohne fachliche Logik → Smoke (5 Themes genügt)
- Seiten mit rollenabhängigem Verhalten → Spec-C (Rolle × Erwartung)
- Sicherheitskritische Features → Spec-B (RFC/OWASP-basiert)
- Privacy-Features mit Kombinatorik → EP (Rolle × RESN × Zustand)
