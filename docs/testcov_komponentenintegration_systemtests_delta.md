<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Delta-Analyse: Komponentenintegrationstest (L3) & Systemtest (L4)

**Datum:** 2026-04-12
**Quellen:** `tds_conditions_ref.md`, `tds_coverage_ref.md`, `layer3-integration/tests/`, `layer4-e2e/tests/`
**Scope:** 35 Feature-IDs aus drei Gruppen — keine Komponententests (L2) betrachtet.

---

## Zusammenfassung

| Gruppe | Feature-IDs | Anzahl | L3-Delta | L4-Delta |
|---|---|---|---|---|
| M-Domäne Stub-Aufarbeitung | M03, M06–M21, M23, M25–M28 | 20 | 20 offen | 18 offen, 2 n/a |
| CLI-Testlücken | G31, P42, A12–A16 | 7 | 7 offen | 7 n/a (CLI) |
| Sonstige Lücken | K01, K02, A08, S53, M15, M17 | 6 (4 netto*) | 4 offen | 2 offen, 2 bereits abgedeckt |

\* M15 und M17 sind in der M-Domäne-Gruppe bereits enthalten und werden dort gezählt.

**Gesamt-Delta (dedupliziert): 31 Features — 31× L3 offen, 22× L4 offen.**

---

## 1 M-Domäne Stub-Aufarbeitung (20 IDs)

Diese Middleware-Klassen haben im Fork nur `assertTrue(class_exists(...))` als L2-Stub.
Weder L3-Komponentenintegrationstests noch dedizierte L4-Systemtests existieren.

| # | ID | Feature | SUT-Klasse | Teststufe | Priorität | L3 (KIT) | L4 (Systemtest) | Empfehlung |
|---|---|---|---|---|---|---|---|---|
| 1 | M03 | Client-IP-Ermittlung (Proxy-Trust) | `ClientIp` | 2 | Mittel | — | — | L3: EP (XFF-Varianten) |
| 2 | M06 | Session-Initialisierung | `UseSession` | 2 | Hoch | — | — | L3: Spec-C (Cookie-Flags) |
| 3 | M07 | Datenbank-Verbindung | `UseDatabase` | 2 | Hoch | — | — | L3: Spec-C (Capsule-Init) |
| 4 | M08 | Datenbank-Schema-Migration | `UpdateDatabaseSchema` | 2 | Hoch | — | — | L3: Spec-C (Migration-Chain) |
| 5 | M09 | Base-URL-Ermittlung | `BaseUrl` | 2 | Mittel | — | — | L3: EP (Host/Scheme/Port-Varianten) |
| 6 | M10 | Routen-Laden | `LoadRoutes` | 2 | Mittel | — | — | L3: Smoke (Route-Count) |
| 7 | M11 | URL-Routing | `Router` | 2 | Hoch | — | — | L3: Spec-C (URL→Handler) |
| 8 | M12 | Request-Handler-Dispatch | `RequestHandler` | 2 | Hoch | — | — | L3: Spec-C (DI + handle()) |
| 9 | M13 | Sprachauswahl | `UseLanguage` | 2 | Mittel | — | — | L3: EP (Prio-Reihenfolge) |
| 10 | M14 | Theme-Auswahl | `UseTheme` | 2 | Niedrig | — | — | L3: Smoke (Default-Theme) |
| 11 | M15 | PHP-Error-zu-Exception-Konvertierung | `ErrorHandler` | 2 | Mittel | — | — | L3: Spec-C (Notice→Exception) |
| 12 | M16 | Exception-Handling & Error-Page-Rendering | `HandleExceptions` | 2, 3 | Hoch | — | — | L3: EP (403/404/500); L4: Spec-C (Error-Pages) |
| 13 | M17 | Debug-Logger (SQL/Perf) | `DebugLogger` | 2 | Niedrig | — | — | L3: Smoke (Header/Log-Flag) |
| 14 | M18 | Housekeeping (Thumbnails/Logs/Temp) | `DoHousekeeping` | 2 | Niedrig | — | — | L3: Smoke (Trigger-Check) |
| 15 | M19 | Response-Kompression | `CompressResponse` | 2 | Niedrig | — | — | L3: Spec-C (gzip-Encoding) |
| 16 | M20 | Content-Length-Header | `ContentLength` | 2 | Niedrig | — | — | L3: Smoke (Header-Setzung) |
| 17 | M21 | Config-Ini-Lesen | `ReadConfigIni` | 2, 3 | Hoch | — | — | L3: EP (vorhanden/fehlend/korrupt); L4: implizit via setup-lock |
| 18 | M23 | Update-Prüfung | `CheckForNewVersion` | 2 | Niedrig | — | — | L3: Smoke (GET-only-Guard) |
| 19 | M25 | GEDCOM-Tag-Registrierung | `RegisterGedcomTags` | 2 | Mittel | — | — | L3: Spec-C (Tag-Lookup) |
| 20 | M26 | Modul-Bootstrap | `BootModules` | 2 | Mittel | — | — | L3: Spec-C (boot()-Aufruf) |
| 21 | M27 | DB-Transaktion mit Retry | `UseTransaction` | 2 | Hoch | — | — | L3: EP (Commit/Deadlock-Retry) |
| 22 | M28 | Response-Emittierung | `EmitResponse` | 2 | Niedrig | — | — | L3: Smoke (Response-Chunks) |

