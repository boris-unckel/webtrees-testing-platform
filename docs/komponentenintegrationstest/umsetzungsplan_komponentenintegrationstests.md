<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Umsetzungsplan: Komponentenintegrationstests (L3 PHPUnit)

**Erstellt:** 2026-04-12
**Basis:** [`testcov_komponentenintegration_systemtests_delta.md`](../testcov_komponentenintegration_systemtests_delta.md)
**Workflow:** [`wf_code-to-test_guide.md`](../wf_code-to-test_guide.md)
**Übergreifende Konzepte:** [`uebergreifende_konzepte_l3.md`](uebergreifende_konzepte_l3.md)

---

## 1 Überblick

**31 Features** ohne L3-Abdeckung, identifiziert in der Delta-Analyse.

**Schritte pro Feature** (-> wf_code-to-test_guide.md Abschnitt 2):

| Kürzel | Schritt | Beschreibung |
|---|---|---|
| P1 | Konsistenzcheck | SUT lesen, aktuellen Test-Code prüfen, Abgleich mit Detailkonzept |
| P2 | Soll-Design | EP/BVA-Matrix finalisieren, Testmethoden-Namen, Fixture-Bedarf |
| P3 | Test-Coding | Testmethoden schreiben, DataProvider implementieren |
| P4 | Ausführung + Fixing | Einzeltest isoliert ausführen, Fehler im Testcode beheben |
| P5 | Dokumentation | **Je Feature einzeln** (nicht erst am Gesamtabschluss): `tds_coverage_ref.md` (L3-Spalte), `tds_conditions_ref.md` (Teststufe), `tp_ratchet_spec.md` (Endekriterien Teststufe 2), `tds_methodik_spec.md` (Verfahren) |

**Gesamtabschluss** nach allen Features: Voll-Lauf (`make test-integration`), Ratchet-Update,
Dokumenten-Konsistenzprüfung (-> wf_test-iteration_guide.md Abschnitt 10).

---

## 2 Zusammenfassung

