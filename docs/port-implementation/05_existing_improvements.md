<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 05 — Bestandsverbesserung der 41 substanziellen Tests

Diese Tests haben bereits echte Testlogik mit Assertions jenseits von `class_exists()`.
Sie werden in Phase P2 auf Verbesserungspotenzial geprüft und ggf. erweitert.

---

## §1 Redirect-Tests (29 Tests, 91 Methoden)

**Status:** `pending`

Die 29 Redirect-Tests sind die **Mustervorlage** für alle neuen Tests.
Verbesserungspotenzial:

1. **Fehlende Edge Cases:** Einige Redirect-Tests testen nur 3 Pfade
   (Success, NoRecord, NoTree). Prüfen, ob weitere Pfade existieren:
   - Missing Parameter (kein `pid`/`famid` im Request)
   - Leerer String als Parameter
   - Record existiert, aber `canShow()` → false (Privacy)

2. **Pattern-Konsistenz:** Alle 29 Tests sollten dasselbe Pattern verwenden.
   Prüfen, ob einzelne Tests abweichen.

**Dateien:**
`RedirectAncestryPhpTest.php`, `RedirectBranchesPhpTest.php`,
`RedirectCalendarPhpTest.php`, `RedirectCompactPhpTest.php`,
`RedirectDescendancyPhpTest.php`, `RedirectFamilyBookPhpTest.php`,
`RedirectFamilyPhpTest.php`, `RedirectFanChartPhpTest.php`,
`RedirectGedRecordPhpTest.php`, `RedirectHourGlassPhpTest.php`,
`RedirectIndividualPhpTest.php`, `RedirectInteractivetreePhpTest.php`,
`RedirectLifespanPhpTest.php`, `RedirectMediaViewerPhpTest.php`,
`RedirectModulePhpTest.php`, `RedirectNotePhpTest.php`,
`RedirectPedigreePhpTest.php`, `RedirectRelationshipPhpTest.php`,
`RedirectReportEnginePhpTest.php`, `RedirectRepositoryPhpTest.php`,
`RedirectSourcePhpTest.php`, `RedirectStatisticsPhpTest.php`,
`RedirectTimeLinePhpTest.php`, und 6 weitere.

## §2 UpgradeWizardStepTest (11 Methoden, 251 LoC)

**Status:** `pending`

Verbesserungspotenzial:

1. **`testStepPendingExist`** ist de facto ein Integrationstest:
   - Verwendet `Auth::login()` und DB-State
   - **Empfehlung:** Entweder nach Layer 3 verschieben oder als bewusste
     Ausnahme mit Kommentar dokumentieren

2. **Reale Services:** `GedcomExportService`, `MaintenanceModeService`,
   `PendingChangesService` sind immer real instanziiert. Prüfen, ob Mock
   möglich und sinnvoll ist (Analyse §B.4: "Mock an der I/O-Grenze, real
   für zustandslose Logik" — dieser Ansatz ist vertretbar).

3. **UpgradeService-Mocking:** 6 von 11 Tests mocken `UpgradeService` korrekt.
   Die 4 Tests, die ihn real instanziieren, rufen ihn nie auf (Dummy-Rolle).
   Hier `self::createStub()` statt realer Instanz verwenden.

## §3 LoginPageTest (2 Methoden)

**Status:** `pending`

**Pattern-Inkonsistenz:** Verwendet reale `TreeService` statt Mock.

**Maßnahme:**
- `TreeService` durch `$this->createMock(TreeService::class)` ersetzen
- `expects($this->once())->method('all')` hinzufügen
- Ggf. zusätzliche Tests:
  - `testPageWithNoTrees()` — leere Collection
  - `testPageWithMultipleTrees()` — mehrere Trees

## §4 BroadcastPageTest (2 Methoden)

**Status:** `pending`

**Fehlende Negativ-Tests:** Nur 2 Methoden bei voraussichtlich mehr Codepfaden.

**Maßnahme prüfen:**
- Admin-Only-Check: Was passiert ohne Admin-Rechte? → `HttpAccessDeniedException`
- Leere User-Liste: Keine Empfänger verfügbar
- Verschiedene Broadcast-Typen (wenn der Handler Typen unterstützt)

## §5 SelectLanguageTest (2 Methoden)

**Status:** `pending`

**Kein Pattern-Bruch** (Analyse §B.3), aber Verbesserungspotenzial:

- `testSelectLanguageForGuest()` prüft nur Statuscode
- `SelectThemeTest` verifiziert dagegen `setPreference()`-Interaktion mit `expects()`
- **Option:** Für Konsistenz mit SelectThemeTest auch hier Verhaltensverifikation
  hinzufügen — oder bewusst als Zustandsverifikation belassen und dokumentieren

## §6 PingTest (3 Methoden)

**Status:** `pending` — prüfen auf Vollständigkeit

Bereits substanziell. Prüfen, ob alle Codepfade abgedeckt sind.

## §7 ModuleActionTest (4 Methoden)

**Status:** `pending` — prüfen auf Vollständigkeit

Bereits substanziell mit Fake-Pattern (anonyme Klasse). Mustervorlage.
Prüfen, ob Edge Cases fehlen.

## §8 DeleteUserTest (3 Methoden)

**Status:** `pending` — prüfen auf Vollständigkeit

Bereits substanziell. Prüfen:
- Selbst-Löschung (Admin löscht eigenen Account)
- Letzter Admin löscht sich selbst → Fehlerbehandlung?

---

## Zusammenfassung

| § | Bereich | Tests | Methoden | Hauptmaßnahme |
|---|---------|-------|----------|---------------|
| 1 | Redirect-Tests | 29 | 91 | Edge Cases, Pattern-Konsistenz |
| 2 | UpgradeWizardStepTest | 1 | 11 | testStepPendingExist → L3, Stubs statt realer Dummies |
| 3 | LoginPageTest | 1 | 2 | TreeService Mock, Negativ-Tests |
| 4 | BroadcastPageTest | 1 | 2 | Negativ-Tests (Admin-Check, leere Liste) |
| 5 | SelectLanguageTest | 1 | 2 | Konsistenz mit SelectThemeTest prüfen |
| 6 | PingTest | 1 | 3 | Vollständigkeitsprüfung |
| 7 | ModuleActionTest | 1 | 4 | Vollständigkeitsprüfung |
| 8 | DeleteUserTest | 1 | 3 | Edge Cases (Selbst-Löschung) |
| **Gesamt** | | **36** | **118** | |
