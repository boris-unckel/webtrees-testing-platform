<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Iteration — Einstieg

Navigationspunkt für eine neue Coverage-Erweiterungs-Iteration
(Teststufe 2 — Komponentenintegrationstest, Layer 3, MySQL).

---

## Ablauf

| Schritt | Datei | Zweck | Ausführung |
|---|---|---|---|
| 0 | `prep-01-env-coverage.md` | Stack starten, Coverage erzeugen, CRAP-Report | Einmalig zu Beginn |
| 1 | `prep-02-analysis.md` | Analyse erstellen (`_full_analysis.md`) | Nach prep-01 |
| 2 | `prep-03-impl-plan.md` | Implementierungsplan + AP-Dateien erstellen | Nach prep-02 |
| 3 | `ap-{gruppe}-{nn}-{name}.md` | APs umsetzen: Skelett parallel, Ausführung sequenziell | Nach prep-03 |
| 4 | `post-01-finalize.md` | Voll-Lauf, Ratchet, Konsistenzprüfung, Commit | Nach allen APs ✅ |

---

## Parallelisierungsstrategie

```
prep-01 → prep-02 → prep-03
                        ↓
        ap-a-01 [Skelett]  ap-a-02 [Skelett]  ap-a-03 [Skelett]  ...parallel...
                        ↓
        ap-a-01 [Ausführung] → ap-a-02 [Ausführung] → ...sequenziell...
                        ↓
        ap-b-01 [Skelett]  ap-b-02 [Skelett]  ...parallel...
                        ↓
        ap-b-01 [Ausführung] → ...sequenziell...
                        ↓
        post-01
```

Alle Gruppen arbeiten auf demselben initialen Coverage-Snapshot.

---

## AP-Datei-Namenskonvention

```
ap-{gruppe}-{nn}-{kurzname}.md
```

| Segment | Bedeutung |
|---|---|
| `gruppe` | `a` (CRAP > 1.000), `b` (CRAP 300–1.000), `c` (CRAP 100–300) |
| `nn` | Zweistellig, nullgefüllt: 01, 02, … |
| `kurzname` | Klassenname in kebab-case, max. 30 Zeichen |

Beispiele: `ap-a-01-right-to-left-support.md`, `ap-b-03-search-general-page.md`

Die AP-Dateien werden von `prep-03` für die aktuelle Iteration generiert.

---

## Pflicht-Constraints (für jede AP-Phase-2 und Testausführung)

| Constraint | Quelle |
|---|---|
| `pgrep -a phpunit` vor jedem neuen Testlauf | CLAUDE.md |
| Lang laufende Tests: `run_in_background: true`, kein `timeout` | CLAUDE.md |
| `make up` (nie `make _compose-up`) | CLAUDE.md |
| Alle neuen Tests in `layer3-integration/tests/` | CLAUDE.md |
| Konstruktor-Verifikation vor jedem PHP-Skelett | Prompt-Erfahrung |
| Kein Commit vor allen APs ✅ + `make test-integration` Exit 0 | Prompt-Erfahrung |
| GPG-signierte Commits | CLAUDE.md |

Vollständige Stack-Regeln: `CLAUDE.md`

---

## Start

Session öffnen → `prep-01-env-coverage.md` lesen → ausführen.
