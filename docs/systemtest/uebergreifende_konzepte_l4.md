<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Übergreifende Konzepte — Systemtest-Iteration L4 (Runde 2)

**Erstellt:** 2026-04-12
**Basis:** [`testcov_komponentenintegration_systemtests_delta.md`](../testcov_komponentenintegration_systemtests_delta.md),
[`wf_code-to-systemtest_guide.md`](../wf_code-to-systemtest_guide.md)

Dieses Dokument beschreibt **neue, iterationsspezifische Konzepte** für die
L4-Systemtest-Erweiterung (3 Features: M16, A08, S53). Die erste Iteration
(29 Features) ist abgeschlossen (Commit 679ef1b, 513 Tests). Bereits dokumentierte
Basis-Patterns (Theme-Loop, Privacy-Role, Admin-Only, API-Only, Security-Audit)
sind in [wf_code-to-systemtest_guide.md Abschnitt 4](../wf_code-to-systemtest_guide.md)
beschrieben und werden hier nur referenziert, nicht wiederholt.

Die Konzepte der ersten Iteration (Formular-Submit-Verification, JS-Widget-Interaktion,
Such-Ausführungs-Verification, Mehrstufiger-Workflow, Modal-Dialog-Interaktion,
Chart-Rendering-Verification) bleiben gültig, sind aber für die drei neuen Features
nicht relevant.

---

## 1 Error-Page-Verification (Provozierte Fehler)

**Betroffenes Feature:** M16 (Exception-Handling & Error-Page-Rendering)

**Problem:** Die bisherigen L4-Tests prüfen `expect(response?.status()).toBeLessThan(500)`
als generische Schutzklausel. M16 erfordert das Gegenteil — absichtlich Fehler provozieren
und die Error-Page-Darstellung verifizieren.

### 1.1 Fehler-Provokation

Verschiedene HTTP-Fehlercodes können durch gezielte Requests ausgelöst werden:

| Fehlercode | Provokation | Route |
|---|---|---|
| 404 Not Found | Nicht existierende URL aufrufen | `/tree/demo/individual/XREF_NOT_EXISTS` |
| 403 Forbidden | Admin-Seite ohne Login aufrufen | `/admin/control-panel` |
| 410 Gone | Gelöschten Record aufrufen | Abhängig von Test-Setup |
| 405 Method Not Allowed | POST auf GET-only-Route | `request.post('/tree/demo')` |

### 1.2 Error-Page DOM-Assertions

Die Error-Pages rendern einen `.alert.alert-danger`-Container:

```typescript
// 404 prüfen
const response = await page.goto('/tree/demo/individual/NONEXISTENT_XREF_12345');
expect(response?.status()).toBe(404);
await expect(page.locator('.alert.alert-danger')).toBeVisible();
```

### 1.3 Kein Theme-Loop nötig

Error-Pages verwenden das Layout `layouts/default` (mit Theme-CSS) oder das
Fallback-Layout `layouts/error` (ohne Theme). Die Fehleranzeige selbst ist
Bootstrap-basiert und nicht theme-spezifisch. Ein Theme-Loop wäre möglich, aber
der Mehrwert ist gering — Spec-C ohne Theme-Loop reicht.

---

## 2 Admin-DataTable-Verification

**Betroffenes Feature:** A08 (Medienverwaltung Admin)

**Problem:** Die Admin-Media-Seiten nutzen jQuery DataTables mit AJAX-Datenquellen.
Nach Formular-Interaktion (Radio-Button-Klick, Folder-Auswahl) wird die Tabelle
asynchron neu geladen.

### 2.1 DataTable-Wait-Pattern

```typescript
// Formular-Änderung → AJAX-Reload abwarten
await page.check('input[name="files"][value="local"]');
await page.waitForLoadState('networkidle');

// DataTable-Container prüfen
await expect(page.locator('table#wt-datatables-admin-media')).toBeVisible();
```

