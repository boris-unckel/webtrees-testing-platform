<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Übergreifende Konzepte — Systemtest-Iteration L4

**Erstellt:** 2026-04-12
**Basis:** [`testcov_systemtests_delta.md`](../testcov_systemtests_delta.md),
[`wf_code-to-systemtest_guide.md`](../wf_code-to-systemtest_guide.md)

Dieses Dokument beschreibt **neue, iterationsspezifische Konzepte** für die
L4-Systemtest-Erweiterung (29 Features). Bereits dokumentierte Basis-Patterns
(Theme-Loop, Privacy-Role, Admin-Only, API-Only, Security-Audit) sind in
[wf_code-to-systemtest_guide.md Abschnitt 4](../wf_code-to-systemtest_guide.md)
beschrieben und werden hier nur referenziert, nicht wiederholt.

---

## 1 Formular-Submit-Verification

**Betroffene Features:** E01, E02, E03, E04, P37, P38, A01, A04, K01, K02

**Problem:** Bestehende L4-Tests prüfen überwiegend Seiten-Rendering (GET → DOM sichtbar).
Viele der neuen Features erfordern Formular-Interaktion: Felder ausfüllen, POST absenden,
Ergebnis nach Redirect verifizieren.

**Ablauf:**

1. Formular-Seite laden (`page.goto`)
2. Felder ausfüllen (`page.fill`, `page.selectOption`, `page.check`)
3. Submit (`button[type="submit"]` klicken)
4. `waitForLoadState('networkidle')` — POST + Redirect abwarten
5. Redirect-Ziel prüfen (`expect(page).toHaveURL(...)`)
6. Ergebnis im DOM verifizieren (`expect(locator).toContainText(...)`)

**Kombination:** Typischerweise mit Theme-Loop (Formular-Rendering ist theme-abhängig)
oder Admin-Only (Editor-/Admin-Rolle erforderlich).

**Abgrenzung:** Schritt 6 unterscheidet dieses Pattern von Smoke-Tests. Es wird nicht nur
geprüft, dass die Seite lädt, sondern dass die Aktion einen **fachlich sichtbaren Effekt** hat.

---

## 2 JS-Widget-Interaktion

**Betroffene Features:** E08 (TomSelect/AutoComplete), S47 (Interaktiver Stammbaum)

**Problem:** JavaScript-Widgets (Dropdowns, Canvas, SVG) erfordern spezifische
Playwright-Interaktionen, die über `fill`/`click` hinausgehen.

### 2.1 TomSelect/AutoComplete (E08)

- Input in `.ts-control input` tippen
- `.ts-dropdown .option`-Selektor auf `visible` warten
- Eintrag per Klick wählen
- Hidden-Input-Wert (XREF) verifizieren

**Achtung:** TomSelect-Selektoren können zwischen Themes variieren.
Theme-Loop empfohlen. Exakte Selektoren in S2 aus View-Templates ableiten.

### 2.2 Canvas/SVG-Widget (S47)

- Canvas/SVG-Element auf Sichtbarkeit prüfen
- Knoten-Interaktion (Klick auf Personen-Element)
- Detail-Panel-Inhalt verifizieren

**Hinweis:** Ob das Widget `<canvas>` oder `<svg>` rendert, muss in S2 aus dem
Upstream-JavaScript-Code ermittelt werden. Die Interaktions-API unterscheidet sich
grundlegend zwischen beiden Varianten.

---

## 3 Such-Ausführungs-Verification

**Betroffene Features:** S05, S06, S07, S08, S10

**Problem:** `search-forms.spec.ts` prüft nur das Rendering der Suchformulare. Die
eigentliche Suchausführung (Eingabe → Submit → Ergebnistabelle mit fachlich korrekten
Treffern) fehlt.

**Ablauf:**

1. Suchseite laden
2. Suchfelder befüllen (Name, Datum, phonetischer Modus etc.)
3. Submit
4. Ergebnistabelle prüfen: Mindestens ein Treffer vorhanden
5. Erwarteten Treffer namentlich verifizieren (aus GEDCOM-Demo-Daten bekannt)

**Abgrenzung zu `search-forms.spec.ts`:** Bestehende Tests → Formular-Rendering.
Neue Tests → Suchergebnis-Qualität (fachlich korrekte Treffer).

### Phonetische Suche (S07, S08)

Zusätzlicher Aspekt: Der phonetische Algorithmus muss über das Suchergebnis nachgewiesen
werden — exakte Schreibweise liefert keinen Treffer, phonetische Variante schon.
Die konkreten Testdaten hängen von den GEDCOM-Fixtures im Demo-Baum ab (S2-Analyse).

### Paginierung (S10)

Suchergebnis mit vielen Treffern → Paginierungs-Controls (`.pagination`) sichtbar →
Seitenwechsel → andere Ergebnisse auf Seite 2.

---

## 4 Mehrstufiger-Workflow

**Betroffene Features:** P30, P41 (Merge), P40 (Pending Changes)

