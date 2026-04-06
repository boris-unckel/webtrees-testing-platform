<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyse-Prompt: Testqualität verbessern — Neue Features (E/A/K/G30/S52/S53/P38–41/SEC-UTL01)

## Aufgabe

Führe eine systematische Testanalyse für alle **29 neu erfassten, noch nicht abgedeckten Features** durch, die im April 2026 in `docs/testing-bigpicture.md` ergänzt wurden. Ergebnis sind drei Ausgabedateien (common2, plan2, je Feature eine Datei). **Noch kein Test-Code schreiben.** Das ist ausschließlich Analyse und Planung (Phasen P1 + P2 je Feature).

**Scope:** Ausschließlich die neuen Features (E01–E08, A01–A11, K01–K02, G30, S52, S53, P38–P41, SEC-UTL01). Die abgeschlossenen Features (G25–G29, S41–S50, P30–P37, SEC-BOT01) sind fertig und werden nicht re-analysiert.

---

## Eingabedaten — zuerst lesen

| Datei | Zweck |
|---|---|
| `docs/testing-bigpicture.md` | Maßgebliche Feature-Beschreibungen, SUT-Klassen, Teststufen, Prioritäten |
| `docs/testquality_improve_common.md` | Bestehende Methodiken (EP, BVA, Mock-Infrastruktur, Aufwandskategorien) — nicht duplizieren |
| `docs/testquality_improve_plan.md` | Referenz für Planformat (Phasen P1–P5, Tabellenstruktur, Randbedingungen) |
| `upstream/webtrees/app/Http/RequestHandlers/` | SUT-Quellcode — Handler-Klassen für Branch-Analyse |
| `upstream/webtrees/app/Services/` | SUT-Serviceklassen (MediaFileService für G30, etc.) |
| `upstream/webtrees/app/Auth.php` | Auth-Infrastruktur — wie Rollen/Rechte im SUT geprüft werden |
| `layer3-integration/tests/` | Bestehende Testklassen — Setup-Patterns, verfügbare Fixtures, DI-Container-Konfiguration |
| `layer3-integration/tests/MysqlTestCase.php` | Basis-Testklasse — setUp/tearDown, Tree-Fixture, Auth-Setup |

---

## Features im Scope

### Teststufe-2-taugliche Features (Komponentenintegrationstest via PHPUnit)