| # | ID | Feature | SUT-Klasse(n) | Teststrategie | Aufwand | L3-Testklasse (Vorschlag) |
|---|---|---|---|---|---|---|
| 1 | M03 | Client-IP-Ermittlung (Proxy-Trust) | `ClientIp` | Spec-C | Niedrig | `ClientIpMiddlewareIntegrationTest` |
| 2 | M06 | Session-Initialisierung | `UseSession` | Spec-C | Mittel | `UseSessionMiddlewareIntegrationTest` |
| 3 | M07 | Datenbank-Verbindung | `UseDatabase` | EP | Mittel | `UseDatabaseMiddlewareIntegrationTest` |
| 4 | M08 | Datenbank-Schema-Migration | `UpdateDatabaseSchema` | Smoke | Niedrig | `UpdateDatabaseSchemaMiddlewareIntegrationTest` |
| 5 | M09 | Base-URL-Ermittlung | `BaseUrl` | Spec-C | Mittel | `BaseUrlMiddlewareIntegrationTest` |
| 6 | M10 | Routen-Laden | `LoadRoutes` | Smoke | Niedrig | `LoadRoutesMiddlewareIntegrationTest` |
| 7 | M11 | URL-Routing | `Router` | Spec-C | Hoch | `RouterMiddlewareIntegrationTest` |
| 8 | M12 | Request-Handler-Dispatch | `RequestHandler` | Spec-C | Niedrig | `RequestHandlerMiddlewareIntegrationTest` |
| 9 | M13 | Sprachauswahl | `UseLanguage` | EP | Mittel | `UseLanguageMiddlewareIntegrationTest` |
| 10 | M14 | Theme-Auswahl | `UseTheme` | EP | Niedrig | `UseThemeMiddlewareIntegrationTest` |
| 11 | M15 | PHP-Error-zu-Exception-Konvertierung | `ErrorHandler` | Spec-C | Mittel | `ErrorHandlerMiddlewareIntegrationTest` |
| 12 | M16 | Exception-Handling & Error-Pages | `HandleExceptions` | Spec-C | Hoch | `HandleExceptionsMiddlewareIntegrationTest` |
| 13 | M17 | Debug-Logger (SQL/Perf) | `DebugLogger` | EP | Mittel | `DebugLoggerMiddlewareIntegrationTest` |
| 14 | M18 | Housekeeping (Thumbnails/Logs) | `DoHousekeeping` | Spec-C | Niedrig | `DoHousekeepingMiddlewareIntegrationTest` |
| 15 | M19 | Response-Kompression | `CompressResponse` | EP | Mittel | `CompressResponseMiddlewareIntegrationTest` |
| 16 | M20 | Content-Length-Header | `ContentLength` | Smoke | Niedrig | `ContentLengthMiddlewareIntegrationTest` |
| 17 | M21 | Config-Ini-Lesen | `ReadConfigIni` | Spec-C | Mittel | `ReadConfigIniMiddlewareIntegrationTest` |
| 18 | M23 | Update-Prüfung | `CheckForNewVersion` | Smoke | Niedrig | `CheckForNewVersionMiddlewareIntegrationTest` |
| 19 | M25 | GEDCOM-Tag-Registrierung | `RegisterGedcomTags` | Smoke | Niedrig | `RegisterGedcomTagsMiddlewareIntegrationTest` |
| 20 | M26 | Modul-Bootstrap | `BootModules` | Smoke | Niedrig | `BootModulesMiddlewareIntegrationTest` |
| 21 | M27 | DB-Transaktion mit Retry | `UseTransaction` | Spec-C | Mittel | `UseTransactionMiddlewareIntegrationTest` |
| 22 | M28 | Response-Emittierung | `EmitResponse` | Spec-C | Hoch | `EmitResponseMiddlewareIntegrationTest` |
| 23 | G31 | GEDCOM-Import via CLI | `TreeImport` | EP | Hoch | `TreeImportCommandIntegrationTest` |
| 24 | P42 | CLI Benutzer-Listing | `UserList` | Spec-C | Niedrig | `UserListCommandIntegrationTest` |
| 25 | A12 | CLI Wartungsmodus aktivieren | `SiteOffline` | Smoke | Niedrig | `SiteOfflineCommandIntegrationTest` |
| 26 | A13 | CLI Wartungsmodus deaktivieren | `SiteOnline` | Smoke | Niedrig | `SiteOnlineCommandIntegrationTest` |
| 27 | A14 | CLI initialer Config-Setup | `ConfigIni` | Spec-C | Hoch | `ConfigIniCommandIntegrationTest` |
| 28 | A15 | CLI Übersetzung kompilieren | `CompilePoFiles` | Spec-C | Mittel | `CompilePoFilesCommandIntegrationTest` |
| 29 | A16 | CLI Baum-Listing | `TreeList` | Spec-C | Niedrig | `TreeListCommandIntegrationTest` |
| 30 | K01 | Kontaktformular | `ContactPage`, `ContactAction` | EP | Mittel | `ContactFormIntegrationTest` |
| 31 | K02 | Benutzer-Nachrichten | `MessagePage`, `MessageAction`, `MessageSelect` | EP | Mittel | `UserMessageIntegrationTest` |
| 32 | A08 | Medienverwaltung Admin | `AdminMediaFileDownload`, `FixLevel0Media*`, `ManageMedia*` | EP | Hoch | `AdminMediaManagementIntegrationTest` |
| 33 | S53 | Legacy-URL-Weiterleitungen | ~27 `Redirect*Php`-Handler | Batch-Smoke + EP | Mittel | `LegacyUrlRedirectIntegrationTest` |

**Aufwandsverteilung:** 12x Niedrig, 13x Mittel, 6x Hoch

---

## 3 Feature-Details

