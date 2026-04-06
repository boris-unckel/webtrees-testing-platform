<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A01: Stammbaum-Management

**Referenz:** A01 | **SUT:** `app/Http/RequestHandlers/CreateTreePage.php`, `CreateTreeAction.php`, `DeleteTreeAction.php`, `ManageTrees.php`, `MergeTreesPage.php`, `MergeTreesAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

`TreeOperationsTest` testet `treeService->create()` und `treeService->delete()` auf Service-Ebene. Die HTTP-Handler `CreateTreeAction`, `DeleteTreeAction`, `MergeTreesAction` sind nicht getestet.

---

## SUT-Kernbefunde

### CreateTreeAction

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Tree-Name existiert bereits → redirect(CreateTreePage) | Nein |
| B2 (Happy Path) | Tree-Name frei → create() + redirect(ManageTrees) | Nein |

### DeleteTreeAction

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Erwartet `$tree`-Attribut im Request, Admin-only | Nein |
| B2 (Happy Path) | Löscht Baum + alle Records (cascade) → response() 200 | Nein |

### MergeTreesAction

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | tree1 oder tree2 nicht vorhanden → redirect(MergeTreesPage) | Nein |
| B2 | tree1 === tree2 → redirect(MergeTreesPage) | Nein |
| B3 | Gemeinsame XREFs → redirect(MergeTreesPage) (Konflikt) | Nein |
| B4 (Happy Path) | Kopiert Records aus Baum2 in Baum1 über 9 Tabellen, Admin-only | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | CreateTreeAction: Duplikat-Name | 302 zu CreateTreePage, kein neuer Baum |
| EP2 | CreateTreeAction: Happy Path | 302 zu ManageTrees, Baum in DB |
| EP3 | DeleteTreeAction: Happy Path | 200, Baum nicht mehr in DB |
| EP4 | MergeTreesAction: gleicher Baum | 302 zu MergeTreesPage |
| EP5 | MergeTreesAction: Gemeinsame XREFs | 302 zu MergeTreesPage |
| EP6 | MergeTreesAction: Happy Path | 302 zu ManageTrees, Records aus Baum2 in Baum1 |
| EP7 | ManageTrees GET | Smoke: 200 |
| EP8 | CreateTreePage GET | Smoke: 200 |

---

## Empfohlene Strategie

**ISTQB B für CreateTreeAction + DeleteTreeAction (niedrig), MergeTreesAction (hoch, 9 Tabellen).** Neue Klasse `TreeManagementIntegrationTest extends MysqlTestCase`. Für MergeTreesAction: zwei Bäume ohne gemeinsame XREFs. DB-Postcondition: Records aus Baum2 in Baum1 vorhanden.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | CreateTreeAction DI: TreeService; DeleteTreeAction DI: TreeService; ManageTrees DI: AdminService+TreeService; View benötigt non-null tree |
| P2: Soll-Design | ✅ | EP1/EP2 (CreateTree), EP3 (DeleteTree 204), EP7 (ManageTrees GET) |
| P3: Test-Coding | ✅ | `TreeManagementIntegrationTest` (4 Tests) |
| P4: Ausführung + Fixing | ✅ | 4/4 grün (Fix: unique tree-name, tree-Attribut für ManageTrees) |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix A01 aktualisiert |
