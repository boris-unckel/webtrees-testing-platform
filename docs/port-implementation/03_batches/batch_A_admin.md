<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch A — Administration

**Priorität:** 3+4 (Dependencies + CRAP-Score)
**Feature-IDs:** A01–A11

---

## Portierbare Tests

### Handler-Tests (Template 1)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `CreateTreePageTest.php` | `CreateTreePage` | `TreeService` | `completed` | A01 |
| 2 | `CreateTreeActionTest.php` | `CreateTreeAction` | `TreeService` | `completed` | A01 |
| 3 | `DeleteTreeActionTest.php` | `DeleteTreeAction` | `TreeService` | `completed` | A01 |
| 4 | `TreePageDefaultEditTest.php` | `TreePageDefaultEdit` | `TreeService` | `completed` | A01 |
| 5 | `TreePreferencesPageTest.php` | `TreePreferencesPage` | `TreeService` | `completed` | A04 |
| 6 | `TreePreferencesActionTest.php` | `TreePreferencesAction` | `TreeService` | `completed` | A04 |
| 7 | `SitePreferencesPageTest.php` | `SitePreferencesPage` | `ModuleService` | `completed` | A06 |
| 8 | `SitePreferencesActionTest.php` | `SitePreferencesAction` | — | `completed` | A06, ggf. T2 |
| 9 | `DataFixPageTest.php` | `DataFixPage` | `DataFixService` | `completed` | A09 |
| 10 | `DataFixActionTest.php` | `DataFixAction` | `DataFixService` | `skipped` | A09, Testdatei existiert nicht |
| 11 | `SiteLogsPageTest.php` | `SiteLogsPage` | — | `completed` | A10 |
| 12 | `SiteLogsDownloadTest.php` | `SiteLogsDownload` | — | `completed` | A10 |
| 13 | `SiteLogsDeleteTest.php` | `SiteLogsDelete` | `SiteLogsService` | `completed` | A10 |

### Service-Tests (L2-fähige Methoden)

| # | Test-Datei | SUT-Klasse | Template | Status | Bemerkung |
|---|-----------|------------|----------|--------|-----------|
| 14 | `RomanNumeralsServiceTest.php` | `RomanNumeralsService` | T2 | `completed` | 4 Testmethoden: numberToRomanNumerals, romanNumeralsToNumber, Edge Cases |

### Bestehende substanzielle Tests (Verbesserung in P2)

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `UpgradeWizardStepTest.php` | 11 | A11: testStepPendingExist ist Integrationstest → ggf. nach L3 |
| `BroadcastPageTest.php` | 2 | A11: fehlende Negativ-Tests, mehr Codepfade |
| `MasqueradeTest.php` | 3 | A11: bereits in batch_P gelistet |
| `ModuleServiceTest.php` | 5 | A05: substanziell |
| `UserServiceTest.php` | 13 | A07: substanziell |
| `ManageMediaDataTest.php` | 3 | A08: substanziell, nicht in L3/L4 abgedeckt |

## Ausgeschlossen (Layer 3)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| A01 (TreeService CRUD) | Stammbaum create/delete/all/find | Direkte DB-Operationen |

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
ls tests/app/Http/RequestHandlers/*Tree*Test.php tests/app/Http/RequestHandlers/*Site*Test.php
ls tests/app/Http/RequestHandlers/*DataFix*Test.php tests/app/Http/RequestHandlers/*Log*Test.php
ls tests/app/Http/RequestHandlers/*Broadcast*Test.php tests/app/Http/RequestHandlers/*Upgrade*Test.php
```

## Statistik

- Portierbar: ~14
- Ausgeschlossen: 1 Feature-ID (TreeService CRUD)
- Ship as-is: RomanNumeralsServiceTest (4 Tests)
- Bereits substanziell: 6 Tests (Verbesserung in P2)
