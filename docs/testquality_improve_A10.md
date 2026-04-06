<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A10: Protokolle & Monitoring

**Referenz:** A10 | **SUT:** `app/Http/RequestHandlers/PendingChangesLogPage.php`, `PendingChangesLogData.php`, `PendingChangesLogAction.php`, `PendingChangesLogDelete.php`, `PendingChangesLogDownload.php`, `SiteLogsDownload.php`, `PhpInformation.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. PendingChangesLogPage: GET → change-Log gefiltert, Admin-only. SiteLogsDownload: GET → Log als CSV-Stream. PhpInformation: GET → phpinfo() Output, Admin-only.

---

## SUT-Kernbefunde

### PendingChangesLogPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | DB::table('change')->min('change_time') → null → today als Fallback | Nein |
| B2 | Changes vorhanden → korrekte min/max Datumswerte | Nein |

### SiteLogsDownload (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → Log als CSV-Stream | Nein |

### PhpInformation (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → phpinfo() Output, Admin-only | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | PendingChangesLogPage GET: keine Changes | 200, earliest = today |
| EP2 | PendingChangesLogPage GET: Changes vorhanden | 200, korrekte Datumswerte |
| EP3 | SiteLogsDownload GET | 200, Content-Type text/csv |
| EP4 | PhpInformation GET | 200, phpinfo()-Output |
| EP5 | PendingChangesLogData GET | Smoke: 200 |

---

## Empfohlene Strategie

**ISTQB B für PendingChangesLogPage-Branches, Smoke für Rest.** Neue Klasse `LogsMonitoringIntegrationTest extends MysqlTestCase`. Admin-Auth. SiteLogsDownload: Content-Type-Validierung.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