### 2.2 Admin-Route-Group

Alle Admin-Media-Routen liegen unter `/admin/` und erfordern `AuthAdministrator`-Middleware.
Das bestehende Admin-Only-Pattern (-> wf_code-to-systemtest_guide.md 4.5) ist direkt
anwendbar.

### 2.3 FixLevel0-Media-Interaktion

Die FixLevel0-Seite enthält Buttons mit `data-*`-Attributen für AJAX-Aktionen.
Da die AJAX-Endpoints POST-basiert sind und nach dem Button-Klick die DataTable
neu laden, muss `waitForResponse` oder `waitForLoadState('networkidle')` genutzt
werden.

---

## 3 Legacy-Redirect-API-Testing

**Betroffenes Feature:** S53 (Legacy-URL-Weiterleitungen)

**Problem:** Die ~27 Legacy-Redirect-Handler antworten mit HTTP 301 und
`Location`-Header. Sie rendern keine HTML-Seite — klassische DOM-Assertions
greifen nicht.

### 3.1 Redirect-Follow-Kontrolle

Playwright folgt Redirects standardmäßig. Für Status-Code-Prüfungen muss
der Redirect *nicht* gefolgt werden:

```typescript
// API-Kontext: Redirects nicht folgen
const response = await request.get('/individual.php?ged=demo&pid=I1', {
    maxRedirects: 0,
});
expect(response.status()).toBe(301);
expect(response.headers()['location']).toMatch(/\/tree\/demo\/individual\/I1/);
```

Alternativ mit `page.goto()` und Response-Abfang:

```typescript
const [response] = await Promise.all([
    page.waitForResponse(resp => resp.url().includes('individual.php')),
    page.goto('/individual.php?ged=demo&pid=I1'),
]);
// page.goto() folgt dem Redirect — der Status bezieht sich auf die finale Seite
// Deshalb besser: API-Only-Pattern mit request-Kontext
```

### 3.2 Stichproben-Ansatz

Da alle 27 Handler dem gleichen Pattern folgen, werden stichprobenartig
5–8 repräsentative Handler getestet:

| Handler | Legacy-URL | Erwarteter Redirect |
|---|---|---|
| RedirectIndividualPhp | `/individual.php?ged=demo&pid=I1` | `/tree/demo/individual/I1` |
| RedirectFamilyPhp | `/family.php?ged=demo&famid=F1` | `/tree/demo/family/F1` |
| RedirectSourcePhp | `/source.php?ged=demo&sid=S1` | `/tree/demo/source/S1` |
| RedirectCalendarPhp | `/calendar.php?ged=demo&view=month` | `/tree/demo/calendar/month/...` |
| RedirectPedigreePhp | `/pedigree.php?ged=demo&rootid=I1` | `/tree/demo/pedigree/...` |

### 3.3 Gone-Exception-Tests (410)

Für ungültige XREFs oder nicht existierende Bäume:

```typescript
const response = await request.get('/individual.php?ged=demo&pid=INVALID_XREF_999', {
    maxRedirects: 0,
});
expect(response.status()).toBe(410);
```

### 3.4 Canonical-Link-Header

Die Redirect-Handler setzen einen `Link: <url>; rel="canonical"` Header für SEO.
Dieser kann als zusätzliche Assertion geprüft werden.

---

## 4 Empfehlung: Test-Datei-Zuordnung

| Feature | Spec-Datei | Pattern | Fixture |
|---|---|---|---|
| M16 | `error-handling.spec.ts` | Spec-C (provozierte Fehler) | `perfschema-fixture` |
| A08 | `media-admin.spec.ts` | Admin-Only + DataTable | `perfschema-fixture` |
| S53 | `legacy-url-redirects.spec.ts` | API-Only (Redirect-Status) | `otel-fixture` |

**M16 und A08** liegen in `layer4-e2e/tests/`, **S53** ebenfalls (kein Security-Test).
