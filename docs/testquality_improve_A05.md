<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A05: Modul-Konfiguration

**Referenz:** A05 | **SUT:** `app/Http/RequestHandlers/ModulesAllPage.php`, `ModulesAllAction.php`, `ModulesAnalyticsPage.php`, `ModulesBlocksPage.php`, `ModulesChartsPage.php`, `ModulesFootersPage.php`, `ModulesHistoricEventsPage.php`, `ModulesLanguagesPage.php`, `ModulesMapsPage.php`, `ModulesMenusPage.php`, `ModulesReportsPage.php`, `ModulesSidebarsPage.php`, `ModulesTabsPage.php`, `ModulesThemesPage.php` (+ je Action)
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Bestehende `RequestHandlerBatchAIntegrationTest` und `RequestHandlerBatchBIntegrationTest` decken einige Admin-Handler als Smoke-Tests ab. Die ~46 Modul-Konfigurationshandler fehlen.

---

## SUT-Kernbefunde

### ModulesAllPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → Liste aller Module mit Enabled-Status | Nein |

### ModulesAllAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | POST → Module aktivieren/deaktivieren (status-Toggle) | Nein |
| B2 | Ungültiger Modulname → tbd bei P1 | Nein |

Alle anderen Page/Action-Handler folgen demselben Schema: GET → View mit Modulliste des jeweiligen Typs; Action → Toggle + redirect.

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ModulesAllPage GET | 200, View mit Modulliste |
| EP2 | ModulesAllAction POST: Modul deaktivieren | 302, Modul in DB als 'disabled' |
| EP3 | ModulesAllAction POST: Modul aktivieren | 302, Modul in DB als 'enabled' |
| EP4–EP17 | Alle anderen Page-Handler (DataProvider) | Smoke: GET → 200 |

---

## Batch-Strategie

**~46 Handler → DataProvider-Ansatz:**
- `ModulesAllPage` + `ModulesAllAction`: vollständige EP-Analyse
- Alle anderen Page-Handler: DataProvider-Smoke (GET → 200)
- Alle anderen Action-Handler: DataProvider-Smoke (POST → 302)

Neue Klasse `ModuleConfigIntegrationTest extends MysqlTestCase`. Admin-Auth, kein Tree nötig.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | ModulesAllPage DI: ModuleService. ModulesAllAction DI: ModuleService; boolean('status-{name}', false). AbstractModuleComponentPage DI: ModuleService+TreeService |
| P2: Soll-Design | ✅ | EP1 (AllPage→200), EP2 (AllAction POST→302), DataProvider EP4–EP8 (Analytics/Blocks/Charts/Menus/Reports→200) |
| P3: Test-Coding | ✅ | `ModuleConfigIntegrationTest` (7 Tests: 2 direkt + 5 DataProvider) |
| P4: Ausführung + Fixing | ✅ | 7/7 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix A05 aktualisiert |