### 3.1 Middleware (M-Domäne) — 20 Features

#### M03: Client-IP-Ermittlung (Proxy-Trust)

**SUT:** `app/Http/Middleware/ClientIp.php` | **Priorität:** Mittel
**Teststrategie:** Spec-C — XFF-Header-Varianten (leer, CSV, null)
**Aufwand:** Niedrig | **Testklasse:** `ClientIpMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M03_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M06: Session-Initialisierung

**SUT:** `app/Http/Middleware/UseSession.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — Session-Status, Masquerade-Check, Activity-Timestamp
**Aufwand:** Mittel | **Testklasse:** `UseSessionMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M06_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M07: Datenbank-Verbindung

**SUT:** `app/Http/Middleware/UseDatabase.php` | **Priorität:** Hoch
**Teststrategie:** EP — DB-Treiber-Varianten, Parameter-Kombinationen
**Aufwand:** Mittel | **Testklasse:** `UseDatabaseMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M07_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M08: Datenbank-Schema-Migration

**SUT:** `app/Http/Middleware/UpdateDatabaseSchema.php` | **Priorität:** Hoch
**Teststrategie:** Smoke — MigrationService per DI gemockt, process()-Aufruf verifiziert
**Aufwand:** Niedrig | **Testklasse:** `UpdateDatabaseSchemaMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M08_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M09: Base-URL-Ermittlung

**SUT:** `app/Http/Middleware/BaseUrl.php` | **Priorität:** Mittel
**Teststrategie:** Spec-C — base_url leer (Auto-Detection) vs. gesetzt, URL-Parsing-Varianten
**Aufwand:** Mittel | **Testklasse:** `BaseUrlMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M09_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M10: Routen-Laden

**SUT:** `app/Http/Middleware/LoadRoutes.php` | **Priorität:** Mittel
**Teststrategie:** Smoke — DI-Dependencies mocken, RouterContainer-Initialisierung verifizieren
**Aufwand:** Niedrig | **Testklasse:** `LoadRoutesMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M10_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M11: URL-Routing

**SUT:** `app/Http/Middleware/Router.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — URL-Rewrite, Route-Matching, 405/406-Fehler, Tree-Lookup
**Aufwand:** Hoch | **Testklasse:** `RouterMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M11_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M12: Request-Handler-Dispatch

**SUT:** `app/Http/Middleware/RequestHandler.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — Handler als String vs. Objekt
**Aufwand:** Niedrig | **Testklasse:** `RequestHandlerMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M12_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M13: Sprachauswahl

**SUT:** `app/Http/Middleware/UseLanguage.php` | **Priorität:** Mittel
**Teststrategie:** EP — Prioritätsreihenfolge (Session → Browser → Fallback)
**Aufwand:** Mittel | **Testklasse:** `UseLanguageMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M13_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M14: Theme-Auswahl

**SUT:** `app/Http/Middleware/UseTheme.php` | **Priorität:** Niedrig
**Teststrategie:** EP — Prioritätsreihenfolge (Session → Site-Default → WebtreesTheme)
**Aufwand:** Niedrig | **Testklasse:** `UseThemeMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M14_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M15: PHP-Error-zu-Exception-Konvertierung

**SUT:** `app/Http/Middleware/ErrorHandler.php` | **Priorität:** Mittel
**Teststrategie:** Spec-C — Error-Reporting aktiv → Exception, unterdrückt → ignoriert
**Aufwand:** Mittel | **Testklasse:** `ErrorHandlerMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M15_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M16: Exception-Handling & Error-Page-Rendering

**SUT:** `app/Http/Middleware/HandleExceptions.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — HttpException, FilesystemException, Throwable, AJAX-Fallback
**Aufwand:** Hoch | **Testklasse:** `HandleExceptionsMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M16_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M17: Debug-Logger (SQL/Perf)

