<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Upstream-Contribution — Layer-2-Komponentests mit Test Doubles

> **Separates Vorhaben**, unabhängig von diesem Repo.
> Ziel: PR an `fisharebest/webtrees` — Testabdeckung im Core verbessern.

Referenzen: [Feature-Matrizen](tds_conditions_ref.md) | [Abdeckungsmatrix](tds_coverage_ref.md) | [Portierungs-Plan](port-implementation/00_plan.md)

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
| Testmethoden | 3296 | 3774 | +478 |
| Assertions | 150475 | 154609 | +4134 |
| Geänderte Dateien | — | 264 | — |
| Eingefügter Code | — | +14040 Zeilen | — |
| Neue Failures | — | 0 | — |
| Bug-Kandidaten | — | 0 | — |

### Batches

| Batch | Kategorie | Tests portiert | Status |
|---|---|---|---|
| `batch_SEC` | Sicherheit (Login, Logout, PasswordReset, Verify, Robots) | ~12 | abgeschlossen |
| `batch_P` | Datenschutz & Zugriff (UserEdit, AccountEdit, PendingChanges) | ~17 | abgeschlossen |
| `batch_S` | Suche & Navigation (AutoComplete, Search, Calendar, Help, Register) | ~37 | abgeschlossen |
| `batch_G` | GEDCOM Import/Export (Upload, EditMedia, Check, Export, Import) | ~12 | abgeschlossen |
| `batch_A` | Administration (CreateTree, Preferences, DataFix, SiteLogs) | ~10 | abgeschlossen |
| `batch_E` | Datenpflege (AddChild, EditFact, CreateNote/Source/Repo, Reorder) | ~51 | abgeschlossen |
| `batch_K` | Kommunikation (Contact, Message, Broadcast) | ~6 | abgeschlossen |
| `batch_U` | Utilities (SelectLanguage, SelectTheme, Ping, TomSelect) | ~23 | abgeschlossen |

## Test-Double-Konventionen

Die Tests folgen dem Maintainer-Anforderungsprofil (R1–R11 aus `port_analysis_strategy.md`):

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
| **T1** (Handler+Service) | SUT hat Service-Konstruktor-Dependencies | LoginAction, RobotsTxt, ContactAction |
| **T2** (Handler-Simple) | SUT hat keine Konstruktor-Dependencies | SiteRegistrationAction, HelpText, AdsTxt |
| **T3** (Handler+Registry) | SUT greift auf `Registry::*Factory()` zu | CopyFact, DeleteFact, IndividualPage |
| **T4** (Module+handle) | SUT ist ein Module mit `handle()` | Chart-/List-Module |

### Kritische Patterns

| Situation | Pattern |
|---|---|
| SUT ruft `$tree->createRecord()` auf | `Auth::login($user)` vor Handler-Aufruf (FK auf `wt_change.user_id`) |
| SUT rendert View mit Layout | `self::createStub()` für Factory (Layout ruft Factory mehrfach auf) |
| SUT ruft `response()` ohne Body auf | Assert `STATUS_NO_CONTENT` (204), nicht `STATUS_OK` (200) |
| SUT nutzt `Validator::parsedBody()->boolean()` | `(string) false === ''`, nicht `'0'` |
| SUT nutzt MySQL-spezifische Funktionen (LEAST, GREATEST) | Test als Layer-3-only ausschließen |

## Layer-2 vs. Layer-3 Abgrenzung

| Layer 2 (SQLite in-memory) | Layer 3 (MySQL im Container) |
|---|---|
| Handler-Routing, Response-Codes | GEDCOM-Import mit realen Dateien |
| Service-Aufrufe über Mocks verifizieren | DB-spezifische SQL-Funktionen (LEAST, GREATEST) |
| Validator-Extraktion aus Request | Transaktionale Pending-Changes-Workflows |
| Session/Auth-Zustandswechsel | E-Mail-Versand (MessageService Integration) |
| Registry-Factory-Injection | `importTree()` + Record-Manipulation |

Vollständige Ausschluss-Liste: `docs/port-implementation/04_exclusions.md`

## Bestandsverbesserungen (P2)

| Testdatei | Verbesserung | Status |
|---|---|---|
| `LoginPageTest` | TreeService gemockt, 2 neue Testmethoden (withTree, noTreeRedirect) | verbessert |
| `SelectLanguageTest` | Mock statt echtem User, Session-Verifikation | verbessert |
| `BroadcastPageTest` | Bereits adäquat — keine Änderung nötig | übersprungen |

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
| Redirect-Tests (29 Dateien) | ausstehend | Bestandsverbesserung §1 — optional |
| UpgradeWizardStepTest | ausstehend | Bestandsverbesserung §2 — optional |
| Upstream-Bug FamilyFactory | bekannt | TypeError bei Privat-Familien (betrifft PRIV_NONE/PRIV_USER) |

## Cross-Referenzen

| Dokument | Zweck |
|---|---|
| `docs/port-implementation/00_plan.md` | Master-Plan (Phasen P0–P4) |
| `docs/port-implementation/tasks/INDEX.md` | Tracking-Index mit Batch-Status |
| `docs/port-implementation/04_exclusions.md` | Layer-3-Ausschlüsse mit Begründung |
| `docs/port-implementation/02_prompts/` | 4 Prompt-Templates (T1–T4) |
| `docs/port_analysis_strategy.md` | Analyse-Ergebnis (Taxonomie, Patterns, Matrix) |
