<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P36: CLI Einstellungs-Verwaltung

**Referenz:** P36 | **SUT:** `SiteSetting`, `TreeSetting`, `UserSetting`, `UserTreeSetting` (alle in `app/Cli/Commands/`)  
**Aktueller Test:** `CliSettingsBatchIntegrationTest` (4 Tests: je Set + List pro Command)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Je ein Test pro Command: Set + List. Alle 4 haben aber identische Branching-Logik mit 14 Branches — davon sind nur 3 getestet.

---

## SUT-Kernbefunde

Alle 4 Commands folgen dem gleichen Zustandsautomaten:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `--list` + `--delete` → FAILURE | ❌ |
| B2 | `--list` + `setting-value` → FAILURE | ❌ |
| B3 | `--list` (ohne Konflikte) → Liste ausgeben | ✅ |
| B4 | `--delete` ohne Name → FAILURE | ❌ |
| B5 | `--delete` + `setting-value` → FAILURE | ❌ |
| B6 | `--delete` + Einstellung nicht vorhanden → Warning, SUCCESS | ❌ |
| B7 | `--delete` + vorhanden → Löschen, SUCCESS | ❌ |
| B8 | Kein Name → FAILURE | ❌ |
| B9 | Name + kein Value + Einstellung fehlt → „not set", SUCCESS | ❌ |
| B10 | Name + kein Value + vorhanden + quiet → Wert nur | ❌ |
| B11 | Name + kein Value + vorhanden | ❌ |
| B12 | Wert = bestehender Wert → Warning „already set" | ❌ |
| B13 | Name + neuer Value → Insert (nicht vorhanden) | ✅ (implizit via Set) |
| B14 | Name + neuer Value → Update (vorhanden) | ❌ |

**Zusätzliche Branches für `TreeSetting`:** Tree nicht gefunden → FAILURE  
**Zusätzliche Branches für `UserSetting`:** User nicht gefunden → FAILURE  
**Zusätzliche Branches für `UserTreeSetting`:** User nicht gefunden ODER Tree nicht gefunden → FAILURE

---

## Äquivalenzklassen (EP)

### Basis (gilt für alle 4 Commands)

| Klasse | Aktion | Erwartung |
|---|---|---|
| EP1 | `--list` | Tabelle aller Einstellungen |
| EP2 | `--list --delete` | FAILURE |
| EP3 | `--delete setting_name` (existiert) | Gelöscht, SUCCESS |
| EP4 | `--delete setting_name` (fehlt) | Warning, SUCCESS |
| EP5 | `--delete` ohne Name | FAILURE |
| EP6 | Get: `setting_name` (existiert) | Aktueller Wert ausgegeben |
| EP7 | Get: `setting_name` (fehlt) | „not currently set", SUCCESS |
| EP8 | Set: neuer Wert | Insert, SUCCESS |
| EP9 | Set: gleicher Wert | Warning „already set", SUCCESS |
| EP10 | Set: anderer Wert (vorhanden) | Update, SUCCESS |

### Spezifisch für `TreeSetting`/`UserSetting`/`UserTreeSetting`

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP11 | Tree/User nicht gefunden | FAILURE, Fehlermeldung |

---

## Grenzwerte (BVA)

- Setting-Name: `''` (leer), 1 Zeichen, sehr langer Name
- Setting-Value: `''` (leer), `null` (nicht angegeben), gleicher wie vorhandener
- Idempotenz-Grenze: Wert exakt gleich vs. um 1 Zeichen verschieden

---

## Empfohlene Strategie

**ISTQB B** — Zustandsautomat ist klar (list → get → set/delete) mit definierten Fehler-Returns. DataProvider über alle 4 Commands (SiteSetting, TreeSetting, etc.) möglich, da sie die gleiche Interface bieten.

**Aufsplittung** nicht zwingend, aber sinnvoll: Pro Command eine Klasse — erlaubt Command-spezifische Tests (Tree-not-found etc.) ohne Mischmasch.

---

## Konkrete Testideen

```
// Für alle 4 Commands per DataProvider
test_setting_command_list_flag_conflicts_with_delete()
test_setting_command_get_nonexistent_setting_reports_not_set()
test_setting_command_delete_nonexistent_setting_warns_but_succeeds()
test_setting_command_set_same_value_warns_already_set()
test_setting_command_update_existing_value()

// Spezifisch
test_tree_setting_fails_when_tree_not_found()
test_user_setting_fails_when_user_not_found()
test_user_tree_setting_fails_when_tree_not_found()
```

---

## Aufwand

**Niedrig** — Bestehende Batch-Test-Klasse erweitern oder in 4 separate Klassen aufsplitten. CLI-Infrastruktur vorhanden.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; UserSetting nutzt stringArgument ('' statt null) |
| P2: Soll-Design | ✅ DONE | 8 neue Tests: B1/B6/B9/B12/B14 für SiteSetting + EP11 für Tree/User/UserTree |
| P3: Test-Coding | ✅ DONE | CliSettingsBatchIntegrationTest.php erweitert: +8 Tests |
| P4: Ausführung + Fixing | ✅ DONE | 17/17 grün, 42 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Zustandsbasiert, CRAP-Zeile korrigiert), Abdeckungsmatrix, Endekriterien, Changelog aktualisiert |
