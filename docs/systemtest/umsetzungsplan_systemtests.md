<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Umsetzungsplan: Systemtests (L4 Playwright)

**Erstellt:** 2026-04-12
**Basis:** [`testcov_systemtests_delta.md`](../testcov_systemtests_delta.md)
**Workflow:** [`wf_code-to-systemtest_guide.md`](../wf_code-to-systemtest_guide.md)
**Übergreifende Konzepte:** [`uebergreifende_konzepte_l4.md`](uebergreifende_konzepte_l4.md)

---

## 1 Überblick

**29 Features** ohne L4-Abdeckung, identifiziert in der Delta-Analyse.

**Schritte pro Feature** (→ wf_code-to-systemtest_guide.md Abschnitt 2):

| Kürzel | Schritt | Beschreibung |
|---|---|---|
| S1 | Kontext lesen | `tp_overview_spec.md` — Doku-/Code-Vorgaben (einmalig/shared) |
| S2 | Feature-Analyse | Upstream-Code: Handler, Views, Routen, Auth-Anforderung |
| S3 | L3-Referenz analysieren | L3-Tests: EP/BVA-Muster, Guards, Fixtures extrahieren |
| S4 | L4-Muster analysieren | Bestehende Playwright-Specs: Patterns, Helper-Nutzung |
| S5 | Spezifikation erstellen | `testspezi/<ID>_systemtest_spezi.md` (→ Vorlage Abschnitt 5) |
| S6 | Tests implementieren | P3: Test-Coding + P4: Ausführung + Fixing |
| S7 | Doku-Update | Coverage-Ref, Conditions-Ref, Ratchet, Methodik |
| S8 | Abschluss | Einzeltest grün, Konsistenzprüfung |

**S1 ist einmalig** pro Iterations-Serie. Für K01, K02 wird S3 durch
Upstream-Ableitung ersetzt (→ Übergreifende Konzepte, Abschnitt 7).

**Gesamtabschluss** nach allen Features: Voll-Lauf (`make test-e2e`), Ratchet-Update,
Dokumenten-Konsistenzprüfung (→ wf_test-iteration_guide.md Abschnitt 10).

---

## 2 Zusammenfassung

