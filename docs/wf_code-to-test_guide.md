<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Workflow: Code-Analyse → Testkonzept → Implementierung

Dieser Leitfaden beschreibt, wie Testziele durch systematische Analyse des
Upstream-Codes (Handler, Services) identifiziert werden. Er leitet den Qualitätssprung
von **strukturbasiert** (CRAP-Analyse, Coverage-getrieben) auf **spezifikationsbasiert**
(ISTQB B/C, EP/BVA-getrieben) ein.

Für Methodik (EP/BVA, Mock-Infrastruktur, Patterns), den 5-Phasen-Arbeitsablauf und
die Abschlussschritte: → [Gemeinsamer Workflow](wf_test-iteration_guide.md)

---

## 1 Übersicht

### Qualitätssprung: Strukturbasiert → Spezifikationsbasiert

| Vorher | Nachher |
|---|---|
| CRAP-Score als Testziel-Auswahl | Anforderungen / Verhalten als Testziel |
| Abbruchkriterium: kein Exception / kein HTTP 500 | Abbruchkriterium: EP/BVA-Matrix vollständig abgedeckt |
| Nur Happy-Path | Happy-Path + negative Pfade + Guards + Grenzfälle |
| Keine Pre-/Postconditions | Datenbankzustand vor/nach Aktion geprüft |

### Eingangsmaterial je Iteration

- **CRAP-Report** (`make crap-report`): Methoden mit CRAP > 100 bei 0% Coverage als Startpunkt.
- **Feature-Matrix** (`docs/tds_conditions_ref.md`): Feature-Referenz-IDs und SUT-Klassen.
- **Abdeckungsmatrix** (`docs/tds_coverage_ref.md`): Bestehende Testklassen und -anzahlen.

---

## 2 Ablauf

