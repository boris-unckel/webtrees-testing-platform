# Prompt: Refactoring von `docs/testing-bigpicture-prompt.md`

> Dieser Prompt beschreibt das Refactoring des Dokuments `docs/testing-bigpicture-prompt.md`.
> Das Dokument ist historisch gewachsen (15 Aktualisierungszyklen, 1792 Zeilen, ~107 KB)
> und soll konsolidiert werden — ohne inhaltlichen Verlust der noch relevanten Teile.

---

## Ziel

Das Dokument wird von einem akkumulierten Arbeitsprotokoll zu einer **konsistenten
Teststrategie-Dokumentation** refactored. ISTQB-Terminologie (Glossar de_DE v4.7.1)
bleibt sprachlich und inhaltlich führend. Die Struktur der Hauptüberschriften bleibt
weitgehend erhalten (sanftes Refactoring), wird aber ISTQB-konform geschärft.

---

## Leitprinzipien

1. **Code ist führend.** Wo Code und Dokumentation divergieren, gilt der Code.
   Fachlichkeit, die beschrieben aber nicht implementiert ist, wird als offener Punkt
   markiert (z. B. „Geplant", „Offen (Prio 4)").
2. **ISTQB v4.7.1 ist sprachlich führend.** Begriffe: Komponententest, Komponentenintegrationstest,
   Systemtest, Teststufe, Testart, Testorakel, Testentwurfsverfahren, Anweisungsüberdeckung, etc.
3. **Keine Prompt-Artefakte.** Alte Prompt-Anweisungen an AI-Tools werden entfernt.
4. **Umsetzungshistorie bleibt erhalten** — als eigener Abschnitt am Dokumentende.
5. **Abgearbeitete Detailpläne werden entfernt.** Die Ergebnisse stehen bereits im
   Implementierungs-Fahrplan; die Plan-Details (AP-Tabellen, Code-Beispiele,
   Voraussetzungen, Migrationsstrategien) sind überholt.
6. **Das Refactoring selbst erhält einen Eintrag** in der Umsetzungshistorie.

---

## Konkrete Änderungen

### A — Entfernen