| # | ID | Feature | Pattern | Aufwand | L3-Referenz | Spec-Datei (Vorschlag) |
|---|---|---|---|---|---|---|
| 1 | E01 | Person/Familie anlegen | Theme-Loop + Form-Submit | Mittel | `AddRelationIntegrationTest` (6) | `person-family-create.spec.ts` |
| 2 | E02 | Fakten bearbeiten | Theme-Loop + Form-Submit | Mittel | `EditFactIntegrationTest` (3) | `fact-edit.spec.ts` |
| 3 | E03 | Rohdaten-Edit (Raw GEDCOM) | Admin-Only + Form-Submit | Niedrig | `EditRawGedcomIntegrationTest` (3) | `raw-gedcom-edit.spec.ts` |
| 4 | E04 | Nebenrecords (NOTE/SOUR/REPO) | Theme-Loop + Modal-Dialog | Mittel | `CreateSubrecordIntegrationTest` (4) | `subrecord-create.spec.ts` |
| 5 | E05 | Medienobjekte | Theme-Loop + Modal-Dialog | Mittel | `MediaObjectIntegrationTest` (3) | `media-object.spec.ts` |
| 6 | E06 | Sortierung (Reorder) | Admin-Only | Niedrig | `ReorderIntegrationTest` (4) | `reorder.spec.ts` |
| 7 | E08 | TomSelect & AutoComplete | Theme-Loop + JS-Widget | Hoch | `TomSelectIntegrationTest` (5) | `tomselect-autocomplete.spec.ts` |
| 8 | S05 | Erweiterte Suche (Felder) | Theme-Loop + Such-Ausführung | Mittel | `SearchIntegrationTest` (partiell) | `advanced-search-execution.spec.ts` ¹ |
| 9 | S06 | Erweiterte Suche (Datum-Modifikatoren) | Theme-Loop + Such-Ausführung | Mittel | `SearchIntegrationTest` (partiell) | `advanced-search-execution.spec.ts` ¹ |
| 10 | S07 | Phonetische Suche (Russell) | Theme-Loop + Such-Ausführung | Mittel | `SearchIntegrationTest` (partiell) | `phonetic-search-execution.spec.ts` ² |
| 11 | S08 | Phonetische Suche (Daitch-Mokotoff) | Theme-Loop + Such-Ausführung | Mittel | `SearchIntegrationTest` (partiell) | `phonetic-search-execution.spec.ts` ² |
| 12 | S10 | Paginierung (Suchergebnisse) | Theme-Loop + Such-Ausführung | Mittel | `SearchIntegrationTest` (partiell) | `search-pagination.spec.ts` |
| 13 | S16 | Chart: Beziehungsfinder | Theme-Loop + Chart-Rendering | Mittel | `RelationshipServiceIntegrationTest` (16) | `relationship-chart.spec.ts` |
| 14 | S18 | Charts: 5 fehlende Typen | Theme-Loop + Chart-Rendering | Niedrig | `ChartModuleIntegrationTest` (17) | `chart-types.spec.ts` |
| 15 | S41 | Statistikdaten-Abfragen | Theme-Loop + Chart-Rendering | Niedrig | `StatisticsDataIntegrationTest` (13) | `statistics-page.spec.ts` |
| 16 | S46 | Homepage-Blöcke | Theme-Loop | Niedrig | `BlockModuleIntegrationTest` (14) | `homepage-blocks.spec.ts` |
| 17 | S47 | Interaktiver Stammbaum | Theme-Loop + JS-Widget | Hoch | `InteractiveTreeIntegrationTest` (3) | `interactive-tree.spec.ts` |
| 18 | S50 | Hilfetexte | Theme-Loop | Niedrig | `HelpTextIntegrationTest` (13) | `help-texts.spec.ts` |
| 19 | P30 | Datensätze zusammenführen (Auswahl) | Admin-Only + Multi-Step | Hoch | `MergeRecordsIntegrationTest` (3) | `merge-records.spec.ts` ³ |
| 20 | P37 | Benutzer-Bearbeitung (Admin) | Admin-Only + Form-Submit | Mittel | `UserEditActionIntegrationTest` (7) | `user-edit-admin.spec.ts` |
| 21 | P38 | Account-Selbstverwaltung | Form-Submit | Mittel | `AccountSelfManagementIntegrationTest` (4) | `account-self-management.spec.ts` |
| 22 | P40 | Änderungsverwaltung (Workflow) | Multi-Step + Privacy-Role | Hoch | `PendingChangesIntegrationTest` (3) | `pending-changes.spec.ts` |
| 23 | P41 | Datensatz-Zusammenführung (vollständig) | Admin-Only + Multi-Step | Hoch | `MergeFactsActionIntegrationTest` (5) | `merge-records.spec.ts` ³ |
| 24 | A01 | Stammbaum-Management | Admin-Only + Form-Submit | Mittel | `TreeManagementIntegrationTest` (4) | `tree-management.spec.ts` |
| 25 | A04 | Stammbaum-Präferenzen | Admin-Only + Form-Submit | Niedrig | `TreePreferencesIntegrationTest` (3) | `tree-preferences.spec.ts` |
| 26 | A05 | Modul-Konfiguration | Admin-Only | Mittel | `ModuleConfigIntegrationTest` (7) | `module-configuration.spec.ts` |
| 27 | A07 | Benutzerverwaltung Admin | Admin-Only | Niedrig | `UserAdminIntegrationTest` (3) | `user-admin.spec.ts` |
| 28 | K01 | Kontaktformular | Theme-Loop + Form-Submit | Niedrig | keiner (Upstream-Ableitung) | `contact-form.spec.ts` |
| 29 | K02 | Benutzer-Nachrichten | Form-Submit | Niedrig | keiner (Upstream-Ableitung) | `user-messages.spec.ts` |

¹ S05 + S06 empfohlen als gemeinsame Spec-Datei (→ Übergreifende Konzepte, Abschnitt 8)
² S07 + S08 empfohlen als gemeinsame Spec-Datei
³ P30 + P41 empfohlen als gemeinsame Spec-Datei

**Aufwandsverteilung:** 8× Niedrig, 15× Mittel, 6× Hoch

---

## 3 Feature-Details

### Voraussetzung: S1 — Kontext lesen (einmalig)

| Schritt | Status | Quelle |
|---|---|---|
| S1: Kontext lesen | ⬜ | `docs/tp_overview_spec.md` → Doku-/Code-Vorgaben, Subdokumente |