| Ref | Feature | Primäre SUT-Klassen | Prio | Aufwand (Schätzung) |
|---|---|---|---|---|
| **G30** | Mediendatei-Upload (HTTP-Formular) | `UploadMediaPage`, `UploadMediaAction` | Mittel | Mittel |
| **S52** | Standortdaten-Verwaltung (CRUD) | `MapDataList`, `MapDataAdd`, `MapDataEdit`, `MapDataSave`, `MapDataDelete`, `MapDataDeleteUnused`, `MapDataExportCSV` | Niedrig | Mittel |
| **P38** | Account-Selbstverwaltung | `AccountEdit`, `AccountUpdate`, `AccountDelete` | Mittel | Mittel |
| **P39** | Authentifizierung-Aktionen | `LoginAction`, `Logout`, `RegisterAction`, `PasswordRequestAction`, `PasswordResetAction`, `VerifyEmail` | Hoch | Hoch |
| **P40** | Änderungsverwaltung (HTTP-Handler) | `PendingChanges`, `PendingChangesAcceptChange`, `PendingChangesAcceptRecord`, `PendingChangesRejectChange`, `PendingChangesRejectRecord` | Hoch | Mittel |
| **P41** | Datensatz-Zusammenführung (vollständig) | `MergeRecordsPage`, `MergeRecordsAction` | Mittel | Mittel |
| **SEC-UTL01** | Web-Assets & Utility-Endpoints | `RobotsTxt`, `FaviconIco`, `WebmanifestJson`, `BrowserconfigXml`, `AppleTouchIconPng`, `AdsTxt`, `AppAdsTxt`, `Ping` | Niedrig | Niedrig |
| **E01** | Person/Familie anlegen & verknüpfen | `AddChildToIndividual*`, `AddParentToIndividual*`, `AddSpouseToIndividual*`, `LinkSpouseToIndividual*`, `AddChildToFamily*`, `AddSpouseToFamily*`, `LinkChildToFamily*` | Hoch | Hoch |
| **E02** | Fakten bearbeiten | `EditFactPage`, `AddNewFact`, `DeleteFact`, `CopyFact`, `PasteFact`, `SelectNewFact` | Hoch | Mittel |
| **E03** | Rohdaten-Edit (Raw GEDCOM) | `EditRawFactPage`, `EditRawFactAction`, `EditRawRecordPage`, `EditRawRecordAction`, `EditRecordPage`, `EditRecordAction` | Mittel | Mittel |
| **E04** | Nebenrecords anlegen | `CreateNoteModal`, `CreateNoteAction`, `EditNotePage`, `EditNoteAction`, `CreateSourceModal`, `CreateSourceAction`, `CreateRepositoryModal`, `CreateRepositoryAction`, `CreateSubmissionModal`, `CreateSubmitterModal` | Mittel | Mittel |
| **E05** | Medienobjekte anlegen & verknüpfen | `CreateMediaObjectModal`, `CreateMediaObjectAction`, `CreateMediaObjectFromFileAction`, `AddMediaFileModal`, `AddMediaFileAction`, `LinkMediaToRecordAction`, `LinkIndividualToMediaModal`, `LinkFamilyToMediaModal`, `LinkSourceToMediaModal` | Mittel | Mittel |
| **E06** | Sortierung (Reorder) | `ReorderChildrenPage`, `ReorderNamesPage`, `ReorderFamiliesPage`, `ReorderMediaPage`, `ReorderMediaAction`, `ReorderMediaFilesPage`, `ReorderMediaFilesAction` | Niedrig | Niedrig |
| **E07** | Mediendatei-Download & Thumbnail | `MediaFileDownload`, `MediaFileThumbnail` | Mittel | Niedrig |
| **E08** | TomSelect & AutoComplete (Edit-Hilfs-APIs) | `TomSelectIndividual`, `TomSelectMediaObject`, `TomSelectSource`, `TomSelectRepository`, `TomSelectNote`, `TomSelectSharedNote`, `AutoCompleteCitation`, `AutoCompleteFolder` | Niedrig | Niedrig |
| **A01** | Stammbaum-Management | `CreateTreePage`, `CreateTreeAction`, `DeleteTreeAction`, `ManageTrees`, `MergeTreesPage`, `MergeTreesAction` | Hoch | Mittel |
| **A02** | Stammbaum-Import (HTTP-Formular) | `ImportGedcomPage`, `ImportGedcomAction` | Hoch | Mittel |
| **A03** | Stammbaum-Export (HTTP-Formular) | `ExportGedcomPage`, `ExportGedcomClient`, `ExportGedcomServer` | Mittel | Mittel |
| **A04** | Stammbaum-Präferenzen | `TreePreferencesPage`, `TreePreferencesAction` | Mittel | Niedrig |
| **A05** | Modul-Konfiguration | `ModulesAllPage`, `ModulesAllAction`, `ModulesAnalyticsPage`, `ModulesBlocksPage`, `ModulesChartsPage`, `ModulesFootersPage`, `ModulesHistoricEventsPage`, `ModulesLanguagesPage`, `ModulesMapsPage`, `ModulesMenusPage`, `ModulesReportsPage`, `ModulesSidebarsPage`, `ModulesTabsPage`, `ModulesThemesPage` (und je …Action) | Niedrig | Mittel |
| **A06** | Site-Präferenzen | `SitePreferencesPage`, `SitePreferencesAction` | Mittel | Niedrig |
| **A07** | Benutzerverwaltung Admin | `UserListPage`, `UsersCleanupPage`, `UsersCleanupAction` | Mittel | Niedrig |
| **A08** | Medienverwaltung Admin | `AdminMediaFileDownload`, `AdminMediaFileThumbnail`, `FixLevel0MediaPage`, `FixLevel0MediaAction`, `ManageMediaPage`, `ManageMediaAction` | Niedrig | Niedrig |
| **A09** | Datenpflege-Werkzeuge | `DataFixPage`, `DataFixChoose`, `DataFixSelect`, `DataFixUpdate`, `CleanDataFolder`, `FindDuplicateRecords`, `AddUnlinkedPage`, `AddUnlinkedAction` | Niedrig | Hoch |
| **A10** | Protokolle & Monitoring | `PendingChangesLogPage`, `PendingChangesLogData`, `PendingChangesLogAction`, `PendingChangesLogDelete`, `PendingChangesLogDownload`, `SiteLogsDownload`, `PhpInformation` | Niedrig | Niedrig |
| **A11** | System & Upgrade | `UpgradeWizardPage`, `UpgradeWizardConfirm`, `CheckForNewVersionNow`, `Masquerade`, `BroadcastPage`, `BroadcastAction`, `EmailPreferencesPage`, `EmailPreferencesAction` | Niedrig | Hoch |