| Was | Zeilen (ca.) | Begründung |
|-----|-------------|------------|
| **Prompt für AI-Diagramm-Tool** | 49–153 (Abschnitt „Prompt für das Big-Picture-Bild" inkl. des eingerückten Prompt-Blocks) | Alte Prompt-Anweisung. Das Mermaid-Diagramm bleibt als einzige Architekturdarstellung. |
| **Detailplan Phase 7a** (OTel-Instrumentation) | 1325–1407 | Vollständig implementiert, Ergebnis steht im Fahrplan. |
| **Detailplan Phase 5b** (E2E-Routenabdeckung) | 1410–1482 | Vollständig implementiert, Ergebnis steht im Fahrplan. |
| **Detailplan Phase 5c** (Theme-Integration) | 1485–1687 | Vollständig implementiert, Ergebnis steht im Fahrplan. |
| **Upstream-Stubs Detailpläne** (Prio 2a–4, APs, Voraussetzungen) | 1233–1322 | Vollständig implementiert. Der Upstream-Contribution-Abschnitt bleibt, wird aber auf Konzept + Ergebnis-Summary gekürzt (siehe Abschnitt E). |
| **Behobene Known Bugs** (4 Stück) | 1724–1792 (GUEST-Bugs + Ergebnis-Testlauf) | Behoben, historisch nicht mehr relevant. Der Ergebnis-Testlauf-Snapshot wird durch einen Hinweis ersetzt, wo aktuelle Ergebnisse zu finden sind (siehe Abschnitt D). |
| **Ergebnis Testlauf** (Snapshot-Tabelle) | 1783–1792 | Veraltet (zeigt Layer 4 mit 13 Tests, aktuell 130). Wird durch Verweis auf CI/Artefakte ersetzt. |

### B — Mapping-Tabelle Layer ↔ ISTQB-Teststufe einführen

Das Dokument schwankt zwischen „Layer 1–5" (Code/Makefile) und „Teststufe 1–3 + Querschnitte"
(ISTQB). Diese sind nicht deckungsgleich. **Lösung:** Eine explizite Mapping-Tabelle
einführen, prominent platziert (direkt nach den Designentscheidungen, vor dem Mermaid-Diagramm).
Danach im gesamten Dokument **durchgängig ISTQB-Terminologie** verwenden. Wo ein Bezug
zum Code nötig ist (z. B. Makefile-Targets, Verzeichnisnamen), die Layer-Bezeichnung
in Klammern ergänzen.

**Mapping:**

| Code (Makefile / Verzeichnis) | ISTQB-Teststufe / Querschnitt |
|-------------------------------|-------------------------------|
| `layer1-static/` / `make test-static` | Querschnitt — Statischer Test |
| `layer2-unit/` / `make test-unit` | Teststufe 1 — Komponententest |
| `layer3-integration/` / `make test-integration` | Teststufe 2 — Komponentenintegrationstest |
| `layer4-e2e/` / `make test-e2e` | Teststufe 3 — Systemtest |
| `layer5-performance/` / `make test-performance` | Querschnitt — Performanztest |

### C — Mermaid-Diagramm aktualisieren

Das bestehende Mermaid-Diagramm (Zeilen 162–245) bleibt die einzige Architekturdarstellung.
Folgende Aktualisierungen:

1. **Layer-Bezeichnungen konsistent auf ISTQB umstellen.** Subgraph-Titel und Knoten-Labels
   verwenden ISTQB-Begriffe. Die Code-Layer-Nummern (`layer1`–`layer5`) werden als
   Klammerzusatz ergänzt, z. B.: `Teststufe 1 — Komponententest (layer2-unit)`.
2. **N+1-Erkennung stehen lassen** im TS2-Subgraph (OTel-Aspiration, kein automatisierter Test — ist als Diagnoseziel korrekt).
3. **Testfall-Zahlen NICHT in das Diagramm aufnehmen.** Alle Testfall-Zahlen sind
   volatil (ändern sich bei jeder Erweiterung). Stattdessen verweist ein Hinweis
   unter dem Diagramm auf `make test-all` und die CI-Artefakte als aktuelle Quelle.

### D — Known Bugs auf offene reduzieren + Upstream-Bug aufnehmen

Der Abschnitt „Bekannte Fehler im Teststack" wird auf **2 offene Einträge** reduziert:

1. **HOST-Bug: SELinux MCS-Label-Konflikt** (Zeilen 1710–1720) — bleibt unverändert.
2. **Upstream-Bug: `FamilyFactory::mapper()` TypeError** (aktuell nur in der Upstream-
   Contribution-Tabelle erwähnt, Zeile 1145) — wird als eigenständiger Known-Bug-Eintrag
   aufgenommen. Format wie bestehende Bugs: Symptom, Ursache, Betrifft, Status.

Alle 4 behobenen GUEST-Bugs werden entfernt.

Der **Ergebnis-Testlauf-Snapshot** (Zeilen 1783–1792) wird entfernt und durch folgenden
Absatz ersetzt (im Abschnitt Known Bugs oder als eigener kurzer Abschnitt):

> **Aktuelle Testergebnisse** finden sich in den CI-Artefakten des letzten GitHub-Actions-Laufs
> (7 Tage Retention) oder lokal via `make test-all`. Die Artefakte pro Teststufe liegen in
> `artifacts/layer1/` bis `artifacts/layer5/`.

### E — Upstream-Contribution kürzen

Der Abschnitt „Upstream-Contribution: Test-Stubs mit echten Tests füllen" (Zeilen 1071–1322)
wird **auf Konzept + Ergebnis-Summary gekürzt:**

**Behalten:**
- Abgrenzungstabelle (Zeilen 1078–1085) — klärt Verantwortlichkeiten
- Vorgehen (5 Schritte, Zeilen 1088–1099) — konzeptueller Plan
- Scope-Tabelle (Zeilen 1103–1113) — welche Domänen abgedeckt werden
- Abgrenzung zu diesem Repo (Zeilen 1115–1120)
- Redundanz und Rückbau (Zeilen 1122–1131)
- Status-Tabelle (Zeilen 1135–1147) — zeigt Ergebnisse
- Abdeckungsmatrizen (Zeilen 1149–1231) — zeigt Code/Doku-Konsistenz

**Entfernen:**
- Detailplan-Tabellen Prio 2a–4 (Zeilen 1233–1322) — alle APs erledigt
- Voraussetzungen und Abhängigkeiten (Zeilen 1312–1322) — erledigt

### F — Umsetzungshistorie

Die 15 bestehenden `*Aktualisiert*`-Einträge (Zeilen 1690–1704) werden 1:1 in einen
eigenen Abschnitt **„Änderungshistorie"** am Dokumentende verschoben. Format beibehalten.
Ein neuer Eintrag für dieses Refactoring wird hinzugefügt:

```
*Aktualisiert: 2026-03-28 — Dokument-Refactoring: Abgearbeitete Detailpläne (Phase 5b, 5c, 7a,
Upstream-Stubs Prio 2a–4) entfernt. AI-Diagramm-Prompt entfernt. Behobene Known Bugs entfernt,
Upstream-Bug FamilyFactory::mapper() als eigener Eintrag aufgenommen. Mapping-Tabelle
Layer ↔ ISTQB-Teststufe eingeführt, durchgängig ISTQB-Terminologie. Mermaid-Diagramm
aktualisiert (ISTQB-Labels, Layer-Zuordnung). Upstream-Contribution auf Konzept + Ergebnis
gekürzt. Testlauf-Snapshot durch Verweis auf CI-Artefakte ersetzt.*
```

### G — Konsistenzprüfung Code ↔ Dokumentation

Nach allen Entfernungen und Kürzungen: **Prüfe jede verbleibende Faktenaussage gegen
den aktuellen Code.** Insbesondere:

| Prüfpunkt | Quelle |
|-----------|--------|
| Verzeichnisstruktur N2 | `ls -R` der Repo-Root |
| Feature-Matrix-IDs in Abdeckungsmatrizen | `grep -r "G\|S[0-9]" layer*/` |
| Testfall-Zahlen im Implementierungs-Fahrplan | Zählung der Testmethoden/-Specs |
| Container-Stack (6 Container) | `compose.yaml` |
| Makefile-Targets | `Makefile` |
| Fixture-Dateien | `fixtures/` |
| E2E-Spec-Dateien und Testbedingungen | `layer4-e2e/tests/` |
| OTel-Konfiguration | `otel/`, `compose.yaml`, `Containerfile.webtrees` |

Wo eine Divergenz gefunden wird: **Code gewinnt.** Die Dokumentation wird angepasst.
Fachlichkeit, die beschrieben aber nicht umgesetzt ist, erhält den Vermerk
„Delta: beschrieben, nicht implementiert" oder wird in der Abdeckungsmatrix als
„Offen" markiert.

### H — Struktur (sanftes Refactoring)

Die Hauptüberschriften bleiben weitgehend erhalten. Reihenfolge und Benennung werden
ISTQB-konform geschärft, wo nötig. Erwartete Struktur nach dem Refactoring:

```
# Teststrategie — webtrees-testing-platform
## Getroffene Designentscheidungen
## Zuordnung Layer ↔ ISTQB-Teststufe (NEU)
## Mermaid-Diagramm (aktualisiert)
## Getroffene Infrastruktur-Entscheidungen (N1–N7)
## Container-Stack-Spezifikation
## Fachliche Anforderungen — Reverse-Engineering-Methodik
### Feature-Matrix: GEDCOM Import/Export
### Feature-Matrix: Suche und Navigation
### Testfall-Verteilung nach Teststufe
## Endekriterien pro Teststufe
## Testorakel
## Testentwurfsverfahren pro Domäne
## Produktrisiken und Projektrisiken
## Überdeckungsstrategie — Ratchet
## Fehlermanagement
## Testkonventionen
## Verfolgbarkeit
## Implementierungs-Fahrplan
## Upstream-Contribution (gekürzt)
### Abdeckungsmatrix (G01–G23, S01–S40)
## Bekannte Fehler (nur offene)
## Aktuelle Testergebnisse (Verweis)
## Änderungshistorie
```

---

## Nicht ändern

- **Feature-Matrizen** (G01–G23, S01–S40) — inhaltlich unverändert lassen
- **Abdeckungsmatrizen** — inhaltlich unverändert lassen (Status-Spalten spiegeln Code wider)
- **Infrastruktur-Entscheidungen N1–N7** — bleiben (sind Architektur-Dokumentation, nicht Pläne)
- **Container-Stack-Spezifikation** — bleibt
- **Testkonventionen** (AAA, FIRST, Namenskonvention, Data Provider) — bleiben
- **Endekriterien, Testorakel, Testentwurfsverfahren, Risiken** — bleiben
- **Überdeckungsstrategie, Fehlermanagement, Verfolgbarkeit** — bleiben

---

## Verifikation nach dem Refactoring

1. **Mermaid rendert fehlerfrei** — in VS Code oder Mermaid Live prüfen
2. **Alle Feature-Matrix-IDs (G01–G23, S01–S40)** sind im Dokument auffindbar
3. **Keine verwaisten Referenzen** auf entfernte Abschnitte (Phase 5b/5c/7a-Details)
4. **ISTQB-Terminologie durchgängig** — kein „Unit-Test" statt „Komponententest", etc.
5. **Dokumentgröße signifikant reduziert** (Ziel: ~50–60% der bisherigen Größe)
6. **Keine inhaltlichen Verluste** bei: Designentscheidungen, Feature-Matrizen,
   Abdeckungsmatrizen, Infrastruktur-Entscheidungen, Testkonventionen, ISTQB-Abschnitten