**Problem:** Diese Features bestehen aus mehreren aufeinanderfolgenden Seiten/Aktionen
mit Zustandsübergängen. Ein einzelner `page.goto()` + Assert reicht nicht.

### 4.1 Merge-Workflow (P30 → P41)

```
Schritt 1: Merge-Seite laden, zwei XREFs eingeben (P30)
Schritt 2: Vorschau prüfen (P30)
Schritt 3: Merge bestätigen und ausführen (P41)
Schritt 4: Ergebnis verifizieren — ein Record bleibt, einer ist weg (P41)
```

### 4.2 Pending-Changes-Workflow (P40)

```
Schritt 1: Als Editor einloggen, Änderung erzeugen → Pending Change entsteht
Schritt 2: Als Moderator einloggen, Pending-Changes-Seite öffnen
Schritt 3: Accept/Reject klicken
Schritt 4: Ergebnis verifizieren — Änderung sichtbar/nicht sichtbar
```

**Multi-Role:** P40 erfordert Login-Wechsel innerhalb eines Tests.
Helper-Kombination: `loginAsRole(page, 'editor')` → Aktion → `logoutRole(page)` →
`loginAsRole(page, 'moderator')` → Verification.

**Baum:** Privacy-Baum (Rollen-User vorhanden) oder Demo-Baum (Admin-Login).
Entscheidung in S2/S3 pro Feature.

---

## 5 Modal-Dialog-Interaktion

**Betroffene Features:** E04 (Nebenrecords), E05 (Medienobjekte)

**Ablauf:**

1. Auslöser-Button klicken (`data-bs-target`-Attribut aus View-Template)
2. `.modal.show`-Selektor auf `visible` warten
3. Felder im Modal ausfüllen (`.modal.show input[name="..."]`)
4. Modal-Submit
5. Modal-Schließung verifizieren (`.modal.show` → `not.toBeVisible`)
6. Ergebnis auf der Hauptseite prüfen (Verknüpfung, XREF sichtbar)

**Bootstrap-Konvention:** webtrees nutzt Bootstrap-Modale. Der aktive Modal-Selektor
ist `.modal.show`. Die konkreten `data-bs-target`-Werte werden in S2 ermittelt.

---

## 6 Chart-Rendering-Verification

**Betroffene Features:** S16 (Beziehungsfinder), S18 (5 Chart-Typen), S41 (Statistik)

**Abgrenzung:** `pedigree.spec.ts` testet bereits einen Chart-Typ als Referenz.
Die neuen Tests folgen dem gleichen Theme-Loop-Grundmuster.

**Smoke-Level (S18):** Pro Chart-Typ: Route laden, kein 5xx, Chart-Container sichtbar.
DataProvider-artig über ein Array von Chart-Routen iterieren.

**Spec-C-Level (S16):** Über Smoke hinaus: Zwei Personen auswählen, Beziehungspfad-Anzeige
verifizieren. Nutzt zusätzlich Formular-Submit-Verification (Konzept 1).

**Statistik (S41):** Statistik-Seite laden, Diagramm-/Tabellen-Container sichtbar.
Konkrete Statistik-Werte nur prüfen, wenn aus Demo-GEDCOM deterministisch ableitbar.

---

## 7 Upstream-Only-Analyse (ohne L3-Referenz)

**Betroffene Features:** K01 (Kontaktformular), K02 (Benutzer-Nachrichten)

**Problem:** Für diese Features existieren keine L3-Komponentenintegrationstests.
Schritt S3 (L3-Referenz analysieren) wird durch eine direkte Upstream-Code-Analyse ersetzt.

**Angepasstes Vorgehen in S3:**

1. Route identifizieren (`WebRoutes.php`)
2. Handler-Klasse lesen: Guards, Validierung, Erfolgs-/Fehlerpfade
3. View-Template lesen: Formularfelder, Selektoren, Pflichtfelder
4. Fachliche Szenarien direkt aus dem Code ableiten
5. Im Spezifikations-Template dokumentieren: `L3-Referenztest: keiner (Upstream-Ableitung)`

**Einschränkung:** Ohne L3-Referenz fehlen EP/BVA-Vorlagen. Die Testtiefe orientiert sich
am sichtbaren UI-Verhalten (Spec-C statt EP-complete).

---

## 8 Empfehlung: Test-Datei-Zusammenlegung

Einige Features sind fachlich so eng gekoppelt, dass getrennte `.spec.ts`-Dateien
unpraktisch wären. Empfehlung für gemeinsame Test-Dateien:

| Features | Gemeinsame Spec-Datei | Grund |
|---|---|---|
| S05 + S06 | `advanced-search-execution.spec.ts` | Gleiche Suchseite, unterschiedliche Felder |
| S07 + S08 | `phonetic-search-execution.spec.ts` | Gleiche Suchseite, unterschiedlicher Algorithmus |
| P30 + P41 | `merge-records.spec.ts` | Sequenzieller Workflow (Auswahl → Ausführung) |

**Jedes Feature erhält trotzdem eine eigene Spezifikations-Datei** (`testspezi/`).
Die Zusammenlegung betrifft nur den Test-Code und wird in S5/S6 entschieden.
