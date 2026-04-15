<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Upstream-Contribution — Layer-2-Komponentests mit Test Doubles

> **Separates Vorhaben**, unabhängig von diesem Repo.
> Ziel: PR an `fisharebest/webtrees` — Testabdeckung im Core verbessern.

Referenzen: [Feature-Matrizen](tds_conditions_ref.md) | [Abdeckungsmatrix](tds_coverage_ref.md)

## Abgrenzung

| Aspekt | `webtrees-testing-platform/` (dieses Repo) | Upstream-Branch (`${WEBTREES_SOURCE}`) |
|---|---|---|
| **Ort** | `webtrees-testing-platform/` | `${WEBTREES_SOURCE}` (Fork unter `webtrees-upstream/webtrees`) |
| **Abhängigkeit** | Bindet `${WEBTREES_SOURCE}` nur lesend ein | Ändert webtrees-Code direkt (nur `tests/`) |
| **Zweck** | Eigene Testinfrastruktur (Container, OTel, Playwright) | Bestehende Stubs → Komponentests mit Test Doubles |
| **Zielgruppe** | Eigenbedarf (Regressionstests vor Updates) | Upstream-Community (PR) |
| **Testframework** | PHPUnit + Playwright (eigene Infra) | PHPUnit (webtrees-eigene Infra: `TestCase.php`, SQLite in-memory) |

## Erreichte Ergebnisse

### Kennzahlen

| Metrik | Vorher (Baseline) | Nachher | Delta |
|---|---|---|---|
| Testmethoden | 3283 | 3800 | +517 |
| Assertions | 150396 | 152711 | +2315 |
| Geänderte Dateien | — | 278 | — |
| Neue Failures | — | 0 | — |
| Bug-Kandidaten | — | 0 | — |

### Batches

| Batch | Kategorie | Completed | Skipped | Status |
|---|---|---|---|---|
| `batch_SEC` | Sicherheit (Login, Logout, PasswordReset, Verify, Robots, Middleware) | 11 | 1 | abgeschlossen |
| `batch_P` | Datenschutz & Zugriff (UserEdit, AccountEdit, PendingChanges) | 17 | 0 | abgeschlossen |
| `batch_S` | Suche & Navigation (AutoComplete, Search, Calendar, Help, Register) | 41 | 1 | abgeschlossen |
| `batch_G` | GEDCOM Import/Export (Upload, EditMedia, Check, Export, Import) | 9 | 2 | abgeschlossen |
| `batch_A` | Administration (CreateTree, Preferences, DataFix, SiteLogs) | 13 | 1 | abgeschlossen |
| `batch_E` | Datenpflege (AddChild, EditFact, CreateNote/Source/Repo, Reorder) | 46 | 5 | abgeschlossen |
| `batch_K` | Kommunikation (Contact, Message, Broadcast) | 6 | 0 | abgeschlossen |
| `batch_U` | Utilities (SelectLanguage, SelectTheme, Ping, TomSelect) | 1 | 0 | abgeschlossen |

## Test-Double-Konventionen

Die Tests folgen dem Maintainer-Anforderungsprofil (R1–R11):

### Mock vs. Stub

| Konstrukt | Verwendung | Beispiel |
|---|---|---|
| `$this->createMock()` + `expects()` | Services/Factories — verifiziere Aufrufe | `$user_service->expects(self::once())->method('findByUserName')` |
| `self::createStub()` | Domain-Objekte (Tree, Individual, Family) — Verhalten konfigurieren | `$tree->method('name')->willReturn('test')` |
| `(new UserService())->create()` | Echter User nötig (Auth::login, setPreference, DB-FK) | Login-/Logout-Tests |
| `new GuestUser()` | Gast-Benutzer — Value Object, direkt instanziierbar | SelectLanguage, Logout |

### Template-Typen

| Template | Entscheidung | Dateien |
|---|---|---|
| **T1** (Handler+Service) | SUT hat Service-Konstruktor-Dependencies | LoginAction, RobotsTxt, ContactAction, SearchReplaceAction |
| **T2** (Handler-Simple) | SUT hat keine Konstruktor-Dependencies | SiteRegistrationAction, HelpText, AdsTxt |
| **T3** (Handler+Registry) | SUT greift auf `Registry::*Factory()` zu | CopyFact, DeleteFact, IndividualPage |
| **T4** (Module+handle) | SUT ist ein Module mit `handle()` | Chart-/List-Module |
| **Middleware** | PSR-15-Middleware: `$middleware->process($request, $handler)` | SecurityHeaders, PublicFiles, BadBotBlocker |
| **Service** | Zustandslose Logik: direkte Instanziierung ohne Mocks | GedcomService, GedcomExportService, RomanNumeralsService |