| Schritt | Zweck | Ausführung |
|---|---|---|
| 1 | Feature-Analyse: SUT-Code lesen, Branches identifizieren, EP/BVA ableiten | Einmalig pro Iterations-Serie |
| 2 | Detailkonzepte erstellen (→ Vorlage Abschnitt 4) | Nach Schritt 1 |
| 3 | Tests implementieren: → [5-Phasen-Arbeitsablauf](wf_test-iteration_guide.md#7-arbeitsablauf-je-feature-5-phasen) | Nach Schritt 2 |
| 4 | Abschluss: → [Voll-Lauf, Ratchet, Commit](wf_test-iteration_guide.md#10-abschluss-voll-lauf-ratchet-konsistenzprüfung-commit) | Nach allen Features |

---

## 3 Vorlage: Analyse-Prompt

Der folgende Prompt wird zu Beginn jeder neuen Iterations-Serie verwendet, um die Analyse für eine Menge neuer Features systematisch durchzuführen. Er ist als Eingabe für ein AI-gestütztes Analysewerkzeug gedacht.

---

### Prompt-Template

```
## Aufgabe

Führe eine systematische Testanalyse für alle <ANZAHL> neu erfassten, noch nicht abgedeckten
Features durch, die in der Feature-Matrix (`docs/tds_conditions_ref.md`) identifiziert wurden.
Ergebnis sind drei Ausgabetypen:
1. Übergreifende-Konzepte-Datei (nur neue, iterationsspezifische Patterns)
2. Umsetzungsplan-Datei (Gesamtstatus, Reihenfolge)
3. Je Feature eine Detailkonzept-Datei

**Noch kein Test-Code schreiben.** Ausschließlich Analyse und Planung (Phasen P1 + P2 je Feature).

## Eingabedaten — zuerst lesen

| Datei | Zweck |
|---|---|
| `docs/tds_conditions_ref.md` | Feature-Beschreibungen, SUT-Klassen, Teststufen, Prioritäten |
| `docs/wf_test-iteration_guide.md` | Bestehende Methodiken — nicht duplizieren |
| `upstream/webtrees/app/Http/RequestHandlers/` | SUT-Quellcode — Handler-Klassen für Branch-Analyse |
| `upstream/webtrees/app/Services/` | SUT-Serviceklassen |
| `layer3-integration/tests/` | Bestehende Testklassen — Setup-Patterns, Fixtures, DI |
| `layer3-integration/tests/MysqlTestCase.php` | Basis-Testklasse |

## Features im Scope

[Auflistung der Feature-Referenz-IDs und deren SUT-Klassen]

## Ausgabedateien

### 1. Übergreifende Konzepte (iterationsspezifisch)

Neue Konzepte, die in `wf_test-iteration_guide.md` noch nicht vorkommen. Nur neue Konzepte —
Bestehendes wird referenziert, nicht kopiert.

### 2. Umsetzungsplan

Gesamtplan mit Status-Tabelle und empfohlener Reihenfolge (Runden).

### 3. Feature-Detailkonzepte

Pro Feature eine Datei im Format der Feature-Detailkonzept-Vorlage
(→ wf_code-to-test_guide.md, Abschnitt 4).

## Analyse-Leitfaden

### Umgang mit grossen Feature-Gruppen (> 10 Handler)
1. Alle Handler der Gruppe grob scannen: Folgen sie einem einheitlichen Pattern?
2. Repräsentativen Handler auswählen (komplexeste Logik / höchster CRAP-Score)
3. Repräsentativen Handler vollständig per EP/BVA analysieren
4. Für die übrigen Handler: "Smoke-Test (GET → 200 / POST → Redirect 302)" als Strategie
5. Gemeinsame Guard-Patterns in der übergreifenden Konzepte-Datei dokumentieren

### Auth-Kontext
Vor dem Schreiben der Auth-Abschnitte: `MysqlTestCase.php` und bestehende Tests lesen,
um das exakte Pattern zu verstehen. Nicht raten.

### Priorisierungs-Kriterien für Reihenfolge
- Priorität aus Feature-Matrix (Hoch > Mittel > Niedrig)
- Aufwand (Niedrig/Mittel vor Hoch)
- Abhängigkeiten beachten (z.B. CreateTree vor Import)
- Session-/Infrastruktur-Einschränkungen → spätere Runden

## Formale Anforderungen

- SPDX-Header auf jede neue .md-Datei
- Referenz-IDs exakt wie in der Feature-Matrix
- Aufwandskategorien: Niedrig / Mittel / Hoch (→ wf_test-iteration_guide.md Abschnitt 6)
- Keine Code-Dateien erstellen — ausschließlich .md-Dokumente
- Keine Änderungen an bestehenden Dokumenten oder Testklassen
- Keine Analyse bereits abgeschlossener Features
```

---

## 4 Vorlage: Feature-Detailkonzept

Für jedes Feature wird eine Detailkonzept-Datei erstellt. Die folgende Struktur ist verbindlich.

### Template für Teststufe-2-Features

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — <REF>: <Feature-Name>

**Referenz:** <REF> | **SUT:** `app/Http/RequestHandlers/<Handler>.php` (+ ggf. weitere Klassen)
**Aktueller Test:** <Testklassenname falls vorhanden, sonst "kein Test — neu anlegen">
**Übergreifende Konzepte:** → [wf_test-iteration_guide.md](wf_test-iteration_guide.md)

---

## Status quo

[Gibt es bereits Tests? Wenn ja: welche Methoden, welche Qualitätsstufe (Smoke vs. EP)?]

---

## SUT-Kernbefunde

[Für jeden relevanten Handler oder jede Klasse: Branch-Tabelle]

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Guard: Record nicht gefunden | ... | Nein |
| Happy Path | ... | Nein |
| ... | | |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ... | ... |

---

## Grenzwerte (BVA)

[Nur wenn sinnvoll — bei reinen Guard-Handlern ohne numerische Grenzen weglassen]

---

## Empfohlene Strategie

[ISTQB B / C / Hybrid. Neue Testklasse anlegen oder bestehende erweitern? Welche Fixtures?]

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
```

### Template für EXCLUDED-Features

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — <REF>: <Feature-Name>

**Referenz:** <REF> | **Status:** EXCLUDED — Teststufe 2 nicht anwendbar
**Übergreifende Konzepte:** → [wf_test-iteration_guide.md](wf_test-iteration_guide.md)

## Ausschlussgrund

[1–3 Sätze: Warum ist Teststufe 2 nicht sinnvoll/möglich? Welche Teststufe deckt es ab?]

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | EXCLUDED | [Teststufe X only] |
```
