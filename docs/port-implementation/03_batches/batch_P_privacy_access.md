<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch P — Datenschutz & Zugriffskontrolle

**Priorität:** 1+2 (Sicherheit + Codepfad-Komplexität)
**Feature-IDs:** P01–P41

**Hinweis:** Der Großteil der Privacy-Features (P01–P24) ist architekturbedingt
Layer-3-Territorium (Individual/Family-Graph-Traversierung, DB-Queries).
Portierbar sind vor allem die Auth-Middleware- und User-Management-Handler.

---

## Portierbare Tests

| # | Test-Datei | SUT-Klasse | Template | Dependencies | Status | Bemerkung |
|---|-----------|------------|----------|-------------|--------|-----------|
| 1 | `DeleteUserTest.php` | `DeleteUser` | T1 | `UserService` | `pending` | P37, bereits substanziell (3 Methoden), Verbesserung prüfen |
| 2 | `UserEditPageTest.php` | `UserEditPage` | T1 | `UserService` | `pending` | P37 |
| 3 | `UserEditActionTest.php` | `UserEditAction` | T1 | `UserService` | `pending` | P37 |
| 4 | `UserAddPageTest.php` | `UserAddPage` | T1 | `UserService` | `pending` | P37 |
| 5 | `UserAddActionTest.php` | `UserAddAction` | T1 | `UserService` | `pending` | P37 |
| 6 | `MasqueradeTest.php` | `Masquerade` | T1 | `UserService` | `pending` | P29, bereits substanziell (3 Methoden), Verbesserung prüfen |
| 7 | `AccountEditPageTest.php` | `AccountEditPage` | T1 | `UserService` | `pending` | P38 |
| 8 | `AccountEditActionTest.php` | `AccountEditAction` | T1 | `UserService` | `pending` | P38 |
| 9 | `AccountDeleteTest.php` | `AccountDelete` | T1 | `UserService` | `pending` | P38 |
| 10 | `LoginActionTest.php` | `LoginAction` | T1 | `AuthenticationService` | `pending` | P39 |
| 11 | `LogoutTest.php` | `Logout` | T2 | — | `pending` | P39 |
| 12 | `PendingChangesAcceptRecordTest.php` | `PendingChangesAcceptRecord` | T1 | `PendingChangesService` | `pending` | P40 |
| 13 | `PendingChangesAcceptTreeTest.php` | `PendingChangesAcceptTree` | T1 | `PendingChangesService` | `pending` | P40 |
| 14 | `PendingChangesRejectRecordTest.php` | `PendingChangesRejectRecord` | T1 | `PendingChangesService` | `pending` | P40 |
| 15 | `PendingChangesRejectTreeTest.php` | `PendingChangesRejectTree` | T1 | `PendingChangesService` | `pending` | P40 |
| 16 | `MergeRecordsPageTest.php` | `MergeRecordsPage` | T3 | `TreeService`, Registry | `pending` | P41 |
| 17 | `MergeRecordsActionTest.php` | `MergeRecordsAction` | T3 | `TreeService`, Registry | `pending` | P41 |

### Bestehende substanzielle Tests (Verbesserung in Phase P2)

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `DeleteUserTest.php` | 3 | Prüfen: alle Codepfade abgedeckt? |
| `MasqueradeTest.php` | 3 | Prüfen: fehlende Negativ-Tests? |
| `LoginPageTest.php` | 2 | Pattern-Inkonsistenz: reale TreeService statt Mock |
| `PasswordResetPageTest.php` | 2 | Prüfen: fehlende Edge Cases |
| `PasswordRequestPageTest.php` | 2 | Prüfen: fehlende Edge Cases |

## Ausgeschlossen (Layer 3 — Graph-Traversierung / DB-Privacy)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| P01–P07 | Stammbaum-Sichtbarkeit, Altersregeln | Privacy-Graph-Traversierung |
| P08–P13 | isDead()-Inferenzen | Individual-Traversierung FAMS/FAMC |
| P14–P15 | Vertrauliche Namen/Beziehungen | Privacy-Graph |
| P16–P21 | RESN-Regeln | RESN+DB |
| P22–P23 | Relationship Privacy | Graph-Traversierung |
| P24 | Privacy in Suche | DB-Query mit Privacy-Filter |
| P25–P26 | Vertraulich-Platzhalter / Charts | L4 vorrangig |
| P30–P34 | Merge/Edit/Privacy-Settings/Reorder | DB-State-Abhängig |
| P35–P36 | CLI User/Settings | CLI+DB |

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
grep -rl 'class_exists' tests/app/Http/RequestHandlers/ | \
  xargs grep -l 'User\|Login\|Logout\|Password\|Account\|Masquerade\|PendingChanges\|Merge\|Auth'
```

## Statistik

- Portierbar: ~17
- Ausgeschlossen: 24 Feature-IDs (Graph/Privacy/DB)
- Bereits substanziell: 5 Tests (Verbesserung in P2)