### Nur Teststufe 3 — EXCLUDED für Komponentenintegrationstest

| Ref | Feature | Ausschlussgrund |
|---|---|---|
| **S53** | Legacy-URL-Weiterleitungen | Teststufe 3 only — 27 Redirect*-Handler leiten auf aktuelle Routen um; kein fachlicher Mehrwert in PHPUnit (nur HTTP 301/302 prüfen, was Playwright besser abbildet) |
| **K01** | Kontaktformular | Teststufe 3 only — SMTP-Abhängigkeit; kein Mailserver im Test-Stack; ContactAction sendet E-Mail, deren Empfang in PHPUnit nicht prüfbar |
| **K02** | Benutzer-Nachrichten | Teststufe 3 only — Gleicher Grund wie K01; MessageAction sendet E-Mail |

Diese drei erhalten in `plan2.md` einen Eintrag mit Status `🚫 EXCLUDED` und kurzer Begründung. Individuelle Feature-Dateien werden für S53, K01, K02 erstellt (Minimalformat: nur Header, Status quo, EXCLUDED-Begründung, keine EP-Analyse).

---

## Ausgabedateien

### 1. `docs/testquality_improve_common2.md`

Neue übergreifende Konzepte, die spezifisch für die neuen Domains sind und in `testquality_improve_common.md` noch nicht vorkommen. Nur neue Konzepte — Bestehendes aus `testquality_improve_common.md` wird referenziert, nicht kopiert.

**Pflichtabschnitte:**

**Abschnitt 1 — Auth-Kontext in Komponentenintegrationstests (E/A/P38–P41)**

Handlers der Domains E und A benötigen eingeloggte Nutzer mit `PRIV_NONE` oder Admin-Rechten. Analysiere `MysqlTestCase` und bestehende Integrationstests (insbesondere `UserEditActionIntegrationTest`, `TreePrivacyActionIntegrationTest`), um zu verstehen, wie der Auth-Kontext in Tests hergestellt wird (`Auth::login()`, `Auth::setUser()` o.ä.). Dokumentiere das Muster.

**Abschnitt 2 — PSR-7 UploadedFile für HTTP-Datei-Uploads (G30, A02)**

G30 (`UploadMediaAction`) und A02 (`ImportGedcomAction`) empfangen Datei-Uploads via POST. Zeige, wie ein `Psr\Http\Message\UploadedFileInterface`-Objekt in PHPUnit-Tests erzeugt und in den PSR-7 Request eingebaut wird. Prüfe bestehende Tests auf ähnliche Patterns (ggf. in `GedcomLoadIntegrationTest` für CLI-Import, aber das ist CLI, kein PSR-7-Upload).

**Abschnitt 3 — Batch-Handler-Strategie für große Feature-Gruppen (E01, E05, A05)**

E01 hat ~14 Handler-Klassen, E05 ~9, A05 ~28 Seiten + Action-Handler. Strategie: Pro Handler-Klasse einen Smoke-Test (GET→200 / POST→Redirect), zusätzlich EP/BVA für die 2–3 fachlich wichtigsten Handler der Gruppe. Formuliere die Entscheidungsregel: Wann reicht Smoke, wann ist EP nötig?

**Abschnitt 4 — Session-State-Einschränkungen (P39, A11 Masquerade)**

`LoginAction` verändert Session-State. PHPUnit-Tests laufen ohne echte HTTP-Session. Analysiere, ob `Auth::*`-Methoden den DI-Container nutzen und damit in Tests mockbar sind. Wenn nicht: welche Branches sind trotzdem testbar (z.B. Fehlerpfade ohne Session-Änderung), welche müssen EXCLUDED werden.

**Abschnitt 5 — Neue dauerhafte Ausschluss-Kandidaten**

Liste alle Branches/Features, die beim SUT-Lesen als nicht testbar in Teststufe 2 identifiziert werden (analog zu G27 und SEC-BOT01-DNS in `testquality_improve_common.md` Abschnitt 10). Format: Tabelle mit SUT, Branch, Grund.

---

### 2. `docs/testquality_improve_plan2.md`

Gesamtplan analog zu `testquality_improve_plan.md`, aber für die neuen Features.

**Pflichtabschnitte:**