---

### 3.1 Datenerfassung (E-Domäne)

#### E01: Person/Familie anlegen & verknüpfen

**L3-Referenz:** `AddRelationIntegrationTest` (6 Tests: AddChild POST→302, AddParent/AddSpouse/AddChild GET→200)
**Pattern:** Theme-Loop + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Mittel | **Spec-Datei:** `person-family-create.spec.ts`
**L4-Testidee:** Formular öffnen, Felder ausfüllen, Submit, neuer Record im Baum sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `AddChildToFamilyAction`, `AddParentToIndividualAction`, `AddSpouseToIndividualAction`; Views für Formulare |
| S3 | ⬜ | EP aus L3: POST→302 (Erfolg), GET→200 (Formular); Guards: Tree/Record-Existenz |
| S4 | ⬜ | Referenz-Spec: `records.spec.ts` (Personen-Seite), `individual.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/E01_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E02: Fakten bearbeiten

**L3-Referenz:** `EditFactIntegrationTest` (3 Tests: AddNewFact GET→200, unknown→redirect)
**Pattern:** Theme-Loop + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Mittel | **Spec-Datei:** `fact-edit.spec.ts`
**L4-Testidee:** Fakt hinzufügen auf Personenseite, Formular ausfüllen, Speichern, Fakt sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `EditFactAction`, `AddNewFact`; View: Fakt-Formular-Template |
| S3 | ⬜ | EP aus L3: GET→200 (Formular), unknown-Record→Redirect; Guards |
| S4 | ⬜ | Referenz-Spec: `individual.spec.ts` (Personen-Detailseite) |
| S5 | ⬜ | → `docs/systemtest/testspezi/E02_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E03: Rohdaten-Edit (Raw GEDCOM)

**L3-Referenz:** `EditRawGedcomIntegrationTest` (3 Tests)
**Pattern:** Admin-Only + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Niedrig | **Spec-Datei:** `raw-gedcom-edit.spec.ts`
**L4-Testidee:** Raw-Edit-Seite laden, GEDCOM editieren, Speichern, Änderung sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `EditRawFactAction`, `EditRawFactPage`; View: Textarea mit GEDCOM |
| S3 | ⬜ | EP aus L3: GET→200, POST→Redirect; Raw-GEDCOM-Validierung |
| S4 | ⬜ | Referenz-Spec: `individual.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/E03_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E04: Nebenrecords anlegen (NOTE/SOUR/REPO/SUBM)

**L3-Referenz:** `CreateSubrecordIntegrationTest` (4 Tests: Modal GET→200, Action POST→JSON-XREF)
**Pattern:** Theme-Loop + Modal-Dialog-Interaktion (→ Konzept 5)
**Aufwand:** Mittel | **Spec-Datei:** `subrecord-create.spec.ts`
**L4-Testidee:** Modal-Dialog öffnen, Note/Source anlegen, XREF zurück, Verknüpfung sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `CreateNoteModal`, `CreateSourceModal`, `CreateRepositoryModal`; Modale Views |
| S3 | ⬜ | EP aus L3: Modal GET→200, POST→JSON mit XREF; 4 Record-Typen |
| S4 | ⬜ | Referenz-Spec: `individual.spec.ts` (Edit-Buttons auf Personenseite) |
| S5 | ⬜ | → `docs/systemtest/testspezi/E04_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E05: Medienobjekte anlegen & verknüpfen