### L4-Bewertung M-Domäne

Die Middleware-Features sind Infrastruktur-Code (Pipeline-Stufen). Direkte L4-Systemtests
sind nur für zwei Features sinnvoll:

| ID | L4-Relevanz | Begründung |
|---|---|---|
| M16 | **L4 empfohlen** | Error-Pages (403/404/500) sind nutzer-sichtbar → DOM-Assertions auf Error-Rendering |
| M21 | **L4 teilweise abgedeckt** | `setup-lock.spec.ts` / `wizard-setup.spec.ts` prüfen Setup-Wizard-Redirect indirekt |
| Alle anderen | **L4 nicht anwendbar** | Middleware-internes Verhalten ohne direkte UI-Auswirkung; L3 reicht aus |

---

## 2 CLI-Testlücken (7 IDs)

CLI-Commands sind nicht über HTTP/Browser erreichbar → L4-Systemtests (Playwright)
sind strukturell nicht anwendbar. Ausschließlich L3-Delta.

| # | ID | Feature | SUT-Klasse | Teststufe | Priorität | L3 (KIT) | L4 (Systemtest) | Geplante L3-Testklasse |
|---|---|---|---|---|---|---|---|---|
| 1 | G31 | GEDCOM-Import via CLI | `TreeImport` | 2 | Hoch | — | n/a (CLI) | `TreeImportCommandIntegrationTest` |
| 2 | P42 | CLI Benutzer-Listing | `UserList` | 2 | Niedrig | — | n/a (CLI) | `UserListCommandIntegrationTest` |
| 3 | A12 | CLI Wartungsmodus aktivieren | `SiteOffline` | 2 | Mittel | — | n/a (CLI) | `SiteOfflineCommandIntegrationTest` |
| 4 | A13 | CLI Wartungsmodus deaktivieren | `SiteOnline` | 2 | Niedrig | — | n/a (CLI) | `SiteOnlineCommandIntegrationTest` |
| 5 | A14 | CLI initialer Config-Setup | `ConfigIni` | 2 | Hoch | — | n/a (CLI) | `ConfigIniCommandIntegrationTest` |
| 6 | A15 | CLI Übersetzung kompilieren | `CompilePoFiles` | 2 | Niedrig | — | n/a (CLI) | `CompilePoFilesCommandIntegrationTest` |
| 7 | A16 | CLI Baum-Listing | `TreeList` | 2 | Niedrig | — | n/a (CLI) | `TreeListCommandIntegrationTest` |

### Referenz-Pattern

Bestehende CLI-Tests als Muster: `TreeExportCommandIntegrationTest.php`, `UserEditCommandIntegrationTest.php`.

---

## 3 Sonstige Lücken (4 netto-IDs, ohne M15/M17-Duplikate)