**SUT:** `app/Http/Middleware/DebugLogger.php` | **Priorität:** Niedrig
**Teststrategie:** EP — debug=false (Skip), debug=true (0/N/1000+ Queries)
**Aufwand:** Mittel | **Testklasse:** `DebugLoggerMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M17_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M18: Housekeeping (Thumbnails/Logs/Temp)

**SUT:** `app/Http/Middleware/DoHousekeeping.php` | **Priorität:** Niedrig
**Teststrategie:** Spec-C — GET + Probability-Trigger vs. POST (Skip)
**Aufwand:** Niedrig | **Testklasse:** `DoHousekeepingMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M18_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M19: Response-Kompression

**SUT:** `app/Http/Middleware/CompressResponse.php` | **Priorität:** Niedrig
**Teststrategie:** EP — Content-Type-Klassen, Accept-Encoding-Varianten
**Aufwand:** Mittel | **Testklasse:** `CompressResponseMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M19_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M20: Content-Length-Header

**SUT:** `app/Http/Middleware/ContentLength.php` | **Priorität:** Niedrig
**Teststrategie:** Smoke — Header bereits vorhanden, Body-Size null, Body-Size bekannt
**Aufwand:** Niedrig | **Testklasse:** `ContentLengthMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M20_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M21: Config-Ini-Lesen

**SUT:** `app/Http/Middleware/ReadConfigIni.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — Config vorhanden (Attribute setzen), Config fehlt (SetupWizard)
**Aufwand:** Mittel | **Testklasse:** `ReadConfigIniMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M21_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M23: Update-Prüfung

**SUT:** `app/Http/Middleware/CheckForNewVersion.php` | **Priorität:** Niedrig
**Teststrategie:** Smoke — GET + kein XHR → Check, POST → Skip, AJAX → Skip
**Aufwand:** Niedrig | **Testklasse:** `CheckForNewVersionMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M23_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M25: GEDCOM-Tag-Registrierung

**SUT:** `app/Http/Middleware/RegisterGedcomTags.php` | **Priorität:** Mittel
**Teststrategie:** Smoke — registerTags()-Aufruf verifizieren
**Aufwand:** Niedrig | **Testklasse:** `RegisterGedcomTagsMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M25_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M26: Modul-Bootstrap

**SUT:** `app/Http/Middleware/BootModules.php` | **Priorität:** Mittel
**Teststrategie:** Smoke — bootModules()-Aufruf verifizieren
**Aufwand:** Niedrig | **Testklasse:** `BootModulesMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M26_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M27: DB-Transaktion mit Retry

**SUT:** `app/Http/Middleware/UseTransaction.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — Commit bei Erfolg, Rollback bei Exception
**Aufwand:** Mittel | **Testklasse:** `UseTransactionMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M27_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### M28: Response-Emittierung

**SUT:** `app/Http/Middleware/EmitResponse.php` | **Priorität:** Niedrig
**Teststrategie:** Spec-C — Headers-Prüfung, Body-Chunks, Connection-Handling
**Aufwand:** Hoch | **Testklasse:** `EmitResponseMiddlewareIntegrationTest`
**Konzept:** -> `testspezi/M28_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

### 3.2 CLI-Commands — 7 Features

#### G31: GEDCOM-Import via CLI

**SUT:** `app/Cli/Commands/TreeImport.php` | **Priorität:** Hoch
**Teststrategie:** EP — gültiger/ungültiger Baum, gültige/ungültige Datei, Encoding, keep-media
**Aufwand:** Hoch | **Testklasse:** `TreeImportCommandIntegrationTest`
**Konzept:** -> `testspezi/G31_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### P42: CLI Benutzer-Listing

**SUT:** `app/Cli/Commands/UserList.php` | **Priorität:** Niedrig
**Teststrategie:** Spec-C — Format-Varianten (table/json/csv), Spezialzeichen
**Aufwand:** Niedrig | **Testklasse:** `UserListCommandIntegrationTest`
**Konzept:** -> `testspezi/P42_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A12: CLI Wartungsmodus aktivieren