**L3-Referenz:** `MediaObjectIntegrationTest` (3 Tests: Modal, Link POST→302)
**Pattern:** Theme-Loop + Modal-Dialog-Interaktion (→ Konzept 5)
**Aufwand:** Mittel | **Spec-Datei:** `media-object.spec.ts`
**L4-Testidee:** Media-Modal öffnen, Datei wählen, verknüpfen, Media auf Person sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `CreateMediaObjectModal`, `LinkMediaToRecordAction`; Media-Upload-View |
| S3 | ⬜ | EP aus L3: Modal-Rendering, Link POST→302; Datei-Upload-Aspekt |
| S4 | ⬜ | Referenz-Spec: `upload-validation.spec.ts` (Datei-Upload-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/E05_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E06: Sortierung (Reorder)

**L3-Referenz:** `ReorderIntegrationTest` (4 Tests: Children/Names/Families GET→200)
**Pattern:** Admin-Only (→ wf_code-to-systemtest_guide.md 4.5)
**Aufwand:** Niedrig | **Spec-Datei:** `reorder.spec.ts`
**L4-Testidee:** Reorder-Seite laden, Reihenfolge ändern, Speichern, neue Reihenfolge sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `ReorderChildrenAction`, `ReorderNamesAction`, `ReorderFamiliesAction` |
| S3 | ⬜ | EP aus L3: 3 Reorder-Typen, alle GET→200; POST-Verhalten |
| S4 | ⬜ | Referenz-Spec: `individual.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/E06_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### E08: TomSelect & AutoComplete (Edit-Hilfs-APIs)

**L3-Referenz:** `TomSelectIntegrationTest` (5 Tests: Individual leer/XREF/Name, Source, Folder)
**Pattern:** Theme-Loop + JS-Widget-Interaktion (→ Konzept 2.1)
**Aufwand:** Hoch | **Spec-Datei:** `tomselect-autocomplete.spec.ts`
**L4-Testidee:** JS-Widget: Tippen, Dropdown erscheint, Eintrag wählen, Wert übernommen

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | TomSelect-JS-Integration, AJAX-Endpoints (`/tree/.../autocomplete/...`); Widget-Selektoren |
| S3 | ⬜ | EP aus L3: Leer-Eingabe, XREF-Eingabe, Namens-Eingabe, Source, Folder |
| S4 | ⬜ | Kein direktes Vorbild in bestehenden L4-Specs — neues Pattern |
| S5 | ⬜ | → `docs/systemtest/testspezi/E08_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; TomSelect-Selektoren theme-abhängig prüfen |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

### 3.2 Suche & Anzeige (S-Domäne)

#### S05: Erweiterte Suche (Felder)

**L3-Referenz:** `SearchIntegrationTest` (partiell: Name, Nachname, Multi-Feld — 8 Tests)
**Pattern:** Theme-Loop + Such-Ausführungs-Verification (→ Konzept 3)
**Aufwand:** Mittel | **Spec-Datei:** `advanced-search-execution.spec.ts` (shared mit S06)
**L4-Testidee:** Felder ausfüllen, Submit, Ergebnistabelle mit erwarteten Personen

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `SearchAdvancedPage`, `SearchAdvancedAction`; Formularfelder im View |
| S3 | ⬜ | EP aus L3: Name-Suche, Nachname-Suche, Multi-Feld-Suche; erwartete Treffer |
| S4 | ⬜ | Referenz-Spec: `search-forms.spec.ts` (nur Rendering), Abgrenzung beachten |
| S5 | ⬜ | → `docs/systemtest/testspezi/S05_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S06: Erweiterte Suche (Datum-Modifikatoren)

**L3-Referenz:** `SearchIntegrationTest` (partiell: Sterbedatum, +/-0/+/-5/+/-20 Jahre)
**Pattern:** Theme-Loop + Such-Ausführungs-Verification (→ Konzept 3)
**Aufwand:** Mittel | **Spec-Datei:** `advanced-search-execution.spec.ts` (shared mit S05)
**L4-Testidee:** Datumssuche mit Modifikatoren, Ergebnisse korrekt gefiltert

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Datum-Modifikator-Felder im View (±0, ±5, ±20 Jahre) |
| S3 | ⬜ | EP aus L3: BVA-Grenzwerte (+/-0, +/-5, +/-20); Treffer vs. Nicht-Treffer |
| S4 | ⬜ | Referenz-Spec: `search-forms.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/S06_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S07: Phonetische Suche (Russell)

**L3-Referenz:** `SearchIntegrationTest` (partiell: Russell Treffer + kein Treffer)
**Pattern:** Theme-Loop + Such-Ausführungs-Verification (→ Konzept 3, 3.1)
**Aufwand:** Mittel | **Spec-Datei:** `phonetic-search-execution.spec.ts` (shared mit S08)
**L4-Testidee:** Suchbegriff eingeben, phonetischer Treffer in Ergebnisliste

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `SearchPhoneticPage/Action`; Russell-Soundex-Modus im Formular |
| S3 | ⬜ | EP aus L3: Russell Treffer (phonetische Variante), kein Treffer (keine Übereinstimmung) |
| S4 | ⬜ | Referenz-Spec: `search-forms.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/S07_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Demo-GEDCOM auf passende Namen prüfen |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S08: Phonetische Suche (Daitch-Mokotoff)

**L3-Referenz:** `SearchIntegrationTest` (partiell: DM Treffer + kein Treffer)
**Pattern:** Theme-Loop + Such-Ausführungs-Verification (→ Konzept 3, 3.1)
**Aufwand:** Mittel | **Spec-Datei:** `phonetic-search-execution.spec.ts` (shared mit S07)
**L4-Testidee:** DM-Suche mit östeuropäischem Namens-Pattern

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `SearchPhoneticPage/Action`; DM-Soundex-Modus im Formular |
| S3 | ⬜ | EP aus L3: DM Treffer, kein Treffer; DM-spezifische Phonetik |
| S4 | ⬜ | Referenz-Spec: `search-forms.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/S08_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Demo-GEDCOM auf DM-geeignete Namen prüfen |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S10: Paginierung (Suchergebnisse)

**L3-Referenz:** `SearchIntegrationTest` (partiell: Limit, Offset, Offset+Limit — 3 Tests)
**Pattern:** Theme-Loop + Such-Ausführungs-Verification (→ Konzept 3, 3.2)
**Aufwand:** Mittel | **Spec-Datei:** `search-pagination.spec.ts`
**L4-Testidee:** Suche mit vielen Treffern, Paginierung sichtbar, Seitenwechsel, andere Ergebnisse

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Paginierungs-Controls im View: `.pagination`, Limit-Parameter, Offset |
| S3 | ⬜ | EP aus L3: BVA Limit/Offset — Grenzwerte (0, 1, max) |
| S4 | ⬜ | Referenz-Spec: `search-forms.spec.ts`, `source-list.spec.ts` (Listen-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S10_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; genügend Demo-Daten für Paginierung sicherstellen |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S16: Chart — Beziehungsfinder

**L3-Referenz:** `RelationshipServiceIntegrationTest` (16 Tests: direkte Pfade, Onkel, Großeltern, Ehepartner)
**Pattern:** Theme-Loop + Chart-Rendering-Verification (→ Konzept 6)
**Aufwand:** Mittel | **Spec-Datei:** `relationship-chart.spec.ts`
**L4-Testidee:** Chart laden, zwei Personen auswählen, Beziehungspfad angezeigt

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `RelationshipsChartPage`; View: Person-Auswahl + Pfad-Anzeige |
| S3 | ⬜ | EP aus L3: 16 Beziehungstypen; für L4 subset wählen (z. B. direkt, Onkel, Ehepartner) |
| S4 | ⬜ | Referenz-Spec: `pedigree.spec.ts` (Chart-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S16_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S18: Charts — 5 fehlende Typen

**L3-Referenz:** `ChartModuleIntegrationTest` (17 Tests: Timeline, Lifespan, FamilyBook, Relationships, Branches)
**Pattern:** Theme-Loop + Chart-Rendering-Verification (→ Konzept 6)
**Aufwand:** Niedrig | **Spec-Datei:** `chart-types.spec.ts`
**L4-Testidee:** Je Chart-Typ: Laden + sichtbarer Chart-Bereich (Smoke)

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Routen für: Timeline, Lifespan, FamilyBook, Descendants, Branches |
| S3 | ⬜ | EP aus L3: 5 Chart-Typen mit erwarteten Routen; für L4 Smoke reicht |
| S4 | ⬜ | Referenz-Spec: `pedigree.spec.ts` (Chart-Smoke-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S18_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; DataProvider-artig über Chart-Routen iterieren |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S41: Statistikdaten-Abfragen

**L3-Referenz:** `StatisticsDataIntegrationTest` (13 Tests)
**Pattern:** Theme-Loop + Chart-Rendering-Verification (→ Konzept 6)
**Aufwand:** Niedrig | **Spec-Datei:** `statistics-page.spec.ts`
**L4-Testidee:** Statistik-Seite laden, Diagramme/Tabellen sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `StatisticsPage`; View: Chart-Container, Tabellen |
| S3 | ⬜ | EP aus L3: 13 Statistik-Abfragen; für L4 Container-Sichtbarkeit prüfen |
| S4 | ⬜ | Referenz-Spec: `homepage.spec.ts` (Block-Rendering) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S41_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S46: Homepage-Blöcke

**L3-Referenz:** `BlockModuleIntegrationTest` (14 Tests: infoStyles DataProvider)
**Pattern:** Theme-Loop (→ wf_code-to-systemtest_guide.md 4.3)
**Aufwand:** Niedrig | **Spec-Datei:** `homepage-blocks.spec.ts`
**L4-Testidee:** Homepage, verschiedene Blöcke sichtbar (News, Statistics, etc.)

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Block-Konfiguration: Welche Blöcke sind auf der Demo-Homepage aktiv? |
| S3 | ⬜ | EP aus L3: 14 Block-Typen mit infoStyles; für L4 Block-Sichtbarkeit prüfen |
| S4 | ⬜ | Referenz-Spec: `homepage.spec.ts` (ergänzt um Blocktypen-Smoke) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S46_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S47: Interaktiver Stammbaum

**L3-Referenz:** `InteractiveTreeIntegrationTest` (3 Tests: getDetails→XREF, 'p'/'c'-Request→HTML)
**Pattern:** Theme-Loop + JS-Widget-Interaktion (→ Konzept 2.2)
**Aufwand:** Hoch | **Spec-Datei:** `interactive-tree.spec.ts`
**L4-Testidee:** Canvas/SVG-Widget laden, Knoten klicken, Detail-Panel zeigt Daten

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | JS-Modul: `InteractiveTreeModule`; Canvas vs. SVG ermitteln; AJAX-Endpoints |
| S3 | ⬜ | EP aus L3: getDetails→XREF, 'p'-Request (Parents), 'c'-Request (Children) |
| S4 | ⬜ | Kein direktes Vorbild in bestehenden L4-Specs — neues Pattern |
| S5 | ⬜ | → `docs/systemtest/testspezi/S47_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Widget-Rendering und Interaktion theme-abhängig prüfen |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### S50: Hilfetexte

**L3-Referenz:** `HelpTextIntegrationTest` (13 Tests: 12 Topics)
**Pattern:** Theme-Loop (→ wf_code-to-systemtest_guide.md 4.3)
**Aufwand:** Niedrig | **Spec-Datei:** `help-texts.spec.ts`
**L4-Testidee:** Hilfe-Icon klicken, Tooltip/Modal mit Text erscheint

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Help-Text-Endpoints; Hilfe-Icons in Views identifizieren |
| S3 | ⬜ | EP aus L3: 12 Topics mit erwarteten Texten; für L4 Icon→Popup prüfen |
| S4 | ⬜ | Referenz-Spec: ggf. `individual.spec.ts` (Seite mit Hilfe-Icons) |
| S5 | ⬜ | → `docs/systemtest/testspezi/S50_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

### 3.3 Datenpflege & Workflow (P-Domäne)

#### P30: Datensätze zusammenführen (Auswahl)

**L3-Referenz:** `MergeRecordsIntegrationTest` (3 Tests)
**Pattern:** Admin-Only + Mehrstufiger-Workflow (→ Konzept 4.1)
**Aufwand:** Hoch | **Spec-Datei:** `merge-records.spec.ts` (shared mit P41)
**L4-Testidee:** Merge-Seite, zwei XREFs eingeben, Vorschau angezeigt

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `MergeRecordsPage`, `MergeRecordsAction`; Formular für XREF-Eingabe |
| S3 | ⬜ | EP aus L3: XREF-Validierung, Vorschau-Rendering; Guards: Record-Existenz |
| S4 | ⬜ | Kein direktes Vorbild — neues Multi-Step-Pattern |
| S5 | ⬜ | → `docs/systemtest/testspezi/P30_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; mit P41 koordinieren (gemeinsame Spec-Datei) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### P37: Benutzer-Bearbeitung (Admin)

**L3-Referenz:** `UserEditActionIntegrationTest` (7 Tests: Duplikat-Email/Username, Self-Edit, Passwort)
**Pattern:** Admin-Only + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Mittel | **Spec-Datei:** `user-edit-admin.spec.ts`
**L4-Testidee:** Admin-User-Edit, Passwort/E-Mail ändern, Speichern, Auswirkung prüfen

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `UserEditAction`, `UserEditPage`; Formularfelder für PW/E-Mail |
| S3 | ⬜ | EP aus L3: Duplikat-E-Mail→Fehler, Duplikat-Username→Fehler, Self-Edit-Guard, PW-Änderung |
| S4 | ⬜ | Referenz-Spec: `auth.spec.ts` (Login-Flow), `access-control.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/P37_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### P38: Account-Selbstverwaltung

**L3-Referenz:** `AccountSelfManagementIntegrationTest` (4 Tests: Edit→200, Update E-Mail, Delete-Guard)
**Pattern:** Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Mittel | **Spec-Datei:** `account-self-management.spec.ts`
**L4-Testidee:** Account-Seite, E-Mail ändern, Speichern, neue E-Mail bestätigt

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `AccountUpdate`, `AccountEdit`; Formular: E-Mail, Passwort, Sprache |
| S3 | ⬜ | EP aus L3: Edit-Seite→200, E-Mail-Update→Redirect, Delete-Guard |
| S4 | ⬜ | Referenz-Spec: `user-pages.spec.ts` (User-Seiten-Rendering) |
| S5 | ⬜ | → `docs/systemtest/testspezi/P38_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### P40: Änderungsverwaltung (Workflow)

**L3-Referenz:** `PendingChangesIntegrationTest` (3 Tests: Accept/Reject/GET→200)
**Pattern:** Mehrstufiger-Workflow + Privacy-Role (→ Konzept 4.2)
**Aufwand:** Hoch | **Spec-Datei:** `pending-changes.spec.ts`
**L4-Testidee:** Editor erzeugt Pending Change, Moderator Accept, Änderung übernommen

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `PendingChangesAcceptChange`, `PendingChangesRejectChange`, `PendingChanges`; Rollen-Anforderungen |
| S3 | ⬜ | EP aus L3: Accept→Änderung wirkt, Reject→Änderung verworfen, Seite→200 |
| S4 | ⬜ | Referenz-Spec: `access-control.spec.ts` (Rollen-Pattern), `privacy-roles` Helper |
| S5 | ⬜ | → `docs/systemtest/testspezi/P40_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Login-Wechsel Editor→Moderator |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### P41: Datensatz-Zusammenführung (vollständig)

**L3-Referenz:** `MergeFactsActionIntegrationTest` (5 Tests)
**Pattern:** Admin-Only + Mehrstufiger-Workflow (→ Konzept 4.1)
**Aufwand:** Hoch | **Spec-Datei:** `merge-records.spec.ts` (shared mit P30)
**L4-Testidee:** Merge ausführen, ein Record bleibt, einer ist weg

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `MergeFactsAction`; Vorschau→Bestätigung→Ausführung |
| S3 | ⬜ | EP aus L3: 5 Fakten-Merge-Szenarien; Postcondition: Record-Zustand |
| S4 | ⬜ | Gemeinsames Pattern mit P30 |
| S5 | ⬜ | → `docs/systemtest/testspezi/P41_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; mit P30 koordinieren (gemeinsame Spec-Datei) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

### 3.4 Administration (A-Domäne)

#### A01: Stammbaum-Management

**L3-Referenz:** `TreeManagementIntegrationTest` (4 Tests: Create Dup→302, Create Neu→DB, Delete→204, ManageTrees→200)
**Pattern:** Admin-Only + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Mittel | **Spec-Datei:** `tree-management.spec.ts`
**L4-Testidee:** Admin-ManageTrees-Seite, neuen Baum anlegen, Baum in Liste sichtbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `CreateTreeAction`, `DeleteTreeAction`, `ManageTrees`; Admin-Route |
| S3 | ⬜ | EP aus L3: Duplikat-Name→302, Neuer Baum→DB-Eintrag, Delete→204 |
| S4 | ⬜ | Referenz-Spec: `upload-validation.spec.ts` (Admin-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/A01_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Cleanup nach Test (erstellten Baum löschen) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### A04: Stammbaum-Präferenzen

**L3-Referenz:** `TreePreferencesIntegrationTest` (3 Tests: GET→200, POST→Preference saved)
**Pattern:** Admin-Only + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Niedrig | **Spec-Datei:** `tree-preferences.spec.ts`
**L4-Testidee:** Präferenzen-Seite laden, Einstellung ändern, Speichern, Einstellung wirkt

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `TreePreferencesPage`, `TreePreferencesAction`; Formularfelder |
| S3 | ⬜ | EP aus L3: GET→200, POST→Preference gespeichert |
| S4 | ⬜ | Referenz-Spec: `upload-validation.spec.ts` (Admin-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/A04_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### A05: Modul-Konfiguration

**L3-Referenz:** `ModuleConfigIntegrationTest` (7 Tests: All/Analytics/Blocks/Charts/Menus/Reports→200)
**Pattern:** Admin-Only (→ wf_code-to-systemtest_guide.md 4.5)
**Aufwand:** Mittel | **Spec-Datei:** `module-configuration.spec.ts`
**L4-Testidee:** Module-Admin-Seite, Modul deaktivieren, Speichern, Modul nicht mehr im Frontend

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `ModulesAllPage`, Typ-spezifische Seiten; Aktivieren/Deaktivieren-Formular |
| S3 | ⬜ | EP aus L3: 7 Modul-Typ-Seiten→200; für L4 Deaktivieren→Frontend-Effekt prüfen |
| S4 | ⬜ | Referenz-Spec: `upload-validation.spec.ts` (Admin-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/A05_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; nach Test Modul wieder aktivieren (Cleanup) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### A07: Benutzerverwaltung Admin

**L3-Referenz:** `UserAdminIntegrationTest` (3 Tests: UserList, Filter, Cleanup)
**Pattern:** Admin-Only (→ wf_code-to-systemtest_guide.md 4.5)
**Aufwand:** Niedrig | **Spec-Datei:** `user-admin.spec.ts`
**L4-Testidee:** Admin-User-Liste, Filter funktioniert, Cleanup-Seite erreichbar

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `UserListPage`; Filter-Formular, Cleanup-Link |
| S3 | ⬜ | EP aus L3: User-Liste→200, Filter→gefilterte Liste, Cleanup→200 |
| S4 | ⬜ | Referenz-Spec: `upload-validation.spec.ts` (Admin-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/A07_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

### 3.5 Kommunikation (K-Domäne)

#### K01: Kontaktformular

**L3-Referenz:** keiner (Upstream-Ableitung → Konzept 7)
**Pattern:** Theme-Loop + Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Niedrig | **Spec-Datei:** `contact-form.spec.ts`
**L4-Testidee:** Kontaktformular laden, ausfüllen, senden, Bestätigungsmeldung

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `ContactPage`, `ContactAction`; View: Formularfelder, Pflichtfelder |
| S3 | ⬜ | **Upstream-Ableitung:** Handler-Guards, Validierung, Erfolgs-/Fehlerpfade direkt aus Code |
| S4 | ⬜ | Referenz-Spec: `login.spec.ts` (Formular-Pattern) |
| S5 | ⬜ | → `docs/systemtest/testspezi/K01_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; E-Mail-Versand nicht prüfbar (→ nur Redirect/Bestätigung) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

#### K02: Benutzer-Nachrichten

**L3-Referenz:** keiner (Upstream-Ableitung → Konzept 7)
**Pattern:** Formular-Submit-Verification (→ Konzept 1)
**Aufwand:** Niedrig | **Spec-Datei:** `user-messages.spec.ts`
**L4-Testidee:** Als User einloggen, Nachricht senden, Bestätigungsmeldung

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | Handler: `MessagePage`, `MessageAction`; View: Empfänger, Betreff, Text |
| S3 | ⬜ | **Upstream-Ableitung:** Handler-Guards, Validierung, Auth-Anforderung direkt aus Code |
| S4 | ⬜ | Referenz-Spec: `login.spec.ts`, `user-pages.spec.ts` |
| S5 | ⬜ | → `docs/systemtest/testspezi/K02_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung; Login erforderlich (kein Visitor-Zugriff) |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md` |
| S8 | ⬜ | Einzeltest grün, Konsistenz |

---

## 4 Gesamtabschluss

Nach Abschluss aller 29 Features (→ wf_test-iteration_guide.md Abschnitt 10):

| Schritt | Status | Beschreibung |
|---|---|---|
| GA1 | ⬜ | Voll-Lauf: `make test-e2e` — alle L4-Tests grün |
| GA2 | ⬜ | Ratchet-Update: `docs/tp_ratchet_spec.md` — Teststufe-3-Endekriterien |
| GA3 | ⬜ | Dokumenten-Konsistenzprüfung: CLAUDE.md, README.md, Coverage-Ref, Conditions-Ref |
| GA4 | ⬜ | Commit (manuell) |