1. **Header:** Scope (29 Refs: 26 aktiv + 3 EXCLUDED), Verweis auf beide common-Dateien
2. **Arbeitsablauf je Referenz-ID (5 Phasen):** Nicht wiederholen — nur mit `→ [testquality_improve_plan.md](testquality_improve_plan.md)` auf den bestehenden Abschnitt verweisen
3. **Randbedingungen:** Gleich wie in `testquality_improve_plan.md` — kurz zusammenfassen, auf Original verweisen
4. **Gesamtstatus-Tabelle:** Alle 29 Refs, Spalten: Ref | Titel | Aufwand | P1 | P2 | P3 | P4 | P5 — alle ⬜ OPEN bzw. 🚫 EXCLUDED
5. **Empfohlene Reihenfolge (Runden):** Mindestens 3 Runden:
   - Runde 1 — Quick Wins (Niedrig/Mittel, klar testbar): z.B. SEC-UTL01, E07, E08, A04, A06, A07, A10, P40
   - Runde 2 — Mittlere Komplexität: z.B. P38, P41, A01–A03, E02–E06, G30, S52
   - Runde 3 — Hoch / eingeschränkt testbar: z.B. P39 (Auth), E01 (viele Handler), A05 (46 Handler), A09, A11
6. **Abschluss:** Voll-Lauf + CRAP-Neubewertung

**Priorisierungs-Kriterien für Reihenfolge:**
- Priorität aus Feature-Matrix (Hoch > Mittel > Niedrig)
- Aufwand (Niedrig/Mittel vor Hoch)
- Abhängigkeiten beachten: A01 (CreateTree) vor A02 (Import), weil Import einen Baum braucht
- P39 (LoginAction) hat Session-Einschränkungen → erst nach Auth-Analyse einplanen

---

### 3. `docs/testquality_improve_<REFERENZ>.md` (29 Einzeldateien)

**Format für Teststufe-2-Features (26 Dateien):**

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — <REFERENZ>: <Feature-Name>