### Kritische Patterns

| Situation | Pattern |
|---|---|
| SUT ruft `$tree->createRecord()` auf | `Auth::login($user)` vor Handler-Aufruf (FK auf `wt_change.user_id`) |
| SUT rendert View mit Layout | `self::createStub()` für Factory (Layout ruft Factory mehrfach auf) |
| SUT ruft `response()` ohne Body auf | Assert `STATUS_NO_CONTENT` (204), nicht `STATUS_OK` (200) |
| SUT nutzt `Validator::parsedBody()->boolean()` | `(string) false === ''`, nicht `'0'` |
| SUT nutzt MySQL-spezifische Funktionen (LEAST, GREATEST) | Test als Layer-3-only ausschließen |
| SUT nutzt `DB::table()` / `DB::connect()` direkt | Layer-3-only (z. B. GedcomLoad, SetupWizard) |
| `Site::getPreference()` hat Defaults | Explizit `Site::setPreference()` im Test setzen (z. B. `USE_REGISTRATION_MODULE` Default `'1'`) |
| Middleware-Test: OTel fügt Attribute hinzu | Kein `->with($request)` auf Mock-Handler — Request wird durch OTel modifiziert |
| Middleware-Test: Server-Params (HTTP_USER_AGENT) | `Nyholm\Psr7\ServerRequest` direkt nutzen statt `TestCase::createRequest()` |
| `route()` kodiert Params in Pfad | Assert `calendar%2Fday` statt `view=day` — 'view' wird Teil der Route |

## Layer-2 vs. Layer-3 Abgrenzung

| Layer 2 (SQLite in-memory) | Layer 3 (MySQL im Container) |
|---|---|
| Handler-Routing, Response-Codes | GEDCOM-Import mit realen Dateien |
| Service-Aufrufe über Mocks verifizieren | DB-spezifische SQL-Funktionen (LEAST, GREATEST) |
| Validator-Extraktion aus Request | Transaktionale Pending-Changes-Workflows |
| Session/Auth-Zustandswechsel | E-Mail-Versand (MessageService Integration) |
| Registry-Factory-Injection | `importTree()` + Record-Manipulation |

## Bestandsverbesserungen (P2)

| Testdatei | Verbesserung | Status |
|---|---|---|
| `LoginPageTest` | TreeService gemockt, 2 neue Testmethoden (withTree, noTreeRedirect) | verbessert |
| `SelectLanguageTest` | Mock statt echtem User, Session-Verifikation | verbessert |
| `BroadcastPageTest` | Negativ-Test (HttpBadRequestException), MessageService gemockt | verbessert |
| `UpgradeWizardStepTest` | UpgradeService-Mocking, Exception-Pfade | verbessert |
| Redirect-Tests (29) | Edge Cases, Pattern-Konsistenz geprüft | verbessert |

## Redundanz und Rückbau

Durch die Portierung auf Test Doubles sind die Layer-2-Tests jetzt innerhalb
der webtrees-eigenen Test-Infrastruktur lauffähig (kein Container nötig).

**Nach Upstream-Akzeptanz:**
- Dieses Repo entfernt redundante Komponenten- und Komponentenintegrationstests
- Dieses Repo konzentriert sich auf: Container-Stack (Testumgebung), Playwright-Systemtests (Layer 4), Performance-Baselines (Layer 5), OTel-Tracing
- Die Feature-Matrizen bleiben als Referenz erhalten

## Offene Punkte

| Punkt | Status | Aktion |
|---|---|---|
| PR-Vorbereitung | ausstehend | Commit-Historie aufräumen, PR-Beschreibung verfassen |
| Upstream-Bug FamilyFactory | bekannt | TypeError bei Privat-Familien (betrifft PRIV_NONE/PRIV_USER) |

Alle 8 Batches abgeschlossen, 0 Pending-Stubs verbleiben. 10 Tests als Skipped markiert
(8× Testdatei fehlt im Upstream, 2× L3-only: SetupWizard, GedcomLoad).