| # | ID | Feature | SUT-Klasse(n) | Teststufe | Priorität | L3 (KIT) | L4 (Systemtest) | Befund |
|---|---|---|---|---|---|---|---|---|
| 1 | K01 | Kontaktformular | `ContactPage`, `ContactAction` | 3 | Niedrig | — | `contact-form.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | **L4 OK, L3 offen** |
| 2 | K02 | Benutzer-Nachrichten | `MessagePage`, `MessageAction`, `MessageSelect` | 3 | Niedrig | — | `user-messages.spec.ts` [Spec-C] ✅ *(3 Tests)* | **L4 OK, L3 offen** |
| 3 | A08 | Medienverwaltung Admin | `AdminMediaFileDownload`, `FixLevel0MediaPage/Action`, `ManageMediaPage/Action` | 2, 3 | Niedrig | — | — | **L3 + L4 offen** |
| 4 | S53 | Legacy-URL-Weiterleitungen | ~27 `Redirect*`-Handler | 3 | Niedrig | — | — | **L3 + L4 offen** |

### Detailbewertung

**K01 / K02 (Kommunikation):** L4-Tests existieren und sind spezifikationsbasiert (Spec-C).
L3-Komponentenintegrationstests fehlen. Da die Features nur Teststufe 3 haben und kein
SMTP im Test-Stack läuft, ist L3 für die E-Mail-Versand-Logik eingeschränkt sinnvoll.
L3-Delta besteht, aber mit niedriger Priorität.

**A08 (Medienverwaltung Admin):** Komplett ungetestet. Teststufe 2 + 3 vorgesehen.
L3-Tests für Admin-Media-Operationen (Download, Thumbnail, FixLevel0, ManageMedia) und
L4-Tests für Admin-UI empfohlen. Hinweis: `ManageMediaDataIntegrationTest.php` deckt
S49 (API-Endpoint) ab, nicht A08 (Admin-Seite).

**S53 (Legacy-URL-Weiterleitungen):** Komplett ungetestet. Teststufe 3 vorgesehen.
~27 Redirect-Handler prüfbar als L4 API-Only-Pattern (HTTP 301/302 statt 404).
L3 wäre ebenfalls möglich (Handler-Unit-Tests für Redirect-Logik).

---

## 4 Priorisierte Übersicht — offene L3-Features

Sortiert nach Priorität (Hoch → Mittel → Niedrig), dann nach ID.

| Prio | IDs | Anzahl |
|---|---|---|
| **Hoch** | M06, M07, M08, M11, M12, M16, M21, M27, G31, A14 | 10 |
| **Mittel** | M03, M09, M13, M15, M25, M26, A12 | 7 |
| **Niedrig** | M10, M14, M17, M18, M19, M20, M23, M28, P42, A13, A15, A16, K01, K02, A08, S53 | 16 |

---

## 5 Priorisierte Übersicht — offene L4-Features

Nur Features, bei denen L4 sinnvoll und noch offen ist.

| Prio | IDs | Anzahl | Pattern |
|---|---|---|---|
| **Hoch** | M16 | 1 | Theme-Loop (Error-Pages) |
| **Niedrig** | A08 | 1 | Admin-Only (Media-Admin) |
| **Niedrig** | S53 | 1 | API-Only (Redirect 301/302) |

K01 und K02 sind bereits L4-abgedeckt. M21 ist L4-teilweise abgedeckt (implizit via Setup-Specs).

---

## 6 Empfohlene Iterationsreihenfolge

### L3 — Komponentenintegrationstest

| Runde | Features | Begründung |
|---|---|---|
| R1 | M06, M07, M08, M11, M12, M21, M27 | Hoch-Prio Middleware — Kern-Pipeline |
| R2 | G31, A14 | Hoch-Prio CLI — Import + Config |
| R3 | M03, M09, M13, M16, M25, M26 | Mittel-Prio Middleware |
| R4 | M15, A12 | Mittel-Prio Rest |
| R5 | A08, S53 | Niedrig-Prio, aber L4-relevant |
| R6 | M10, M14, M17, M18, M19, M20, M23, M28, P42, A13, A15, A16, K01, K02 | Niedrig-Prio Rest |

### L4 — Systemtest

| Runde | Features | Pattern |
|---|---|---|
| R1 | M16 | Theme-Loop: Error-Pages (403/404/500) pro Theme |
| R2 | S53 | API-Only: ~27 Legacy-Redirects (301/302-Prüfung) |
| R3 | A08 | Admin-Only: Media-Admin-Seiten |
