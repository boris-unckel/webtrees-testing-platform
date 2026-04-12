<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — A05: Modul-Konfiguration

**Referenz:** A05 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/admin/modules` (GET All + POST Action), `/admin/modules/analytics` (GET), `/admin/modules/blocks` (GET), `/admin/modules/charts` (GET), `/admin/modules/menus` (GET), `/admin/modules/reports` (GET) → `ModulesAllPage`, `ModulesAllAction`, `ModulesAnalyticsPage`, `ModulesBlocksPage`, `ModulesChartsPage`, `ModulesMenusPage`, `ModulesReportsPage`
**L3-Referenztest:** ModuleConfigIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Modul-Konfiguration existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (ModuleConfigIntegrationTest) decken die Handler-Ebene ab (6 Tests: All Page→200, All Action→302, Analytics/Blocks/Charts/Menus/Reports Pages→200 via DataProvider), prüfen aber nicht das DOM-Rendering der Modul-Tabellen und nicht die Interaktion mit Enable/Disable-Toggles.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/admin/modules` | GET | `ModulesAllPage` |
| `/admin/modules` | POST | `ModulesAllAction` |
| `/admin/modules/analytics` | GET | `ModulesAnalyticsPage` |
| `/admin/modules/blocks` | GET | `ModulesBlocksPage` |
| `/admin/modules/charts` | GET | `ModulesChartsPage` |
| `/admin/modules/menus` | GET | `ModulesMenusPage` |
| `/admin/modules/reports` | GET | `ModulesReportsPage` |

Alle Handler erfordern Administrator-Berechtigung. `ModulesAllPage` zeigt eine Gesamtübersicht aller Module mit Enable/Disable-Toggles. `ModulesAllAction` speichert die geänderten Modul-Status (aktiviert/deaktiviert). Die Typ-spezifischen Seiten (Analytics, Blocks, Charts, Menus, Reports) zeigen jeweils nur die Module des entsprechenden Typs.

### View-Analyse

Die Modul-Übersichtsseite enthält eine Tabelle mit Modulnamen, Beschreibungen und Aktivierungs-Checkboxen. Die Typ-spezifischen Seiten zeigen gefilterte Listen mit zusätzlichen Konfigurationsoptionen (Reihenfolge, Sidebar-Zuordnung etc.). Alle Seiten nutzen Bootstrap-Tabellen.

### Theme-Abhängigkeit

Admin-Seiten nutzen ein festes Layout. Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**ModuleConfigIntegrationTest** — 6 Tests:

1. All Page GET → 200 (Modul-Übersicht wird gerendert)
2. All Action POST ohne Änderungen → 302 (Redirect, kein Fehler)
3. Analytics Page GET → 200 (via DataProvider)
4. Blocks Page GET → 200 (via DataProvider)
5. Charts Page GET → 200 (via DataProvider)
6. Menus Page GET → 200 (via DataProvider)
7. Reports Page GET → 200 (via DataProvider)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes). Sie prüfen nicht das DOM-Rendering der Modul-Tabellen und nicht die fachliche Wirkung von Modul-(De-)Aktivierung.

**EP/BVA-Analyse:**

- EP1: All Page (→200) — Lesefall
- EP2: All Action ohne Änderungen (→302) — Idempotenter Submit
- EP4–EP8: DataProvider für 5 Modul-Typ-Seiten (Analytics, Blocks, Charts, Menus, Reports → alle 200)

---

## Bestehende L4-Muster-Analyse

**Referenz-Spec:** `upload-validation.spec.ts` — enthält das Admin-Pattern (Login als Admin, Admin-Seite aufrufen). Die Modul-Typ-Seiten können DataProvider-artig über ein Array von Routen iteriert werden, analog zum DataProvider-Ansatz in den L3-Tests.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Module-Übersichtsseite lädt (Tabelle mit Modulen, Enable/Disable-Toggles sichtbar) | Admin | Seite lädt, Modul-Tabelle mit Checkboxen sichtbar | Nein |
| T2 | Modul-Typ-Seite Analytics lädt | Admin | Seite lädt, Analytics-Module aufgelistet | Nein |
| T3 | Modul-Typ-Seite Blocks lädt | Admin | Seite lädt, Block-Module aufgelistet | Nein |
| T4 | Modul-Typ-Seite Charts lädt | Admin | Seite lädt, Chart-Module aufgelistet | Nein |
| T5 | Modul-Typ-Seite Menus lädt | Admin | Seite lädt, Menü-Module aufgelistet | Nein |
| T6 | Modul-Typ-Seite Reports lädt | Admin | Seite lädt, Report-Module aufgelistet | Nein |

**Optional (L4-erweiterbar):** Modul deaktivieren → Frontend-Effekt prüfen (z.B. Chart-Menüpunkt verschwindet). Dies geht über Smoke hinaus und würde Siegel [Spec-C] ergeben. In S6 entscheiden.

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only (wf_code-to-systemtest_guide.md 4.5)
**Begründung:** Alle Testszenarien sind Smoke-Level: Admin-Seite laden, DOM-Inhalt prüfen (Tabelle/Liste sichtbar). Kein Theme-Loop (Admin-Bereich). Die Modul-Typ-Seiten (T2–T6) können über ein Array von Routen DataProvider-artig iteriert werden, um Code-Duplikation zu vermeiden.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `module-configuration.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Seiten) |
| **Login-Strategie** | Admin-Login |
| **Baum** | Kein spezifischer Baum — Admin-Bereich |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `module-configuration.spec.ts` [Smoke] ✅ *(6 Tests)* |
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
