<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# improve-layer3 — Statustracker

Baseline: 79 Sweep-Treffer (Stand der Baseline: 2026-05-23). Quelle: `artifacts/layer3/silent-tests-sweep.txt`.

## Kategorien

| Kat | Bedeutung |
|---|---|
| `A1` | `markTestSkipped` mit dokumentiertem Upstream-Bug — verhalts-blind |
| `A2` | `markTestSkipped` mit Fixture-/Datenbedingung |
| `B`  | `assertTrue(class_exists(...))` — Smoke-Test ohne Verhaltens-Prüfung |
| `C`  | `assertTrue(true)` — "kein Exception = Erfolg", keine Property |
| `D`  | `addToAssertionCount` — Phantom-Assertion im silenten Catch |
| `FP` | Sweep-Match in einem Dokumentations-Kommentar, kein Code |

## Strategie-Codes

| Code | Vorgehen |
|---|---|
| `FAILURE_PIN` | Skip entfernen; **Soll-Verhalten** asserten. Test schlägt rot fehl, solange der Upstream-Bug aktiv ist (Default-Politik dieses Repos, siehe `docs/wf_test-iteration_guide.md` §5 i.7). Failure-Message benennt SUT, Defekt-Ort und nötigen Fix. Sobald Upstream fixt, wird der Test grün. |
| `FIX_SET` | Skip entfernen; Voraussetzung im Test selbst aufbauen (Media-Record, Familie, …) und echte Postcondition prüfen. |
| `BEHAVIOR_HANDLE` | `class_exists`-Smoke durch Container-Instanziierung + `handle()` + Statuscode-Assertion ersetzen. |
| `POSTCOND` | `assertTrue(true)` durch echte Postcondition ersetzen (DB-Zustand, Mock-Capture, Returnwert). |
| `SHARP_CATCH` | Silenten Catch entfernen oder durch `expectException` ersetzen. |
| `ACCEPT_DESIGN` | SEC-AUDIT-OR-Property — by design, kein Code-Change. |
| `ACCEPT_SEMANTIC` | "Kein Exception = Erfolg" ist hier der dokumentierte Property — kein Code-Change. |
| `FALSE_POS` | Sweep-Treffer ist Dokumentation, kein Code — kein Change. |

## Status

| Wert | Bedeutung |
|---|---|
| `offen` | noch nicht bearbeitet |
| `in_arbeit` | Worker hat Lauf begonnen, noch kein Commit |
| `erledigt` | Filter-Lauf grün, Commit erstellt |
| `akzeptiert` | by-design, kein Eingriff |
| `false_positive` | Sweep-Match, kein Defekt |
| `blockiert` | Worker hat aufgegeben, Begründung in `audit.log` |

## Tasks