**SUT:** `app/Cli/Commands/SiteOffline.php` | **Priorität:** Mittel
**Teststrategie:** Smoke — Message-Varianten, Fehlerfall
**Aufwand:** Niedrig | **Testklasse:** `SiteOfflineCommandIntegrationTest`
**Konzept:** -> `testspezi/A12_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A13: CLI Wartungsmodus deaktivieren

**SUT:** `app/Cli/Commands/SiteOnline.php` | **Priorität:** Niedrig
**Teststrategie:** Smoke — Idempotenz (bereits online), Datei-Löschung
**Aufwand:** Niedrig | **Testklasse:** `SiteOnlineCommandIntegrationTest`
**Konzept:** -> `testspezi/A13_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A14: CLI initialer Config-Setup

**SUT:** `app/Cli/Commands/ConfigIni.php` | **Priorität:** Hoch
**Teststrategie:** Spec-C — 13 Optionen, DB-Connect-Prüfung, Escaping
**Aufwand:** Hoch | **Testklasse:** `ConfigIniCommandIntegrationTest`
**Konzept:** -> `testspezi/A14_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A15: CLI Übersetzung kompilieren

**SUT:** `app/Cli/Commands/CompilePoFiles.php` | **Priorität:** Niedrig
**Teststrategie:** Spec-C — PO-Dateien vorhanden/fehlend, Schreibfehler
**Aufwand:** Mittel | **Testklasse:** `CompilePoFilesCommandIntegrationTest`
**Konzept:** -> `testspezi/A15_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A16: CLI Baum-Listing

**SUT:** `app/Cli/Commands/TreeList.php` | **Priorität:** Niedrig
**Teststrategie:** Spec-C — Format-Varianten (table/json/csv), imported-Flag
**Aufwand:** Niedrig | **Testklasse:** `TreeListCommandIntegrationTest`
**Konzept:** -> `testspezi/A16_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

### 3.3 Sonstige — 4 Features

#### K01: Kontaktformular

**SUT:** `ContactPage`, `ContactAction` | **Priorität:** Niedrig
**Teststrategie:** EP — Validierung (Pflichtfelder, E-Mail, CAPTCHA, Rate-Limit), Erfolg/Fehler
**Aufwand:** Mittel | **Testklasse:** `ContactFormIntegrationTest`
**Konzept:** -> `testspezi/K01_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### K02: Benutzer-Nachrichten

**SUT:** `MessagePage`, `MessageAction`, `MessageSelect` | **Priorität:** Niedrig
**Teststrategie:** EP — Contact-Method-Prüfung, Pflichtfelder, deliverMessage-Mock
**Aufwand:** Mittel | **Testklasse:** `UserMessageIntegrationTest`
**Konzept:** -> `testspezi/K02_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### A08: Medienverwaltung Admin

**SUT:** 5 Handler (`AdminMediaFileDownload`, `FixLevel0MediaPage/Action`, `ManageMediaPage/Action`)
**Priorität:** Niedrig
**Teststrategie:** EP — Path-Security, Formular-Validierung, FixLevel0-Logik
**Aufwand:** Hoch | **Testklasse:** `AdminMediaManagementIntegrationTest`
**Konzept:** -> `testspezi/A08_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

#### S53: Legacy-URL-Weiterleitungen

**SUT:** ~27 `Redirect*Php`-Handler | **Priorität:** Niedrig
**Teststrategie:** Batch-Smoke (DataProvider für 27 Handler) + EP für 3–4 komplexe Handler
**Aufwand:** Mittel | **Testklasse:** `LegacyUrlRedirectIntegrationTest`
**Konzept:** -> `testspezi/S53_kompintetest_spezi.md`

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |

---

## 4 Empfohlene Implementierungsreihenfolge

