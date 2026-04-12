<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — A01: Stammbaum-Management

**Referenz:** A01 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/admin/trees/manage` (GET), `/admin/trees/create` (GET+POST), `/admin/trees/delete/{tree}` (GET) → `ManageTrees`, `CreateTreePage`, `CreateTreeAction`, `DeleteTreeAction`
**L3-Referenztest:** TreeManagementIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für das Stammbaum-Management existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (TreeManagementIntegrationTest) decken die Handler-Ebene ab (4 Tests: Create Duplikat→302, Create Neu→DB-Eintrag, Delete→204, ManageTrees→200), prüfen aber nicht die End-to-End-Interaktion im Browser — insbesondere nicht, ob angelegte Bäume in der Verwaltungsliste erscheinen und gelöschte Bäume daraus verschwinden.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/admin/trees/manage` | GET | `ManageTrees` |
| `/admin/trees/create` | GET | `CreateTreePage` |
| `/admin/trees/create` | POST | `CreateTreeAction` |
| `/admin/trees/delete/{tree}` | GET | `DeleteTreeAction` |

Alle Handler erfordern Administrator-Berechtigung. `ManageTrees` zeigt eine Übersicht aller Stammbäume mit Verwaltungslinks. `CreateTreePage` rendert das Anlege-Formular (Name, Titel). `CreateTreeAction` validiert den Namen (Duplikatprüfung) und legt den Baum in der DB an. `DeleteTreeAction` löscht einen Baum nach Bestätigung.

### View-Analyse

Die ManageTrees-Seite zeigt eine Tabelle/Liste der vorhandenen Bäume (demo, muster, privacy) mit Links zu Einstellungen und Löschung. Das Anlege-Formular enthält `input[name="tree_name"]` (technischer Name) und `input[name="tree_title"]` (Anzeige-Titel). Bei Duplikat-Name wird auf die Create-Seite zurückgeleitet mit Fehlermeldung.

### Theme-Abhängigkeit

Admin-Seiten nutzen ein festes Layout (kein Theme-Wechsel im Admin-Bereich). Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**TreeManagementIntegrationTest** — 4 Tests:

1. Create mit Duplikat-Name → 302 (Redirect zurück auf Create-Seite)
2. Create mit neuem Name → 302 (Redirect auf ManageTrees), neuer Baum in DB vorhanden
3. Delete mit gültigem Tree → 204 (Baum aus DB entfernt)
4. ManageTrees GET → 200 (Übersichtsseite wird gerendert)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes, DB-Zustand). Sie prüfen nicht das DOM-Rendering der Baumliste und nicht die visuelle Rückmeldung bei Duplikat-Fehlern.

**EP/BVA-Analyse:**

- EP1: Duplikat-Name (→302 to create) — Fehlerfall
- EP2: Neuer Name (→302 to manage, DB-Eintrag) — Erfolgsfall
- EP3: Delete (→204, DB gelöscht) — Destruktiver Erfolgsfall
- EP7: ManageTrees (→200) — Lesefall

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Admin-Stammbaum-Verwaltung. Als Referenz dient `upload-validation.spec.ts` für das Admin-Only-Pattern (Login als Admin, Admin-Seite aufrufen, Interaktion prüfen).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | ManageTrees-Seite lädt korrekt (Liste vorhandener Bäume sichtbar: demo, muster, privacy) | Admin | Seite lädt, Baumliste zeigt alle 3 bekannten Bäume | Nein |
| T2 | Neuen Baum anlegen via Formular, Baum erscheint in Liste | Admin | POST→302, Redirect auf ManageTrees, neuer Baum in Baumliste sichtbar | Nein |
| T3 | Duplikat-Name → Fehlermeldung/Redirect | Admin | POST→302, Redirect auf Create-Seite, Fehlermeldung sichtbar | Nein |
| T4 | Erstellten Test-Baum löschen → Baum verschwindet aus Liste (Cleanup) | Admin | Delete-Aktion, Baum nicht mehr in Baumliste vorhanden | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Stammbaum-Verwaltung ist eine reine Admin-Funktion ohne Theme-Abhängigkeit. Die Testszenarien T2–T4 folgen dem Formular-Submit-Verification-Pattern (Formular laden, Felder ausfüllen, Submit, Redirect verifizieren, Ergebnis im DOM prüfen). T1 ist Smoke-Level (Seite lädt, Inhalt korrekt). T4 dient gleichzeitig als Cleanup, um die Demo-Daten nicht zu verschmutzen.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `tree-management.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Seite) |
| **Login-Strategie** | Admin-Login |
| **Baum** | Kein spezifischer Baum — Admin-Bereich (baumübergreifend) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `tree-management.spec.ts` [Spec-C] ✅ *(4 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
