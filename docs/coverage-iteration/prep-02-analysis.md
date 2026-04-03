<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# prep-02 — Analyse erstellen

**Ausgabe:** `docs/component-integration-coverage_full_analysis.md` (überschreibt bestehende Datei)

Struktur und Tiefe folgen `docs/coverage-iteration/sample-analysis.md` (fiktive Beispieldaten —
nicht überschreiben). Alle Abschnitte 2.1–2.7 mit den aktuellen Werten aus `coverage.xml`
und der `make crap-report`-Ausgabe aus `prep-01` befüllen.

---

## 2.1 — Gesamtüberblick

Aus `artifacts/layer3/coverage.xml` extrahieren:
- Gesamt-Anweisungsüberdeckung: covered / total, Prozent
- Gesamt-Methodenüberdeckung: covered / total, Prozent

Paket-Aufschlüsselungs-Tabelle:

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |

Bewertungskategorien: Sehr gut (>80%), Gut (50–80%), Partiell (10–50%),
Gering (1–10%), Marginal (<1%), Keine Coverage (0%).

Vergleich zur vorherigen Baseline als Delta-Spalte.

## 2.2 — CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

Aus der `make crap-report`-Ausgabe (Kontext aus prep-01):

| Rang | CRAP | Paket | Klasse | Methode | cx |

Vollständige Liste — nicht auf 30 begrenzen.

## 2.3 — Klassifikation: DB-abhängig vs. Bootstrap-only

Für jeden Kandidaten aus 2.2: Quellcode-Analyse — benötigt die Methode
`DB::table()`, `Tree`, oder `Registry::individualFactory()`? Oder reicht
webtrees-Bootstrap (I18N, Registry)?

**DB-abhängig (braucht `createTreeWithGedcom()`):**
| CRAP | Klasse | Methode | DB-Zugriff | Feature-Matrix-Bezug |

**Bootstrap-only (kein DB-Aufruf, trotzdem Layer-3):**
| CRAP | Klasse | Methode | Begründung | Testbarkeit |

Grenzfälle explizit begründen (z. B. Klasse hat `DB::table()`, aber die zu
testende Methode nicht).

## 2.4 — Gap-Analyse Feature-Matrix × Coverage

FM-IDs aus `docs/testing-bigpicture.md` lesen.
Für jede ID: Testklasse(n), Coverage-Status (grün/partiell/rot), Bemerkung.

## 2.5 — Priorisierter Handlungsplan (ohne Code)

Aktionspunkte nach CRAP absteigend, gruppiert:

```
Gruppe A: CRAP > 1.000 (höchste Priorität)
Gruppe B: CRAP 300–1.000
Gruppe C: CRAP 100–300
```

Für jeden Punkt: Zielklasse, Methode, CRAP/cx, Begründung (1–2 Sätze),
Umsetzungsidee (kein Code), Einschätzung Testaufwand (niedrig/mittel/hoch).

## 2.6 — testing-bigpicture.md Diff-Vorschläge

Für jede Änderung an Ratchet-Ist-Stand, FM-Tabelle, Abdeckungsmatrix:
diff-Block mit Kontext (Tabelle/Abschnitt).

## 2.7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |

Pflichteinträge:
- pcov statement-level (keine Branch-Coverage)
- Bootstrap-only-Tests: zählen zur Layer-3-Coverage, aber kein DB-Beweis
- Methoden, die nur in E2E- oder Performance-Lauf triggerbar sind
- Sehr große Klassen (cx > 50): hoher CRAP, aber Klasse komplex

---

## Weiter

→ `prep-03-impl-plan.md`