### Runde 1 — Hoch-Prio Middleware (Kern-Pipeline)

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 1 | M08 | Schema-Migration | Niedrig | Smoke, sehr gut testbar — schneller Einstieg |
| 2 | M12 | Request-Handler-Dispatch | Niedrig | Einfach, isoliert testbar |
| 3 | M20 | Content-Length-Header | Niedrig | Smoke, sehr gut testbar |
| 4 | M21 | Config-Ini-Lesen | Mittel | Hoch-Prio, zwei klare Branches |
| 5 | M07 | Datenbank-Verbindung | Mittel | Hoch-Prio, EP auf Parameter |
| 6 | M06 | Session-Initialisierung | Mittel | Hoch-Prio, mehrere Branches |
| 7 | M27 | DB-Transaktion mit Retry | Mittel | Hoch-Prio, Transaction-Pattern |

### Runde 2 — Hoch-Prio CLI + Routing

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 8 | A12 | Site Offline (CLI) | Niedrig | Schneller Win, einfacher Command |
| 9 | A13 | Site Online (CLI) | Niedrig | Schneller Win, Pendant zu A12 |
| 10 | M11 | URL-Routing | Hoch | Hoch-Prio, komplexeste Middleware |
| 11 | G31 | GEDCOM-Import (CLI) | Hoch | Hoch-Prio, umfangreiche EP-Matrix |
| 12 | A14 | Config-Setup (CLI) | Hoch | Hoch-Prio, 13 Optionen |

### Runde 3 — Mittel-Prio Middleware

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 13 | M03 | Client-IP | Niedrig | Mittel-Prio, gut testbar |
| 14 | M09 | Base-URL | Mittel | Mittel-Prio, URL-Parsing |
| 15 | M13 | Sprachauswahl | Mittel | Mittel-Prio, EP |
| 16 | M25 | GEDCOM-Tags | Niedrig | Mittel-Prio, Smoke |
| 17 | M26 | Modul-Bootstrap | Niedrig | Mittel-Prio, Smoke |
| 18 | M15 | Error-Handler | Mittel | Mittel-Prio, globale Funktionen |

### Runde 4 — Mittel-Prio CLI + Kommunikation

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 19 | P42 | User-Listing (CLI) | Niedrig | Niedrig-Prio, aber schnell |
| 20 | A16 | Tree-Listing (CLI) | Niedrig | Niedrig-Prio, identisch zu P42 |
| 21 | A15 | Compile PO (CLI) | Mittel | Niedrig-Prio, File-I/O |
| 22 | K01 | Kontaktformular | Mittel | Niedrig-Prio, EP |
| 23 | K02 | Benutzer-Nachrichten | Mittel | Niedrig-Prio, EP |

### Runde 5 — Niedrig-Prio Middleware

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 24 | M14 | Theme-Auswahl | Niedrig | Niedrig-Prio, EP |
| 25 | M10 | Routen-Laden | Niedrig | Niedrig-Prio, Smoke |
| 26 | M17 | Debug-Logger | Mittel | Niedrig-Prio, EP |
| 27 | M18 | Housekeeping | Niedrig | Niedrig-Prio, Probability-Check |
| 28 | M23 | Version-Check | Niedrig | Niedrig-Prio, Smoke |

### Runde 6 — Komplex + L4-relevant

| # | ID | Feature | Aufwand | Begründung |
|---|---|---|---|---|
| 29 | M16 | Exception-Handling | Hoch | Hoch-Prio, aber komplex |
| 30 | M19 | Response-Kompression | Mittel | Niedrig-Prio, EP |
| 31 | M28 | Response-Emittierung | Hoch | Niedrig-Prio, globale Funktionen |
| 32 | A08 | Media-Admin | Hoch | Niedrig-Prio, 5 Handler |
| 33 | S53 | Legacy-Redirects | Mittel | Niedrig-Prio, Batch-Test |
