<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# sample-analysis.md — Strukturvorlage (fiktive Beispieldaten)

> Dieses Dokument zeigt Struktur und Tiefe der Coverage-Analyse.
> Alle Daten sind fiktiv. Es wird **nicht** überschrieben.
> Vorlage für `prep-02-analysis.md`.

---

## 2.1 — Gesamtüberblick

```
Gesamt-Anweisungsüberdeckung:   22,5%  (9.914 / 44.066 Statements)
Gesamt-Methodenüberdeckung:     19,2%  (853 / 4.441 Methoden)
```

| Metrik | Vorher (296 Tests) | Nachher (312 Tests) | Delta |
|---|---|---|---|
| Anweisungsüberdeckung | 19,8% (8.716 / 44.066) | 22,5% (9.914 / 44.066) | +2,7 Pp |
| Methodenüberdeckung | 17,7% (787 / 4.441) | 19,2% (853 / 4.441) | +1,5 Pp |

### Paket-Aufschlüsselung (Ausschnitt)

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|
| Http\Routes | 370 | 99,7% | 2 | 50,0% | Sehr gut |
| Services | 5.727 | 41,3% | 297 | 24,6% | Partiell |
| Http\RequestHandlers | 14.280 | 3,1% | 1.834 | 2,7% | Gering |
| Report | 2.104 | 0,0% | 198 | 0,0% | Keine Coverage |

---

## 2.2 — CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

| Rang | CRAP | Paket | Klasse | Methode | cx |
|---|---|---|---|---|---|
| 1 | 6.972 | (root) | RightToLeftSupport | spanLtrRtl | 83 |
| 2 | 2.256 | Report | ReportHtmlTextBox | render | 47 |
| 3 | 1.722 | Http\RequestHandlers | SearchGeneralPage | handle | 41 |

---

## 2.3 — Klassifikation: DB-abhängig vs. Bootstrap-only

**DB-abhängig (braucht `createTreeWithGedcom()`):**

| CRAP | Klasse | Methode | DB-Zugriff | FM-Bezug |
|---|---|---|---|---|
| 1.722 | SearchGeneralPage | handle | `DB::table('individuals')` | FM-S03 |

**Bootstrap-only (kein DB-Aufruf, trotzdem Layer-3):**

| CRAP | Klasse | Methode | Begründung | Testbarkeit |
|---|---|---|---|---|
| 6.972 | RightToLeftSupport | spanLtrRtl | Kein DB-Zugriff, nur String-Ops | Hoch |
| 2.256 | ReportHtmlTextBox | render | Reines String-Rendering | Mittel |

---

## 2.4 — Gap-Analyse Feature-Matrix × Coverage

| FM-ID | Testklasse | Status | Bemerkung |
|---|---|---|---|
| FM-S01 | SearchIntegrationTest | grün | Vollständig |
| FM-S03 | — | rot | SearchGeneralPage::handle fehlt |
| FM-C01 | CalendarIntegrationTest | partiell | getAnniversaryEvents abgedeckt, handle nicht |

---

## 2.5 — Priorisierter Handlungsplan

**Gruppe A (CRAP > 1.000):**

| AP | Klasse | Methode | CRAP/cx | Begründung | Aufwand |
|---|---|---|---|---|---|
| AP1 | RightToLeftSupport | spanLtrRtl | 6972/83 | Bootstrap-only, maximale CRAP-Wirkung | niedrig |
| AP2 | SearchGeneralPage | handle | 1722/41 | DB-abhängig, FM-S03 kritisch | mittel |

**Gruppe B (CRAP 300–1.000):**

| AP | Klasse | Methode | CRAP/cx | Begründung | Aufwand |
|---|---|---|---|---|---|
| AP3 | ReportHtmlTextBox | render | 2256/47 | Bootstrap-only, großer CRAP-Block | mittel |

---

## 2.6 — testing-bigpicture.md Diff-Vorschläge

```diff
 ## Ist-Stand (Teststufe 2, Stand: YYYY-MM-DD, nach AP1–APn)
-Anweisungsüberdeckung: 19,8% (8.716 / 44.066)
+Anweisungsüberdeckung: 22,5% (9.914 / 44.066)
-Methodenüberdeckung:   17,7% (787 / 4.441)
+Methodenüberdeckung:   19,2% (853 / 4.441)
-Tests: 296, Assertions: 899, Testklassen: 21
+Tests: 312, Assertions: 1.034, Testklassen: 23
```

---

## 2.7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |
|---|---|---|
| pcov statement-level | Keine Branch-Coverage | Als bekannte Lücke dokumentieren |
| Bootstrap-only-Tests | Kein DB-Beweis | Im Testkommentar kennzeichnen |
| Methoden nur in E2E erreichbar | Im Integrations-Layer nicht triggerbar | FM-Eintrag als "E2E only" |
| Sehr große Klassen (cx > 50) | Hoher CRAP, aber Klasse komplex | Priorität trotzdem nach CRAP |