**Referenz:** <REFERENZ> | **SUT:** `app/Http/RequestHandlers/<Handler>.php` (+ ggf. weitere Klassen)
**Aktueller Test:** <Testklassenname falls vorhanden, sonst "kein Test — neu anlegen">
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), ggf. [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

[Gibt es bereits Tests in RequestHandlerBatchA/B oder anderen Klassen? Wenn ja: welche Methoden, welche Qualitätsstufe (Smoke vs. EP)?]

---

## SUT-Kernbefunde

[Für jeden relevanten Handler oder jede Klasse: Branch-Tabelle mit Bedingung + "Bisher getestet?" — analog zu bestehenden Einzeldokumenten wie testquality_improve_G29.md im Git-Verlauf]

Vorlage:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Guard: Record nicht gefunden | ... | ❌ |
| Happy Path | ... | ❌ |
| ... | | |

---

## Äquivalenzklassen (EP)

[EP-Matrix. Wenn ein Feature viele Handler hat: nur für die wichtigsten (Hoch-Prio-Handlers) ausarbeiten; für die restlichen reicht "Smoke-Test (GET → 200)" als Beschreibung]

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ... | ... |

---

## Grenzwerte (BVA)

[Nur wenn sinnvoll — bei reinen Guard-Handlern ohne numerische Grenzen weglassen]

---

## Empfohlene Strategie

[ISTQB B / C / Hybrid. Neue Testklasse anlegen oder bestehende erweitern? Welche Fixtures werden benötigt?]

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
```

**Format für EXCLUDED-Features (3 Dateien: S53, K01, K02):**

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — <REFERENZ>: <Feature-Name>

**Referenz:** <REFERENZ> | **Status:** 🚫 EXCLUDED — Teststufe 2 nicht anwendbar
**Übergreifende Konzepte:** → [testquality_improve_common2.md](testquality_improve_common2.md)

## Ausschlussgrund

[1–3 Sätze: Warum ist Teststufe 2 für dieses Feature nicht sinnvoll/möglich? Welche Teststufe deckt es ab?]

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | 🚫 | Teststufe 3 only |
```

---

## Analyse-Leitfaden (Hinweise für die Ausführung)

### Umgang mit großen Feature-Gruppen

**E01 (~14 Handler), E05 (~9 Handler), A05 (~28 Seiten + Actions):**

Nicht alle Handler gleich tief analysieren. Vorgehen:
1. Alle Handler der Gruppe grob scannen: Folgen sie einem einheitlichen Pattern?
2. Repräsentativen Handler auswählen (derjenige mit der komplexesten Logik oder dem höchsten CRAP-Score, falls bekannt)
3. Repräsentativen Handler vollständig per EP/BVA analysieren
4. Für die übrigen Handler: "Smoke-Test (GET → 200 / POST → Redirect 302)" als Strategie eintragen
5. Gemeinsame Guard-Patterns in `common2.md` Abschnitt 3 dokumentieren statt in jedem Einzeldokument wiederholen

**A05 (Modul-Konfiguration, ~46 Handler):**
Analog zu `HelpTextIntegrationTest` (S50): Ein DataProvider mit allen Modul-Konfigurationsseiten-URLs, ein einzelner `test_module_config_page_returns_200($url)`. Für `ModulesAllAction` (Aktivieren/Deaktivieren) zusätzlich ein EP.

### Auth-Kontext: Wie bestehende Tests ihn setzen

Bevor die Auth-Abschnitte in common2.md und den Einzeldokumenten geschrieben werden: `MysqlTestCase.php` und mindestens zwei bestehende Tests lesen (z.B. `UserEditActionIntegrationTest`, `TreePrivacyActionIntegrationTest`), um das exakte Pattern zu verstehen. Nicht raten.

### Session-State und P39 (LoginAction)

`LoginAction::handle()` schreibt in `$_SESSION`. PHPUnit läuft ohne echten Browser. Prüfe:
- Hat webtrees einen `SessionInterface` im DI-Container, der mockbar wäre?
- Falls nein: Welche Branches in `LoginAction` sind session-unabhängig? (z.B. falsche Credentials → keine Session-Schreibung → testbar)
- Dokumentiere ehrlich, was testbar ist und was nicht. EXCLUDED-Branches in `common2.md` Abschnitt 5 eintragen.

### G30 vs. G27 (bereits EXCLUDED)

G27 wurde EXCLUDED weil `GuzzleHttp\Client` direkt instanziiert wird (kein DI). G30 (`UploadMediaAction`) verwendet einen anderen Pfad. Prüfe den Konstruktor von `UploadMediaAction`: Welche Abhängigkeiten hat er? Wenn die Datei-Speicherung über ein Interface oder Service geht, das im DI-Container austauschbar ist → testbar. Wenn direkt auf Filesystem geschrieben wird → `vfsStream` evaluieren (→ `testquality_improve_common.md` Abschnitt 5.2).

### P40 (Änderungsverwaltung) — direkter DB-Ansatz

`PendingChangesAcceptRecord` und `PendingChangesRejectRecord` schreiben in die `change`-Tabelle. Diese ist direkt über `DB::table('change')` prüfbar — wie bei bestehenden Tests (G28, P34). Strategie: Precondition (pending change in change-Tabelle anlegen), Action (Handler aufrufen), Postcondition (Status 'accepted'/'rejected' in change-Tabelle prüfen).

### SEC-UTL01 — Minimalaufwand

Alle 7–8 Handler geben nur Content-Type + 200 zurück. Ein DataProvider über alle Handler-Instanzen reicht. Aufwand: Niedrig.

---

## Formale Anforderungen

- **SPDX-Header** auf jede neue `.md`-Datei: erste Zeile `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->`
- **Referenz-IDs** exakt wie in `testing-bigpicture.md`: E01–E08, A01–A11, K01–K02, G30, S52, S53, P38–P41, SEC-UTL01
- **Aufwandskategorien** aus `testquality_improve_common.md` Abschnitt 8 (Niedrig/Mittel/Hoch)
- **Keine Code-Dateien erstellen** — ausschließlich `.md`-Dokumente
- **Keine Änderungen** an `docs/testing-bigpicture.md`, `docs/testquality_improve_common.md`, `docs/testquality_improve_plan.md`, oder bestehenden Testklassen
- **Keine Analyse** der bereits abgeschlossenen Features G25–G29, S41–S50, P30–P37, SEC-BOT01

---

## Reihenfolge der Ausführung

1. `docs/testquality_improve_common2.md` zuerst erstellen (andere Dokumente verweisen darauf)
2. Alle 29 Einzeldokumente `docs/testquality_improve_<REF>.md` erstellen (können parallel erarbeitet werden, da unabhängig voneinander)
3. `docs/testquality_improve_plan2.md` zuletzt erstellen (setzt vollständige Aufwandsschätzungen aus den Einzeldokumenten voraus)
