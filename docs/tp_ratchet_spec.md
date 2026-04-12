<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Überdeckungsstrategie und Endekriterien

Dieses Dokument definiert die Endekriterien jeder Teststufe sowie die
Ratchet-basierte Überdeckungsstrategie für den Komponententest.

Verwandte Dokumente:
- [Feature-Matrizen](tds_conditions_ref.md)
- [Abdeckungsmatrix](tds_coverage_ref.md)
- [Risikomanagement](tp_risks_spec.md)

---

## Endekriterien pro Teststufe

> Eingangskriterien sind implizit durch die sequentielle Job-Kette definiert:
> Jede Stufe startet nur, wenn alle vorgelagerten Stufen erfolgreich waren.

| Teststufe / Querschnitt | Endekriterien |
|---|---|
| Statischer Test | PHPStan Level 8: 0 Errors; PHPCS PSR-12: 0 Violations; Trivy: informell (keine Abbruch-Schwelle, Ergebnisse in `artifacts/layer1/`) |
| Teststufe 1 — Komponententest | Alle Feature-Matrix-Komponententests grün (G05, G06, G11, G17–G19, G22, G23, S04); Anweisungsüberdeckung ≥ vorheriger Wert (Ratchet) |
| Teststufe 2 — Komponentenintegrationstest | Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, G24–G26, G28–G29, S01–S03, S05–S08, S10–S12, S19 (inkl. Nachnamen-Collation via handle()), S21, S22, S41–S50, P01–P24, P27–P37, SEC-BOT01); strukturbasierte CRAP-Analyse-Tests grün (G27, S45) |
| Teststufe 3 — Systemtest | Alle Systemtestfälle grün über alle 5 Standard-Themes (G20, G21, S05–S08, S09, S10, S13–S18, S20, S23–S24, S26–S41, S46, S47, S50, E01–E06, E08, K01); S32–S34 theme-unabhängig grün; Admin-Only-Systemtests grün (E03, E06, A01, A04, A05, A07, P37, P38, K02); Privacy-Systemtests grün (P01–P03, P14–P19, P22, P24–P29, P30, P40, P41) |
| Performanztest | Kein Szenario >20% über Baseline; kein Szenario mit >+2 DB-Queries gegenüber Baseline |
| Sicherheitstest | Alle MUSS-Prüfpunkte (SEC-H01–H06, SEC-C01–C03, SEC-W01, SEC-WZ01–WZ04) grün; SOLL-Prüfpunkte grün oder als Upstream-Befund dokumentiert; KANN-Prüfpunkte (SEC-HDR01–HDR04) dokumentiert |

---

## Überdeckungsstrategie — Ratchet

> Anweisungsüberdeckung (ISTQB: Statement Coverage) via pcov, gemessen im Komponententest.

**Strategie:** Die Anweisungsüberdeckung darf nur steigen, niemals sinken.

| Aspekt | Entscheidung |
|---|---|
| **Überdeckungsart** | Anweisungsüberdeckung (pcov) |
| **Zielwert** | Kein absoluter Wert — Ratchet-Prinzip |
| **Mechanismus** | CI prüft: aktuelle Überdeckung ≥ vorherige Überdeckung |
| **Baseline** | Wird beim ersten vollständigen Testlauf automatisch gesetzt |
| **Scope** | Service-Klassen der Feature-Matrizen (G01–G23, S01–S24, S26–S40, P01–P29) |
| **Reporting** | Coverage-HTML als CI-Artefakt (7 Tage Retention) |

### Ist-Stand (Stand: 2026-04-11, Commit: 72bb731)

> Vollständiger Layer-Vergleich: [`docs/coverage-runs/2026-04-11_layer2-vs-layer3.md`](coverage-runs/2026-04-11_layer2-vs-layer3.md)

#### Layer 2 — Upstream-Unit-Tests (Teststufe 1, `make test-unit`)

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | **39,82 %** (17.527 / 44.021 Statements) |
| Methodenüberdeckung | 36,06 % (1.598 / 4.432 Methoden) |
| Pakete mit >80 %-Coverage | Census (99,8 %), SurnameTradition (89,8 %), Report (79,1 %), Elements (86,9 %) |
| Pakete mit 0 %-Coverage | CustomTags, Cli, GedcomFilters, Helpers, Statistics |

#### Layer 3 — Integrationstests (Teststufe 2, `make test-integration`)

> Vorherige Werte (2026-04-04, nach AP A-01 + AP B-01–B-07 + AP C-01–C-07): 32,4 % / 22,8 %.

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | **39,83 %** (17.540 / 44.035 Statements) — Ratchet-Basis |
| Methodenüberdeckung | 36,19 % (1.604 / 4.432 Methoden) |
| Pakete mit >80 %-Coverage | CustomTags (97,2 %), Helpers (94,1 %), GedcomFilters (81,5 %), Date (77,0 %) |
| Pakete mit 0 %-Coverage | Statistics, Schema (<4 %), Census (<3 %) |
| Größte unabgedeckte Pakete | Http/RequestHandlers (35 %), Module (restliche Methoden) |

#### Komplementarität

Layer 2 und Layer 3 decken überwiegend **verschiedene Bereiche** ab:
- L2 stärker: Census (−97,6 %), Elements (−58,5 %), SurnameTradition (−67,1 %), Http (−26,4 %), Report (−21,3 %)
- L3 stärker: Services (+39,7 %), CustomTags (+97,2 %), Date (+70,3 %), Cli (+45,2 %), Module (+14,0 %)

Nettobilanz: +6.645 Statements wo L3 besser / −6.648 wo L2 besser → Gesamtdifferenz ≈ 0.

**Begründung:** Das Projekt startet bei ~0% substanzieller Überdeckung (95% Stub-Tests).
Ein willkürlicher Zielwert (z. B. 80%) wäre spekulativ. Die Ratchet-Strategie schützt
gegen Rückschritte und garantiert monotones Wachstum. Jeder echte Test ist ein Gewinn.

**Sicherheitstest-Track:** Anweisungsüberdeckung (pcov) ist für den Sicherheitstest nicht
anwendbar — der Distribution-Container enthält kein pcov, keine Dev-Dependencies und keinen
PHPUnit-Runner. Die Tests prüfen von außen (HTTP, Dateisystem), nicht von innen.
Stattdessen gelten drei alternative Metriken:

| Aspekt | Metrik |
|---|---|
| **Prüfpunkt-Abdeckung** | 26/26 Prüfpunkte implementiert und ausgeführt |
| **Angriffsmuster-Abdeckung** | URL-Encoding (9 Varianten), Path-Traversal (5 Varianten) durchlaufen |
| **Vektor-Abdeckung** | Alle 8 Angriffsvektoren durch mindestens einen Prüfpunkt adressiert |

**Vektor-zu-Prüfpunkt-Mapping:**

| Vektor | Adressiert durch |
|---|---|
| V1 — Direktzugriff `data/` | SEC-H03, SEC-H04, SEC-H06 |
| V2 — Direktzugriff `data/media/` | SEC-H05, SEC-M01 |
| V3 — Datei-Permissions | SEC-C03 |
| V4 — Directory Listing | SEC-PUB03 |
| V5 — Wizard nach Setup | SEC-W01, SEC-WZ04 |
| V6 — Fehlende `.htaccess` | SEC-H01, SEC-H02 |
| V7 — Path-Traversal | SEC-PUB04 |
| V8 — Security-Headers | SEC-HDR01–SEC-HDR04 |