| ID | Kat | Datei:Zeile | Strategie | Status |
|---|---|---|---|---|
| L3SP-001 | A2 | AddMediaFileActionIntegrationTest.php:66 | FIX_SET | erledigt |
| L3SP-002 | A2 | AddMediaFileActionIntegrationTest.php:109 | FIX_SET | erledigt |
| L3SP-003 | B | AdminMediaManagementIntegrationTest.php:161 | BEHAVIOR_HANDLE | erledigt |
| L3SP-004 | A1 | AutoCompleteIntegrationTest.php:211 | FAILURE_PIN | erledigt |
| L3SP-005 | B | CalendarChartIntegrationTest.php:175 | BEHAVIOR_HANDLE | erledigt |
| L3SP-006 | B | CalendarChartIntegrationTest.php:224 | BEHAVIOR_HANDLE | erledigt |
| L3SP-007 | B | CopyFactIntegrationTest.php:48 | BEHAVIOR_HANDLE | erledigt |
| L3SP-008 | B | CopyFactIntegrationTest.php:192 | BEHAVIOR_HANDLE | erledigt |
| L3SP-009 | A2 | EditMediaFileIntegrationTest.php:61 | FIX_SET | erledigt |
| L3SP-010 | A2 | EditMediaFileIntegrationTest.php:98 | FIX_SET | erledigt |
| L3SP-011 | A2 | EditMediaFileIntegrationTest.php:105 | FIX_SET | erledigt |
| L3SP-012 | C | ErrorHandlerMiddlewareIntegrationTest.php:115 | ACCEPT_SEMANTIC | akzeptiert |
| L3SP-013 | B | HeaderPageIntegrationTest.php:53 | BEHAVIOR_HANDLE | erledigt |
| L3SP-014 | A2 | InteractiveTreeIntegrationTest.php:70 | FIX_SET | erledigt |
| L3SP-015 | A2 | InteractiveTreeIntegrationTest.php:98 | FIX_SET | erledigt |
| L3SP-016 | D | LegacyUrlRedirectIntegrationTest.php:268 | SHARP_CATCH | erledigt |
| L3SP-017 | C | LoginActionIntegrationTest.php:351 | ACCEPT_DESIGN | akzeptiert |
| L3SP-018 | B | LogsMonitoringIntegrationTest.php:158 | BEHAVIOR_HANDLE | erledigt |
| L3SP-019 | B | LogsMonitoringIntegrationTest.php:220 | BEHAVIOR_HANDLE | erledigt |
| L3SP-020 | B | LogsMonitoringIntegrationTest.php:359 | BEHAVIOR_HANDLE | erledigt |
| L3SP-021 | B | MapDataCrudIntegrationTest.php:195 | BEHAVIOR_HANDLE | erledigt |
| L3SP-022 | B | MapDataCrudIntegrationTest.php:240 | BEHAVIOR_HANDLE | erledigt |
| L3SP-023 | B | MapDataCrudIntegrationTest.php:274 | BEHAVIOR_HANDLE | erledigt |
| L3SP-024 | B | MapDataCrudIntegrationTest.php:309 | BEHAVIOR_HANDLE | erledigt |
| L3SP-025 | B | MapDataCrudIntegrationTest.php:361 | BEHAVIOR_HANDLE | erledigt |
| L3SP-026 | B | MapDataCrudIntegrationTest.php:400 | BEHAVIOR_HANDLE | erledigt |
| L3SP-027 | B | MapDataCrudIntegrationTest.php:450 | BEHAVIOR_HANDLE | erledigt |
| L3SP-028 | B | MapDataCrudIntegrationTest.php:530 | BEHAVIOR_HANDLE | erledigt |
| L3SP-029 | C | MediaFileDeliveryIntegrationTest.php:400 | ACCEPT_DESIGN | akzeptiert |
| L3SP-030 | C | MediaFileServiceUploadIntegrationTest.php:194 | ACCEPT_DESIGN | akzeptiert |
| L3SP-031 | A2 | MergeFactsIntegrationTest.php:50 | FIX_SET | erledigt |
| L3SP-032 | FP | ModuleActionIntegrationTest.php:33 | FALSE_POS | false_positive |
| L3SP-033 | C | ModuleActionIntegrationTest.php:104 | ACCEPT_DESIGN | akzeptiert |
| L3SP-034 | A2 | ModuleActionIntegrationTest.php:133 | ACCEPT_DESIGN | akzeptiert |
| L3SP-035 | B | NotFoundIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-036 | B | NotePageIntegrationTest.php:55 | BEHAVIOR_HANDLE | erledigt |
| L3SP-037 | B | PageBlockDisplayIntegrationTest.php:74 | BEHAVIOR_HANDLE | erledigt |
| L3SP-038 | B | PageBlockDisplayIntegrationTest.php:123 | BEHAVIOR_HANDLE | erledigt |
| L3SP-039 | B | PageBlockEditIntegrationTest.php:70 | BEHAVIOR_HANDLE | erledigt |
| L3SP-040 | B | PageBlockEditIntegrationTest.php:117 | BEHAVIOR_HANDLE | erledigt |
| L3SP-041 | B | PageBlockUpdateIntegrationTest.php:73 | BEHAVIOR_HANDLE | erledigt |
| L3SP-042 | B | PageBlockUpdateIntegrationTest.php:120 | BEHAVIOR_HANDLE | erledigt |
| L3SP-043 | B | PasswordResetActionIntegrationTest.php:56 | BEHAVIOR_HANDLE | erledigt |
| L3SP-044 | B | PasteFactIntegrationTest.php:51 | BEHAVIOR_HANDLE | erledigt |
| L3SP-045 | B | PublicFilesMiddlewareIntegrationTest.php:46 | BEHAVIOR_HANDLE | erledigt |
| L3SP-046 | B | RenumberTreeActionIntegrationTest.php:239 | BEHAVIOR_HANDLE | erledigt |
| L3SP-047 | B | ReportIntegrationTest.php:496 | BEHAVIOR_HANDLE | erledigt |
| L3SP-048 | C | ReportPdfObjectsIntegrationTest.php:78 | POSTCOND | erledigt |
| L3SP-049 | C | ReportPdfObjectsIntegrationTest.php:103 | POSTCOND | erledigt |
| L3SP-050 | C | ReportPdfObjectsIntegrationTest.php:128 | POSTCOND | erledigt |
| L3SP-051 | C | ReportPdfObjectsIntegrationTest.php:157 | POSTCOND | erledigt |
| L3SP-052 | C | ReportPdfObjectsIntegrationTest.php:184 | POSTCOND | erledigt |
| L3SP-053 | B | SearchReplaceActionIntegrationTest.php:40 | BEHAVIOR_HANDLE | erledigt |
| L3SP-054 | B | SearchReplacePageIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-055 | B | TomSelectIntegrationTest.php:181 | BEHAVIOR_HANDLE | erledigt |
| L3SP-056 | B | TomSelectIntegrationTest.php:249 | BEHAVIOR_HANDLE | erledigt |
| L3SP-057 | B | TomSelectIntegrationTest.php:289 | BEHAVIOR_HANDLE | erledigt |
| L3SP-058 | B | TomSelectIntegrationTest.php:357 | BEHAVIOR_HANDLE | erledigt |
| L3SP-059 | B | TomSelectIntegrationTest.php:424 | BEHAVIOR_HANDLE | erledigt |
| L3SP-060 | B | TomSelectIntegrationTest.php:491 | BEHAVIOR_HANDLE | erledigt |
| L3SP-061 | B | TomSelectIntegrationTest.php:558 | BEHAVIOR_HANDLE | erledigt |
| L3SP-062 | B | TomSelectIntegrationTest.php:625 | BEHAVIOR_HANDLE | erledigt |
| L3SP-063 | B | TomSelectIntegrationTest.php:665 | BEHAVIOR_HANDLE | erledigt |
| L3SP-064 | B | TreeManagementIntegrationTest.php:173 | BEHAVIOR_HANDLE | erledigt |
| L3SP-065 | B | TreeManagementIntegrationTest.php:258 | BEHAVIOR_HANDLE | erledigt |
| L3SP-066 | B | TreeManagementIntegrationTest.php:270 | BEHAVIOR_HANDLE | erledigt |
| L3SP-067 | B | TreeManagementIntegrationTest.php:339 | BEHAVIOR_HANDLE | erledigt |
| L3SP-068 | B | TreeManagementIntegrationTest.php:401 | BEHAVIOR_HANDLE | erledigt |
| L3SP-069 | B | TreeManagementIntegrationTest.php:445 | BEHAVIOR_HANDLE | erledigt |
| L3SP-070 | B | TreePageDefaultEditIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-071 | B | TreePageDefaultUpdateIntegrationTest.php:37 | BEHAVIOR_HANDLE | erledigt |
| L3SP-072 | B | TreePageEditIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-073 | B | TreePageIntegrationTest.php:38 | BEHAVIOR_HANDLE | erledigt |
| L3SP-074 | B | TreePageUpdateIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-075 | B | UserPageDefaultEditIntegrationTest.php:36 | BEHAVIOR_HANDLE | erledigt |
| L3SP-076 | B | UserPageDefaultUpdateIntegrationTest.php:37 | BEHAVIOR_HANDLE | erledigt |
| L3SP-077 | B | UserPageEditIntegrationTest.php:51 | BEHAVIOR_HANDLE | erledigt |
| L3SP-078 | B | UserPageIntegrationTest.php:54 | BEHAVIOR_HANDLE | erledigt |
| L3SP-079 | B | UserPageUpdateIntegrationTest.php:52 | BEHAVIOR_HANDLE | erledigt |

## Vor-bestempelte Endzustände (Stand der Baseline)

Aus der Erstanalyse:

- `akzeptiert` (6): L3SP-012, L3SP-017, L3SP-029, L3SP-030, L3SP-033, L3SP-034 — `assertTrue(true)`/`markTestSkipped` ist dort der dokumentierte Property bzw. eine Fixture-Voraussetzung des bewussten SEC-AUDIT-Designs.
- `false_positive` (1): L3SP-032 — Treffer in einem Doc-Kommentar (`* … wird per markTestSkipped`).

Diese sieben Zeilen brauchen keinen Worker-Lauf — der Status ist final. Bei Re-Generierung der Sweep-Liste neu bestätigen.

## Aktive Queue

72 Zeilen mit Status `offen` zum Bearbeiten.

Counter ad-hoc reproduzierbar:
```
grep -E "^\| L3SP-" docs/improve-layer3/plan.md | awk -F'|' '{print $6}' | sort | uniq -c
```
