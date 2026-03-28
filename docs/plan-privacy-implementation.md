# Umsetzungsplan — Datenschutz & Zugriffskontrolle (Privacy & Access Control)

> **Zweck:** Detaillierter Implementierungsplan mit Statustracking für die Umsetzung
> von `docs/plan-privacy-testing-prompt.md` (Feature-Matrix P01–P29, ~202 Testfälle).
>
> Erstellt: 2026-03-28. Referenz: `docs/plan-privacy-testing-prompt.md` (Planungs-Prompt),
> `docs/testing-bigpicture-prompt.md` (Teststrategie).

---

## Statuslegende

| Symbol | Bedeutung |
|---|---|
| ⬜ | Geplant |
| 🔄 | In Arbeit |
| ✅ | Erledigt |
| ⏭️ | Übersprungen (mit Begründung) |
| 🐛 | Blockiert / Bug gefunden |

---

## Phase P1 — Fixture & Infrastruktur

> **Ziel:** Alle Voraussetzungen für die Privacy-Tests schaffen: GEDCOM-Template,
> dynamische Generierung, Basisklasse, Rollen-Helper, Setup-Erweiterung.
> **Abhängigkeiten:** Keine.

### AP P1.1 — GEDCOM-Template erstellen

| Status | Aufgabe |
|---|---|
| ⬜ | `fixtures/privacy-test-template.ged` erstellen mit allen 30+ Testpersonen und 7+ Familien aus dem Planungs-Prompt (Abschnitt „Benötigte Personen / Familien") |
| ⬜ | Platzhalter `__YEAR_MINUS_N__` für alle dynamischen Daten verwenden |
| ⬜ | GEDCOM-Validität prüfen: korrekter HEAD-Record, TRLR, konsistente XREFs |
| ⬜ | Statische Personen (P_DEAD_HISTORIC, P_DEAD_EXPLICIT, P_DEAD_PLACED, P_RESN_*, P_ALIVE_NO_DATES) mit fixen Daten |
| ⬜ | Dynamische Personen (P_BOUNDARY_*, P_KEEP_*, P_INFER_*) mit `__YEAR_MINUS_N__`-Platzhaltern |
| ⬜ | Relationship-Kette: P_REL_USER → F_REL_1 → P_REL_CLOSE; P_REL_USER → ... → F_REL_CHAIN → P_REL_FAR; P_REL_UNRELATED ohne Verbindung |
| ⬜ | Inferenz-Familien: F_INFER_PARENT, F_INFER_PARENT_BOUNDARY, F_INFER_SPOUSE, F_INFER_CHILD, F_INFER_GRANDCHILD — jeweils mit passenden Hilfspersonen |
| ⬜ | Personen mit Fact-Level RESN: P_FACT_RESN_BIRT (`2 RESN privacy` auf BIRT), P_FACT_RESN_DEAT (`2 RESN confidential` auf DEAT) |
| ⬜ | RESN-locked-Personen: P_RESN_LOCKED (`1 RESN locked`), P_RESN_PRIV_LOCKED (`1 RESN privacy, locked`) |

### AP P1.2 — Fixture-Generator

| Status | Aufgabe |
|---|---|
| ⬜ | `scripts/generate-privacy-fixture.sh` erstellen: liest Template, ersetzt `__YEAR_MINUS_N__` mit `date +%Y - N`, schreibt `fixtures/privacy-test.ged` |
| ⬜ | Skript idempotent machen (überschreibt existierendes `privacy-test.ged`) |
| ⬜ | Skript manuell testen: Ausgabe-GEDCOM auf korrekte Jahreszahlen prüfen |

### AP P1.3 — Setup-Skript erweitern

| Status | Aufgabe |
|---|---|
| ⬜ | `scripts/setup-webtrees.sh`: Aufruf von `generate-privacy-fixture.sh` vor PHP-Import-Block einfügen |
| ⬜ | `$fixtures`-Array: dritten Eintrag `['name' => 'privacy', 'title' => 'Privacy Test Tree', 'file' => $fixturesDir . '/privacy-test.ged']` ergänzen |
| ⬜ | 4 Test-User anlegen (member, editor, moderator, manager) mit Rollen im `privacy`-Baum UND im `demo`-Baum |
| ⬜ | Editor-User: `PREF_AUTO_ACCEPT_EDITS=''` (Pending Changes bleiben stehen) |
| ⬜ | Relationship-Privacy-User: `PREF_TREE_ACCOUNT_XREF=P_REL_USER`, `PREF_TREE_PATH_LENGTH=3` für einen dedizierten User |
| ⬜ | `make setup` ausführen und prüfen: 3 Bäume importiert, 5+ User angelegt, bestehende Tests nicht beeinträchtigt |

### AP P1.4 — PrivacyTestCase.php (Teststufe 2)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/PrivacyTestCase.php` erstellen (erweitert `MysqlTestCase`) |
| ⬜ | `generatePrivacyGedcom()`: Template lesen, `__YEAR_MINUS_N__` ersetzen |
| ⬜ | `createPrivacyTree()`: GEDCOM generieren, importieren, Tree-Objekt zurückgeben |
| ⬜ | `createUserWithRole(string $role, Tree $tree)`: User anlegen + Rolle zuweisen |
| ⬜ | `setTreePreference(Tree $tree, string $key, string $value)`: Helfer für Stammbaumeinstellungen |
| ⬜ | Smoke-Test: leere Testklasse, die `createPrivacyTree()` aufruft und Tree-ID assertiert |

### AP P1.5 — Playwright-Helpers (Teststufe 3)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/helpers/privacy-roles.ts` erstellen: `loginAsRole(page, role)`, `logoutRole(page)` |
| ⬜ | Rollen: `visitor`, `member`, `editor`, `moderator`, `manager` |
| ⬜ | Smoke-Test: minimale Spec, die mit jeder Rolle `/tree/privacy` aufruft und HTTP-Status prüft |

### AP P1.6 — Bestehende Tests verifizieren

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-unit` — bestehende Komponententests weiterhin grün |
| ⬜ | `make test-integration` — bestehende 178 Integrationstests weiterhin grün (1 skipped) |
| ⬜ | `make test-e2e` — bestehende ~150 E2E-Tests weiterhin grün |

**Phase P1 abgeschlossen:** ⬜

---

## Phase P2 — isDead()-Tests (Teststufe 2)

> **Ziel:** `IsDeadTest.php` — vollständige Abdeckung des isDead()-Algorithmus
> mit Grenzwertanalyse und Verwandten-Inferenz.
> **Abhängigkeiten:** P1.
> **Features:** P08–P13 (~30 Testmethoden).

### AP P2.1 — Expliziter Tod und Basis-isDead()

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/IsDeadTest.php` erstellen (erweitert `PrivacyTestCase`) |
| ⬜ | P08: `test_is_dead_with_deat_y_returns_true` |
| ⬜ | P08: `test_is_dead_with_deat_date_returns_true` |
| ⬜ | P08: `test_is_dead_with_deat_plac_returns_true` |
| ⬜ | P08: `test_is_dead_without_deat_young_person_returns_false` |
| ⬜ | P10: `test_is_dead_with_recent_birth_no_deat_returns_false` |
| ⬜ | P10: `test_is_dead_no_dates_no_relatives_returns_false` (Fallback: lebend) |

### AP P2.2 — MAX_ALIVE_AGE Grenzwertanalyse

| Status | Aufgabe |
|---|---|
| ⬜ | P09: DataProvider mit Event-Grenzwerten (exakt, ±1 Tag) |
| ⬜ | P04: `test_max_alive_age_boundary_exact_birth_is_dead` |
| ⬜ | P04: `test_max_alive_age_boundary_minus1_birth_is_alive` |
| ⬜ | P04: `test_max_alive_age_boundary_plus1_birth_is_dead` |
| ⬜ | P09: Nicht-Geburts-Event (z.B. OCCU) älter als MAX_ALIVE_AGE → tot |

### AP P2.3 — Verwandten-Inferenz

| Status | Aufgabe |
|---|---|
| ⬜ | P11: `test_is_dead_inference_parents_old_events_returns_true` |
| ⬜ | P11: `test_is_dead_inference_parents_boundary_returns_false` (Grenze MAX_ALIVE_AGE+45) |
| ⬜ | P12: `test_is_dead_inference_spouse_old_marriage_returns_true` |
| ⬜ | P12: `test_is_dead_inference_spouse_old_partner_events_returns_true` (MAX_ALIVE_AGE+40) |
| ⬜ | P12: `test_is_dead_inference_spouse_boundary_returns_false` |
| ⬜ | P13: `test_is_dead_inference_children_old_events_returns_true` (MAX_ALIVE_AGE−15) |
| ⬜ | P13: `test_is_dead_inference_grandchildren_old_events_returns_true` (MAX_ALIVE_AGE−30) |
| ⬜ | P13: `test_is_dead_inference_children_boundary_returns_false` |

### AP P2.4 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-integration` — IsDeadTest grün |
| ⬜ | Ergebnis dokumentieren: Anzahl Tests, Anzahl Assertions, ggf. Skipped/Failures |

**Phase P2 abgeschlossen:** ⬜ | Tests: _/_ passed | Assertions: _ | Skipped: _ |

---

## Phase P3 — Visibility-Tests (Teststufe 2)

> **Ziel:** `PrivacyVisibilityTest.php` — Stammbaum-Sichtbarkeit, Verstorbene/Lebende,
> KEEP_ALIVE, Namen und Beziehungen vertraulicher Personen.
> **Abhängigkeiten:** P1.
> **Features:** P01–P07, P14–P15 (~40 Testmethoden).

### AP P3.1 — Stammbaum-Sichtbarkeit (P01, P03)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/PrivacyVisibilityTest.php` erstellen |
| ⬜ | P01: `REQUIRE_AUTHENTICATION=1` → Besucher kann Record nicht sehen |
| ⬜ | P01: `REQUIRE_AUTHENTICATION=0` → Besucher sieht öffentliche Records |
| ⬜ | P03: `HIDE_LIVE_PEOPLE=0` → Privacy komplett deaktiviert, alle Rollen sehen alles |
| ⬜ | P03: `HIDE_LIVE_PEOPLE=1` → Privacy aktiv (Normalfall), Rollenunterschied greift |

### AP P3.2 — Verstorbene Personen (P02)

| Status | Aufgabe |
|---|---|
| ⬜ | P02: DataProvider: `SHOW_DEAD_PEOPLE` × Rolle (B, M, V) × Personenzustand (historisch tot, kürzlich tot, lebend) |
| ⬜ | P02: `SHOW_DEAD_PEOPLE=PRIV_PRIVATE` → Besucher sieht Verstorbene |
| ⬜ | P02: `SHOW_DEAD_PEOPLE=PRIV_USER` → Nur Mitglieder+ sehen Verstorbene |

### AP P3.3 — KEEP_ALIVE Grenzwertanalyse (P04–P07)

| Status | Aufgabe |
|---|---|
| ⬜ | P04: MAX_ALIVE_AGE-Grenzwerte (nutzt isDead()-Ergebnis) × Sichtbarkeit pro Rolle |
| ⬜ | P05: DataProvider: KEEP_ALIVE_YEARS_BIRTH=10 × Geburt (innerhalb, Grenze, außerhalb) × Rolle |
| ⬜ | P06: DataProvider: KEEP_ALIVE_YEARS_DEATH=10 × Tod (innerhalb, Grenze, außerhalb) × Rolle |
| ⬜ | P07: Beide KEEP_ALIVE gesetzt — OR-Logik prüfen (einer greift, einer nicht) |

### AP P3.4 — Namen und Beziehungen (P14–P15)

| Status | Aufgabe |
|---|---|
| ⬜ | P14: DataProvider: `SHOW_LIVING_NAMES` (PRIV_PRIVATE, PRIV_USER, PRIV_NONE) × Rolle |
| ⬜ | P14: `canShowName()` getrennt von `canShow()` prüfen |
| ⬜ | P15: `SHOW_PRIVATE_RELATIONSHIPS=1` → vertrauliche Beziehungen in Familienliste sichtbar |
| ⬜ | P15: `SHOW_PRIVATE_RELATIONSHIPS=0` → keine Beziehungsanzeige |

### AP P3.5 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-integration` — PrivacyVisibilityTest grün |
| ⬜ | Ergebnis dokumentieren |

**Phase P3 abgeschlossen:** ⬜ | Tests: _/_ passed | Assertions: _ | Skipped: _ |

---

## Phase P4 — RESN-Tests (Teststufe 2)

> **Ziel:** `ResnPrivacyTest.php` — RESN-Tags (Record + Fact), default_resn.
> **Abhängigkeiten:** P1.
> **Features:** P16–P21 (~25 Testmethoden).

### AP P4.1 — Record-Level RESN (P16–P18)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/ResnPrivacyTest.php` erstellen |
| ⬜ | P16: DataProvider: RESN none × Rolle (B, M, V) → alle sehen Person |
| ⬜ | P17: DataProvider: RESN privacy × Rolle → nur M+ sehen Person |
| ⬜ | P18: DataProvider: RESN confidential × Rolle → nur V+ sehen Person |
| ⬜ | RESN auf Familien-Record (FAM) — via `Registry::familyFactory()->make()`, nicht Mapper |

### AP P4.2 — Fact-Level RESN (P19)

| Status | Aufgabe |
|---|---|
| ⬜ | P19: BIRT-Fakt mit `2 RESN privacy` → Person sichtbar, BIRT-Fakt nur für M+ |
| ⬜ | P19: DEAT-Fakt mit `2 RESN confidential` → Person sichtbar, DEAT-Fakt nur für V+ |
| ⬜ | P19: Fakt ohne RESN → für alle sichtbar (Kontrollgruppe) |

### AP P4.3 — default_resn (P20–P21)

| Status | Aufgabe |
|---|---|
| ⬜ | P20: Eintrag in `default_resn` mit `xref=P_EDIT_TARGET, tag_type=NULL` → gesamter Record eingeschränkt |
| ⬜ | P21: Eintrag mit `xref=NULL, tag_type=BIRT` → alle BIRT-Fakten im Baum eingeschränkt |
| ⬜ | P21: Eintrag mit `xref=P_EDIT_TARGET, tag_type=DEAT` → nur DEAT dieses Records eingeschränkt |
| ⬜ | DB-Einträge in `default_resn` per Setup-Code anlegen, nicht per GEDCOM |

### AP P4.4 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-integration` — ResnPrivacyTest grün |
| ⬜ | Ergebnis dokumentieren |

**Phase P4 abgeschlossen:** ⬜ | Tests: _/_ passed | Assertions: _ | Skipped: _ |

---

## Phase P5 — Relationship Privacy & Suche (Teststufe 2)

> **Ziel:** `RelationshipPrivacyTest.php` + `PrivacySearchTest.php`.
> **Abhängigkeiten:** P1.
> **Features:** P22–P24 (~20 Testmethoden).

### AP P5.1 — Relationship Privacy (P22–P23)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/RelationshipPrivacyTest.php` erstellen |
| ⬜ | P22: User mit `PREF_TREE_PATH_LENGTH=3` → P_REL_CLOSE (2 Schritte) sichtbar |
| ⬜ | P22: User mit `PREF_TREE_PATH_LENGTH=3` → P_REL_FAR (6 Schritte) nicht sichtbar |
| ⬜ | P22: User mit `PREF_TREE_PATH_LENGTH=3` → P_REL_UNRELATED nicht sichtbar |
| ⬜ | P22: User mit `PREF_TREE_PATH_LENGTH=0` → Pfadlänge deaktiviert, alle sichtbar |
| ⬜ | P23: User mit `PREF_TREE_PATH_LENGTH=3` aber kein `PREF_TREE_ACCOUNT_XREF` → Fallback: alles sichtbar |

### AP P5.2 — Privacy in Suchergebnissen (P24)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/PrivacySearchTest.php` erstellen |
| ⬜ | P24: Besucher sucht nach lebender Person → nicht in Ergebnissen |
| ⬜ | P24: Mitglied sucht nach lebender Person → in Ergebnissen |
| ⬜ | P24: Besucher sucht nach RESN-confidential-Person → nicht in Ergebnissen |
| ⬜ | P24: Verwalter sucht nach RESN-confidential-Person → in Ergebnissen |
| ⬜ | P24: Besucher sucht nach Person mit RESN none → in Ergebnissen |

### AP P5.3 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-integration` — RelationshipPrivacyTest + PrivacySearchTest grün |
| ⬜ | Ergebnis dokumentieren |

**Phase P5 abgeschlossen:** ⬜ | Tests: _/_ passed | Assertions: _ | Skipped: _ |

---

## Phase P6 — Zugriffskontrolle (Teststufe 2)

> **Ziel:** `AccessControlTest.php` — Edit, Accept, Lock, auto_accept.
> **Abhängigkeiten:** P1.
> **Features:** P27–P29 (~20 Testmethoden).

### AP P6.1 — Bearbeiter-Edit (P27)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer3-integration/tests/AccessControlTest.php` erstellen |
| ⬜ | P27: Bearbeiter fügt Fakt hinzu → DB-Tabelle `change` hat Eintrag mit `status='pending'` |
| ⬜ | P27: Bearbeiter mit `PREF_AUTO_ACCEPT_EDITS='1'` → Change sofort akzeptiert |
| ⬜ | P27: Mitglied versucht Edit → Exception / Kein DB-Eintrag |
| ⬜ | P27: Besucher versucht Edit → Exception / Kein DB-Eintrag |

### AP P6.2 — Moderator-Akzeptanz (P28)

| Status | Aufgabe |
|---|---|
| ⬜ | P28: Bearbeiter erstellt Pending Change → Moderator akzeptiert → `status='accepted'` |
| ⬜ | P28: Akzeptierter Change → GEDCOM des Records aktualisiert |
| ⬜ | P28: Moderator verwirft Change → `status='rejected'` |

### AP P6.3 — RESN locked und Zugriffsverbot (P29)

| Status | Aufgabe |
|---|---|
| ⬜ | P29: Bearbeiter auf P_RESN_LOCKED → `canEdit()` = false |
| ⬜ | P29: Verwalter auf P_RESN_LOCKED → `canEdit()` = true |
| ⬜ | P29: Bearbeiter auf P_RESN_PRIV_LOCKED → `canShow()` = true (Mitglied+), `canEdit()` = false |
| ⬜ | P29: Besucher auf P_RESN_PRIV_LOCKED → `canShow()` = false |
| ⬜ | P29: Verwalter auf P_RESN_PRIV_LOCKED → `canShow()` = true, `canEdit()` = true |
| ⬜ | P29: Mitglied kann Edit-Seite/Handler nicht aufrufen → Exception |

### AP P6.4 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-integration` — AccessControlTest grün |
| ⬜ | Ergebnis dokumentieren |

**Phase P6 abgeschlossen:** ⬜ | Tests: _/_ passed | Assertions: _ | Skipped: _ |

---

## Phase P7 — Systemtests (Teststufe 3, Playwright)

> **Ziel:** 6 Playwright-Spec-Dateien + 1 Helper für End-to-End-Privacy-Tests.
> **Abhängigkeiten:** P1 (Setup-Erweiterung, Fixture, User-Anlage).
> **Features:** P01–P03, P14–P19, P22, P24–P29 (~67 Tests).

### AP P7.1 — Privacy Visibility (P01–P03, P14, P25)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/privacy-visibility.spec.ts` erstellen |
| ⬜ | P01: Visitor auf `/tree/privacy` bei `REQUIRE_AUTHENTICATION=1` → Redirect zu Login |
| ⬜ | P25: Visitor auf `/tree/privacy/individual/P_ALIVE_YOUNG` → Access-Denied-Meldung |
| ⬜ | P25: Member auf `/tree/privacy/individual/P_ALIVE_YOUNG` → Person sichtbar |
| ⬜ | P14: SHOW_LIVING_NAMES prüfen — Visitor sieht Name, aber keine Details |
| ⬜ | P02: Visitor sieht historisch Verstorbene (P_DEAD_HISTORIC) |
| ⬜ | P03: HIDE_LIVE_PEOPLE=0 → Visitor sieht alles |
| ⬜ | Alle Prüfungen mit `loginAsRole()` Helper |

### AP P7.2 — Privacy RESN (P16–P19)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/privacy-resn.spec.ts` erstellen |
| ⬜ | P16: Visitor auf P_RESN_NONE → Person sichtbar (trotz „lebend") |
| ⬜ | P17: Visitor auf P_RESN_PRIVACY → Access Denied; Member → sichtbar |
| ⬜ | P18: Member auf P_RESN_CONFIDENTIAL → Access Denied; Manager → sichtbar |
| ⬜ | P19: Member auf P_FACT_RESN_BIRT → Person sichtbar, Geburtsdatum sichtbar; Visitor → Geburtsdatum verborgen |

### AP P7.3 — Privacy Suche (P24)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/privacy-search.spec.ts` erstellen |
| ⬜ | P24: Visitor sucht nach P_ALIVE_YOUNG (Name) → nicht in Ergebnisliste |
| ⬜ | P24: Member sucht nach P_ALIVE_YOUNG → in Ergebnisliste |
| ⬜ | P24: Visitor sucht nach P_RESN_NONE → in Ergebnisliste |
| ⬜ | P24: Visitor sucht nach P_RESN_CONFIDENTIAL → nicht in Ergebnisliste |

### AP P7.4 — Privacy Charts (P26)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/privacy-charts.spec.ts` erstellen |
| ⬜ | P26: Pedigree-Chart mit P_REL_USER → vertrauliche Vorfahren als „Private"-Boxen (SHOW_PRIVATE_RELATIONSHIPS=1) |
| ⬜ | P26: SHOW_PRIVATE_RELATIONSHIPS=0 → vertrauliche Vorfahren komplett ausgeblendet |

### AP P7.5 — Relationship Privacy (P22)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/privacy-relationship.spec.ts` erstellen |
| ⬜ | P22: Relationship-User auf P_REL_CLOSE → sichtbar |
| ⬜ | P22: Relationship-User auf P_REL_FAR → Access Denied |
| ⬜ | P22: Relationship-User auf P_REL_UNRELATED → Access Denied |

### AP P7.6 — Access Control (P27–P29)

| Status | Aufgabe |
|---|---|
| ⬜ | `layer4-e2e/tests/access-control.spec.ts` erstellen |
| ⬜ | P27: Editor auf Edit-Seite → Formular sichtbar, Fakt hinzufügen möglich |
| ⬜ | P27: Nach Edit → Pending-Change-Hinweis auf Personenseite |
| ⬜ | P28: Moderator sieht Pending Changes → akzeptieren → Änderung übernommen |
| ⬜ | P29: Visitor auf Edit-Seite → Access Denied |
| ⬜ | P29: Member auf Edit-Seite → Access Denied |
| ⬜ | P29: Visitor sieht keine Edit-Buttons auf Personenseite |
| ⬜ | P29: Editor auf P_RESN_LOCKED → Edit-Button fehlt oder Edit nicht möglich |

### AP P7.7 — Tests ausführen

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-e2e` — alle Privacy-Specs grün |
| ⬜ | Ergebnis dokumentieren |

**Phase P7 abgeschlossen:** ⬜ | Tests: _/_ passed | Skipped: _ | Flaky: _ |

---

## Phase P8 — Kompletttest & Fehlerbereinigung

> **Ziel:** `make test-all` grün über alle 5 Layer. Fehler aus P2–P7 beheben.
> Iterationsrunden bis alles stabil läuft.
> **Abhängigkeiten:** P2–P7.

### AP P8.1 — Erster Komplettlauf

| Status | Aufgabe |
|---|---|
| ⬜ | `make down && make up && make setup` — sauberer Stack-Neustart |
| ⬜ | `make test-all` — alle Layer sequenziell |
| ⬜ | Ergebnisse dokumentieren: pro Layer Anzahl passed/failed/skipped |

### AP P8.2 — Fehlerbereinigung (Iterationsrunden)

| Iteration | Status | Fehler | Fix |
|---|---|---|---|
| 1 | ⬜ | _(wird bei Durchführung ausgefüllt)_ | |
| 2 | ⬜ | | |
| 3 | ⬜ | | |

### AP P8.3 — Abschlusslauf

| Status | Aufgabe |
|---|---|
| ⬜ | `make test-all` — komplett grün (0 Failures, Skipped nur bei bekannten upstream Bugs) |
| ⬜ | Ergebnisprotokoll: finale Zahlen pro Layer |

**Phase P8 abgeschlossen:** ⬜

---

## Phase P9 — Dokumentation aktualisieren

> **Ziel:** Ergebnisse in `docs/testing-bigpicture-prompt.md` integrieren.
> **Abhängigkeiten:** P8 (fehlerfreier Komplettlauf).

### AP P9.1 — Implementierungs-Fahrplan ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | Neue Phase in Fahrplan-Tabelle (nach Phase 10): `Phase 11 — Privacy & Zugriffskontrolle` mit Status und Ergebnis |

### AP P9.2 — Feature-Matrix ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | Neue Sektion „Feature-Matrix: Datenschutz & Zugriffskontrolle" mit P01–P29 einfügen (nach bestehenden Matrizen) |
| ⬜ | Testfall-Verteilung nach Teststufe aktualisieren (Gesamtzahlen G+S+P) |
| ⬜ | Prioritätsverteilung aktualisieren |

### AP P9.3 — Reverse-Engineering-Quellen aktualisieren

| Status | Aufgabe |
|---|---|
| ⬜ | Satz „Die Domänen Beziehungsberechnung und Privacy/Zugriffskontrolle sind bewusst als niedrigere Priorität eingestuft" → ersetzen durch Verweis auf P01–P29 |

### AP P9.4 — Produktrisiken ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | R8–R13 aus `plan-privacy-testing-prompt.md` in Risikotabelle aufnehmen |

### AP P9.5 — Testentwurfsverfahren ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | Privacy-spezifische Verfahren (Grenzwertanalyse für isDead, Entscheidungstabellentest für Rollenmatrix) in Verfahrenstabelle aufnehmen |

### AP P9.6 — Endekriterien ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | Teststufe 2 und 3 Endekriterien: Privacy-Features (P01–P29) grün |

### AP P9.7 — Abdeckungsmatrix ergänzen

| Status | Aufgabe |
|---|---|
| ⬜ | Neue Abdeckungsmatrix-Sektion „Datenschutz & Zugriffskontrolle (P01–P29)" analog zu G01–G23 und S01–S40 |

### AP P9.8 — Verzeichnisstruktur aktualisieren

| Status | Aufgabe |
|---|---|
| ⬜ | N2-Verzeichnisbaum: neue Dateien ergänzen (PrivacyTestCase.php, 6 Testklassen, 6 Spec-Dateien, privacy-roles.ts, privacy-test-template.ged, generate-privacy-fixture.sh) |

**Phase P9 abgeschlossen:** ⬜

---

## Ergebnisprotokoll

> Wird bei Durchführung ausgefüllt.

### Testlauf-Ergebnisse (nach Phase P8)

| Layer | Target | Ergebnis | Tests | Passed | Failed | Skipped |
|---|---|---|---|---|---|---|
| Layer 1 — Statischer Test | `make test-static` | ⬜ | | | | |
| Layer 2 — Komponententest | `make test-unit` | ⬜ | | | | |
| Layer 3 — Komponentenintegrationstest | `make test-integration` | ⬜ | | | | |
| Layer 4 — Systemtest | `make test-e2e` | ⬜ | | | | |
| Layer 5 — Performanztest | `make test-performance` | ⬜ | | | | |

### Neue Tests (Zusammenfassung)

| Teststufe | Testklasse/Spec | Feature-IDs | Tests | Assertions |
|---|---|---|---|---|
| Teststufe 2 | `IsDeadTest.php` | P08–P13 | ⬜ | |
| Teststufe 2 | `PrivacyVisibilityTest.php` | P01–P07, P14–P15 | ⬜ | |
| Teststufe 2 | `ResnPrivacyTest.php` | P16–P21 | ⬜ | |
| Teststufe 2 | `RelationshipPrivacyTest.php` | P22–P23 | ⬜ | |
| Teststufe 2 | `PrivacySearchTest.php` | P24 | ⬜ | |
| Teststufe 2 | `AccessControlTest.php` | P27–P29 | ⬜ | |
| Teststufe 3 | `privacy-visibility.spec.ts` | P01–P03, P14, P25 | ⬜ | |
| Teststufe 3 | `privacy-resn.spec.ts` | P16–P19 | ⬜ | |
| Teststufe 3 | `privacy-search.spec.ts` | P24 | ⬜ | |
| Teststufe 3 | `privacy-charts.spec.ts` | P26 | ⬜ | |
| Teststufe 3 | `privacy-relationship.spec.ts` | P22 | ⬜ | |
| Teststufe 3 | `access-control.spec.ts` | P27–P29 | ⬜ | |
| **Gesamt** | | **P01–P29** | **⬜** | |

### Gefundene Bugs

| # | Beschreibung | Betroffene Features | Typ | Status |
|---|---|---|---|---|
| _(wird bei Durchführung ausgefüllt)_ | | | | |

---

## Fazit

> Wird nach vollständiger Umsetzung und fehlerfreiem `make test-all` ausgefüllt.

### Ergebnis

| Metrik | Wert |
|---|---|
| Neue Features (P-Reihe) | /29 |
| Neue Testmethoden (Teststufe 2) | /~135 |
| Neue E2E-Tests (Teststufe 3) | /~67 |
| Gesamt neue Tests | /~202 |
| Feature-Abdeckung gesamt (G+S+P) | /91 |
| Iterationsrunden Fehlerbereinigung | |
| Gefundene Upstream-Bugs | |
| Skipped Tests (bekannte Einschränkungen) | |

### Bewertung

_(Freitext: Qualitative Einschätzung der Privacy-Testabdeckung, erkannte Lücken,
Empfehlungen für zukünftige Erweiterungen, Lessons Learned.)_

### Aktualisierung testing-bigpicture-prompt.md

| Abschnitt | Änderung | Status |
|---|---|---|
| Implementierungs-Fahrplan | Phase 11 ergänzt | ⬜ |
| Feature-Matrix | P01–P29 Sektion eingefügt | ⬜ |
| Testfall-Verteilung | Gesamtzahlen aktualisiert | ⬜ |
| Prioritätsverteilung | P-Features eingerechnet | ⬜ |
| Reverse-Engineering-Quellen | Privacy-Verweis aktualisiert | ⬜ |
| Produktrisiken | R8–R13 ergänzt | ⬜ |
| Testentwurfsverfahren | Privacy-Verfahren ergänzt | ⬜ |
| Endekriterien | P-Features in Kriterien aufgenommen | ⬜ |
| Abdeckungsmatrix | P01–P29 Matrix eingefügt | ⬜ |
| Verzeichnisstruktur (N2) | Neue Dateien ergänzt | ⬜ |
