# Planungs-Prompt — Datenschutz & Zugriffskontrolle (Privacy & Access Control)

> **Zweck:** Dieser Prompt plant die systematische Testabdeckung der Domäne
> **Datenschutz & Zugriffskontrolle** in webtrees. Er definiert Feature-Matrix,
> Fixture-Design, dynamische Testdaten-Generierung und Rollenmatrix.
> **Umsetzung erfolgt separat.**
>
> Erstellt: 2026-03-28. ISTQB-Terminologie (Glossar de_DE v4.7.1) ist führend.
> Rahmenbedingungen: `docs/testing-bigpicture-prompt.md`.

---

## Kontext und Motivation

Die bestehende Teststrategie (62/62 Features, G01–G23 + S01–S40) deckt GEDCOM
Import/Export und Suche/Navigation ab. Die Domäne **Privacy/Zugriffskontrolle**
war bewusst als „niedrigere Priorität, spätere Phase" eingestuft
(siehe `testing-bigpicture-prompt.md`, Abschnitt „Reverse-Engineering-Quellen").

Diese Phase wird jetzt konkret. Das Ziel: **Systematische Verifikation, dass die
Sichtbarkeits- und Bearbeitungsregeln von webtrees für alle Rollenebenen und
Konfigurationskombinationen korrekt greifen.**

### Produktrisiken (Ergänzung zu R1–R7)

| Risiko-ID | Risiko | Wahrscheinlichkeit | Auswirkung | Maßnahme |
|---|---|---|---|---|
| R8 | Privacy-Leak: Lebende Person für Besucher sichtbar | Mittel | Kritisch | P01–P07, P10–P11, P22–P24 |
| R9 | Privacy-Leak: Vertrauliche Fakten (Geburtsdatum, SSN) sichtbar | Niedrig | Kritisch | P17–P19 |
| R10 | isDead()-Fehlklassifikation an Datumsgrenzen | Mittel | Hoch | P08–P13 |
| R11 | RESN-Tags werden ignoriert oder falsch interpretiert | Niedrig | Hoch | P14–P16 |
| R12 | Bearbeiter kann ohne Berechtigung Daten ändern | Niedrig | Hoch | P25–P27 |
| R13 | Relationship Privacy zeigt entfernte/unverwandte Personen | Niedrig | Mittel | P20–P21 |

---

## Architektur der Privacy-Logik in webtrees

### Entscheidungskette (canShow)

Die Sichtbarkeitsprüfung folgt einer kaskadierenden Logik
(`GedcomRecord::canShowRecord()` → `Individual::canShowByType()`):

```
1. HIDE_LIVE_PEOPLE == 0?          → Alles sichtbar (Privacy deaktiviert)
2. Eigener Datensatz des Users?     → Sichtbar
3. Expliziter RESN-Tag am Record?   → RESN-Wert bestimmt Sichtbarkeit
4. default_resn für dieses XREF?    → Tabellenwert bestimmt Sichtbarkeit
5. Access-Level == PRIV_NONE?       → Verwalter sehen alles
6. canShowByType() [Individual]:
   a. isDead() + SHOW_DEAD_PEOPLE?  → Verstorbene: sichtbar (mit KEEP_ALIVE-Ausnahme)
   b. Relationship Privacy?         → Pfadlängen-Prüfung
   c. Fallback:                     → Nur Mitglieder+ (PRIV_USER)
```

### Fakten-Ebene (Fact::canShow)

```
1. Expliziter RESN auf Fakt?                → RESN-Wert bestimmt Sichtbarkeit
2. Link auf Record gleichen Typs?            → Privacy des Ziel-Records
3. individual_fact_privacy[xref][tag]?       → Tabellenwert
4. fact_privacy[tag]?                        → Tabellenwert
5. Keine Einschränkung                       → Öffentlich
```

### isDead()-Algorithmus

```
1. Expliziter Tod (DEAT Y / DEAT+DATE / DEAT+PLAC)?     → Tot
2. Irgendein datiertes Event > MAX_ALIVE_AGE Jahre alt?   → Tot
3. Geburtsdatum vorhanden + alle Events < MAX_ALIVE_AGE?  → Lebend
4. Inferenz über Verwandte:
   a. Eltern-Events > MAX_ALIVE_AGE + 45 Jahre alt?       → Tot
   b. Heirats-Events > MAX_ALIVE_AGE - 10 Jahre alt?      → Tot
   c. Ehepartner-Events > MAX_ALIVE_AGE + 40 Jahre alt?   → Tot
   d. Kinder-Events > MAX_ALIVE_AGE - 15 Jahre alt?       → Tot
   e. Enkel-Events > MAX_ALIVE_AGE - 30 Jahre alt?        → Tot
5. Keine Schlussfolgerung möglich                          → Lebend (Annahme)
```

### Access-Level-Hierarchie

| Konstante | Wert | Rolle | Bedeutung |
|---|---|---|---|
| `PRIV_PRIVATE` | 2 | Besucher | Öffentlich sichtbar |
| `PRIV_USER` | 1 | Mitglied | Nur für angemeldete Benutzer |
| `PRIV_NONE` | 0 | Verwalter | Nur für Verwalter/Admin |
| `PRIV_HIDE` | -1 | System | Für niemanden sichtbar |

### Rollen → Access-Level-Zuordnung

| Rolle | `Auth::accessLevel()` | Leserechte | Schreibrechte |
|---|---|---|---|
| Besucher | `PRIV_PRIVATE` (2) | Öffentliche Daten | Keine |
| Mitglied | `PRIV_USER` (1) | + Mitglieder-Daten | Keine |
| Bearbeiter | `PRIV_USER` (1) | = Mitglied | Hinzufügen/Ändern/Löschen (mit Moderation) |
| Moderator | `PRIV_USER` (1) | = Mitglied | + Änderungen akzeptieren/verwerfen |
| Verwalter | `PRIV_NONE` (0) | Alles im eigenen Baum | + Baumkonfiguration |
| Administrator | `PRIV_NONE` (0) | Alles in allen Bäumen | + Systemkonfiguration |

**Wichtig:** Bearbeiter, Moderator und Mitglied haben denselben `accessLevel()` = `PRIV_USER`.
Die Unterschiede liegen ausschließlich in den **Schreibrechten**, nicht in der Lesesichtbarkeit.
Verwalter und Administrator haben `accessLevel()` = `PRIV_NONE` und sehen alles.

---

## Designentscheidungen (aus Interview)

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Teststufe | Teststufe 2 (Komponentenintegrationstest) UND Teststufe 3 (Systemtest) — beide systematisch alle Rollen |
| 2 | Rollen | Alle 6 Rollen: Besucher, Mitglied, Bearbeiter, Moderator, Verwalter, Administrator. Bearbeiter-Edit mit DB-Persistenz-Prüfung |
| 3 | Kombinatorik | Umfangreich, aber kein volles kartesisches Produkt — Cluster-basiert |
| 4 | Fixture-Strategie | Dynamische Testdaten-Generierung auf Basis einer Template-Fixture |
| 5 | isDead()-Inferenz | Explizit abgedeckt mit separaten Fixtures pro Inferenz-Regel |
| 6 | Fact-Level-Privacy | In Scope |
| 7 | Relationship Privacy | In Scope |
| 8 | Feature-Matrix-Reihe | Eigene Reihe P01–Pxx |
| 9 | Testdaten-Stammbaum | `privacy-test.ged` als Template für dynamische Generierung |
| 10 | Nicht-Sichtbarkeit | „Vertraulich"-Platzhalter prüfen + Nicht-Auftauchen in Suchergebnissen; DOM-Prüfung als Fallback |

---

## Feature-Matrix: Datenschutz & Zugriffskontrolle (P01–P27)

> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.
> Rollen: B = Besucher, M = Mitglied, E = Bearbeiter, Mo = Moderator, V = Verwalter, A = Administrator.

### Cluster 1 — Stammbaum-Sichtbarkeit

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P01 | Stammbaum-Sichtbarkeit | `REQUIRE_AUTHENTICATION=1`: Besucher sieht keine Daten, wird zur Anmeldung umgeleitet. `=0`: Besucher sieht öffentliche Daten. | B, M | 2, 3 | Hoch |

### Cluster 2 — Verstorbene / Lebende Personen

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P02 | Verstorbene Personen zeigen | `SHOW_DEAD_PEOPLE=PRIV_PRIVATE(2)`: Besucher sieht Verstorbene. `=PRIV_USER(1)`: Nur Mitglieder+. Verstorbene Person = `isDead() === true`. | B, M, V | 2, 3 | Hoch |
| P03 | Lebende Personen zeigen (Override) | `HIDE_LIVE_PEOPLE=0`: Privacy komplett deaktiviert — alle Daten für alle Rollen sichtbar. `=1`: Privacy aktiv (Normalfall). | B, M, V | 2, 3 | Hoch |
| P04 | MAX_ALIVE_AGE — Altersgrenze | Grenzwertanalyse mit `MAX_ALIVE_AGE=120`: Person geboren vor genau 120 Jahren (Grenze), 119 Jahren (lebend), 121 Jahren (tot). | B, M | 2 | Hoch |
| P05 | KEEP_ALIVE_YEARS_BIRTH | Verstorbene Person mit Geburt innerhalb von N Jahren bleibt geschützt. Grenzwertanalyse: Geburt ==N Jahre her (geschützt), ==N+1 (nicht geschützt). Default: 0 (deaktiviert). | B, M | 2 | Hoch |
| P06 | KEEP_ALIVE_YEARS_DEATH | Verstorbene Person mit Tod innerhalb von N Jahren bleibt geschützt. Grenzwertanalyse: Tod ==N Jahre her (geschützt), ==N+1 (nicht geschützt). Sinnvoller Testwert: N=10. | B, M | 2 | Hoch |
| P07 | KEEP_ALIVE kombiniert | Person ist verstorben (`isDead()=true`), aber sowohl KEEP_ALIVE_YEARS_BIRTH als auch KEEP_ALIVE_YEARS_DEATH sind gesetzt — einer greift, der andere nicht. Prüft OR-Logik. | B, M | 2 | Mittel |

### Cluster 3 — isDead()-Algorithmus

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P08 | isDead(): Expliziter Tod | Person mit `1 DEAT Y` oder `1 DEAT\n2 DATE ...` oder `1 DEAT\n2 PLAC ...` → `isDead()=true`. Person ohne DEAT → `isDead()=false` (wenn jung genug). | — | 2 | Hoch |
| P09 | isDead(): Datiertes Event > MAX_ALIVE_AGE | Irgendein Event (nicht nur Geburt) älter als MAX_ALIVE_AGE → tot. Grenzwertanalyse: Event exakt am Grenzwert, ±1 Tag. | — | 2 | Hoch |
| P10 | isDead(): Geburt vorhanden + jung | Person mit Geburtsdatum < MAX_ALIVE_AGE und keinem alten Event → `isDead()=false`. Auch wenn kein DEAT vorhanden. | — | 2 | Hoch |
| P11 | isDead(): Inferenz Eltern | Person ohne datierte Events, aber Eltern haben Events > MAX_ALIVE_AGE+45 Jahre alt → tot. Grenzwertanalyse: Eltern-Event exakt an Grenze. | — | 2 | Hoch |
| P12 | isDead(): Inferenz Ehepartner | Person ohne datierte Events. (a) Heiratsevent > MAX_ALIVE_AGE−10 → tot. (b) Ehepartner-Event > MAX_ALIVE_AGE+40 → tot. Grenzwertanalyse für beide Pfade. | — | 2 | Mittel |
| P13 | isDead(): Inferenz Kinder/Enkel | Person ohne datierte Events. (a) Kinder-Event > MAX_ALIVE_AGE−15 → tot. (b) Enkel-Event > MAX_ALIVE_AGE−30 → tot. Grenzwertanalyse für beide Pfade. | — | 2 | Mittel |

### Cluster 4 — Namen und Beziehungen vertraulicher Personen

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P14 | Namen vertraulicher Personen | `SHOW_LIVING_NAMES=PRIV_USER(1)`: Mitglieder sehen Namen, aber keine Details. `=PRIV_NONE(0)`: Nur Verwalter sehen Namen. `=PRIV_PRIVATE(2)`: Besucher sehen Namen. | B, M, V | 2, 3 | Hoch |
| P15 | Vertrauliche Beziehungen | `SHOW_PRIVATE_RELATIONSHIPS=1`: Leere „Vertraulich"-Boxen in Charts/Diagrammen. `=0`: Keine Boxen — vertrauliche Verwandte komplett ausgeblendet. | B, M | 2, 3 | Mittel |

### Cluster 5 — RESN-Tags (Record-Level und Fact-Level)

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P16 | RESN none (Record) | `1 RESN none` auf Person → für alle Rollen sichtbar, auch wenn Person sonst als „lebend" geschützt wäre. Überschreibt isDead()-Logik. | B, M, V | 2, 3 | Hoch |
| P17 | RESN privacy (Record) | `1 RESN privacy` auf Person → nur Mitglieder+ sehen den Datensatz. Besucher sieht „Vertraulich". | B, M, V | 2, 3 | Hoch |
| P18 | RESN confidential (Record) | `1 RESN confidential` auf Person → nur Verwalter/Admin sehen den Datensatz. Mitglieder und Besucher sehen „Vertraulich". | B, M, V, A | 2, 3 | Hoch |
| P19 | RESN auf Fakten-Ebene | `2 RESN privacy` auf einem BIRT-Fakt → Person sichtbar, aber Geburtsdatum nur für Mitglieder+. `2 RESN confidential` auf DEAT → Todesdatum nur für Verwalter+. | B, M, V | 2, 3 | Hoch |

### Cluster 6 — default_resn (Tabellen-basierte Einschränkung)

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P20 | default_resn (Individuum) | Eintrag in `default_resn` mit `xref=I123, tag_type=NULL` → gesamter Record eingeschränkt (wie Record-RESN, aber per Datenbank statt GEDCOM). | B, M, V | 2 | Mittel |
| P21 | default_resn (Faktentyp) | Eintrag in `default_resn` mit `xref=NULL, tag_type=SSN` → alle SSN-Fakten im Baum eingeschränkt. Eintrag mit `xref=I123, tag_type=BIRT` → nur BIRT von I123 eingeschränkt. | B, M, V | 2 | Mittel |

### Cluster 7 — Relationship Privacy

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P22 | Relationship Privacy (Pfadlänge) | User hat `PREF_TREE_PATH_LENGTH=3` und ist mit Person X über 2 Schritte verwandt → X sichtbar. Person Y über 5 Schritte → Y nicht sichtbar. Pfadlänge=0 → deaktiviert. | M | 2, 3 | Mittel |
| P23 | Relationship Privacy (kein XREF) | User hat Pfadlänge > 0, aber kein `PREF_TREE_ACCOUNT_XREF` → Fallback: alles sichtbar (kein Crash). | M | 2 | Mittel |

### Cluster 8 — Privacy-Integration (Suche, Export, Darstellung)

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P24 | Privacy in Suchergebnissen | Geschützte Person taucht für Besucher nicht in Suchergebnissen auf. Für Mitglieder mit passendem Access-Level: Person in Ergebnissen enthalten. | B, M, V | 2, 3 | Hoch |
| P25 | Personenseite: Vertraulich-Platzhalter | Besucher ruft Personenseite auf → „Vertraulich"/„Private" statt Echtdaten. Name ggf. sichtbar (abhängig von SHOW_LIVING_NAMES). Details (Geburt, Tod, Fakten) verborgen. | B, M, V | 3 | Hoch |
| P26 | Charts: Vertrauliche Boxen | Ahnentafel-Chart mit vertraulichen Personen → leere Boxen mit „Vertraulich" (wenn SHOW_PRIVATE_RELATIONSHIPS=1) oder komplett ausgeblendet (=0). | B, M | 3 | Mittel |

### Cluster 9 — Zugriffskontrolle (Schreibrechte)

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P27 | Bearbeiter: Datensatz bearbeiten | Bearbeiter fügt einen Fakt hinzu → Änderung als „pending change" in DB. Prüfung: DB-Tabelle `change` enthält den Eintrag. Seite zeigt Pending-Hinweis. | E | 2, 3 | Hoch |
| P28 | Moderator: Änderungen akzeptieren | Moderator akzeptiert eine Pending Change → Datensatz wird in DB aktualisiert (`gedcom_chunk` / GEDCOM aktualisiert). | Mo | 2, 3 | Hoch |
| P29 | Kein Bearbeitungszugriff / RESN locked | Besucher und Mitglied können Edit-Seiten nicht aufrufen → Access Denied. Besucher sieht keine Edit-Buttons. Bearbeiter kann RESN-locked-Records nicht editieren (nur Verwalter). RESN-Kombinationen (`privacy, locked`) wirken additiv: Sichtbarkeit + Edit-Lock getrennt. | B, M, E, V | 2, 3 | Hoch |

---

## Testfall-Verteilung nach Teststufe

| Teststufe | Feature-Matrix-IDs | Anzahl |
|---|---|---|
| Teststufe 2 — Komponentenintegrationstest | P01–P24, P27–P29 | **27** |
| Teststufe 3 — Systemtest | P01–P03, P14–P19, P22, P24–P29 | **18** |
| **Nur Teststufe 2** | P04–P13, P20–P21, P23 | 13 |
| **Nur Teststufe 3** | P25, P26 | 2 |
| **Beide Teststufen** | P01–P03, P14–P19, P22, P24, P27–P29 | 14 |

> **Begründung:** Die kombinatorisch aufwändigen Tests (Grenzwertanalyse, isDead()-Inferenz,
> default_resn) laufen in Teststufe 2 — schnell, deterministisch, viele Äquivalenzklassen per
> DataProvider. Teststufe 3 verifiziert die End-to-End-Sichtbarkeit im Browser: Platzhalter,
> Suchergebnisse, Charts, Edit-Buttons.

---

## Prioritätsverteilung

| Priorität | Feature-IDs | Anzahl | Anteil |
|---|---|---|---|
| Hoch | P01–P06, P08–P10, P14, P16–P19, P24, P25, P27–P29 | 19 | 66% |
| Mittel | P07, P12, P13, P15, P20–P23, P26 | 9 | 31% |
| Niedrig | — | 0 | 0% |

---

## Testentwurfsverfahren pro Cluster

| Verfahren (ISTQB) | Cluster / Feature-IDs | Begründung |
|---|---|---|
| **Grenzwertanalyse** | Cluster 2 (P04–P06), Cluster 3 (P08–P13) | Datumsgrenzen: MAX_ALIVE_AGE ±1, KEEP_ALIVE ±1, Inferenz-Offsets ±1 |
| **Äquivalenzklassenbildung** | Cluster 5 (P16–P19), Cluster 6 (P20–P21) | RESN-Werte (none, privacy, confidential) × Rollen; default_resn-Typen |
| **Entscheidungstabellentest** | Cluster 4 (P14–P15), Cluster 8 (P24) | SHOW_LIVING_NAMES (3 Stufen) × Rollen; Suche × Privacy-Zustand × Rolle |
| **Anwendungsfall-Test** | Cluster 8–9 (P25–P29) | End-to-End-Szenarien: Seitenaufruf → Sichtbarkeitsprüfung → Edit → DB-Persistenz |
| **Paarweiser Test** | Cluster 1–2 (P01–P03) | Kombinatorik: REQUIRE_AUTHENTICATION × HIDE_LIVE_PEOPLE × SHOW_DEAD_PEOPLE × Rolle — paarweise statt volles Produkt |

---

## Rollenmatrix — Erwartete Sichtbarkeit

> Zeigt die erwartete Sichtbarkeit für jede Kombination aus Personenzustand und Rolle.
> Annahme: Standardkonfiguration (HIDE_LIVE_PEOPLE=1, SHOW_DEAD_PEOPLE=2, MAX_ALIVE_AGE=120,
> SHOW_LIVING_NAMES=1, SHOW_PRIVATE_RELATIONSHIPS=1, KEEP_ALIVE=0).

| Personenzustand | Besucher | Mitglied | Bearbeiter | Moderator | Verwalter | Admin |
|---|---|---|---|---|---|---|
| Verstorben (historisch) | ✅ Sichtbar | ✅ | ✅ | ✅ | ✅ | ✅ |
| Verstorben (kürzlich, KEEP_ALIVE greift) | ❌ Vertraulich | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lebend | ❌ Vertraulich | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lebend, RESN none | ✅ Sichtbar | ✅ | ✅ | ✅ | ✅ | ✅ |
| RESN privacy | ❌ Vertraulich | ✅ | ✅ | ✅ | ✅ | ✅ |
| RESN confidential | ❌ Vertraulich | ❌ Vertraulich | ❌ Vertraulich | ❌ Vertraulich | ✅ | ✅ |
| Lebend, Name sichtbar (SHOW_LIVING_NAMES=1) | Name ja, Details nein | ✅ | ✅ | ✅ | ✅ | ✅ |

### Schreibrechte-Matrix

> **Hinweis:** „pending vs. direkt" hängt nicht von der Rolle ab, sondern von der
> User-Preference `PREF_AUTO_ACCEPT_EDITS`. Ohne diese Preference erzeugen **alle**
> Rollen Pending Changes. Mit der Preference werden eigene Edits sofort akzeptiert.

| Aktion | Besucher | Mitglied | Bearbeiter | Moderator | Verwalter | Admin |
|---|---|---|---|---|---|---|
| Fakt hinzufügen | ❌ | ❌ | ✅ (pending*) | ✅ (pending*) | ✅ (pending*) | ✅ (pending*) |
| Fakt ändern | ❌ | ❌ | ✅ (pending*) | ✅ (pending*) | ✅ (pending*) | ✅ (pending*) |
| Locked Record/Fakt bearbeiten | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Pending Change akzeptieren | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Pending Change verwerfen | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Edit-Seite aufrufen | ❌ Access Denied | ❌ Access Denied | ✅ | ✅ | ✅ | ✅ |

> \* Mit `PREF_AUTO_ACCEPT_EDITS='1'` wird der eigene Pending Change sofort akzeptiert.

---

## Fixture-Design: `privacy-test.ged`

### Konzept: Template mit dynamischen Datums-Platzhaltern

Die Fixture `fixtures/privacy-test-template.ged` enthält GEDCOM mit Platzhaltern
für zeitabhängige Daten. Ein Generator-Skript/Funktion ersetzt die Platzhalter
zur Testlaufzeit.

**Platzhalter-Konvention:** `__YEAR_MINUS_<N>__` wird zu `date('Y') - N`.
Beispiel: `__YEAR_MINUS_120__` → `1906` (bei Testlauf in 2026).

### Benötigte Personen

| XREF | Bezeichnung | GEDCOM-Charakteristik | Zweck |
|---|---|---|---|
| `@P_DEAD_HISTORIC@` | Historisch Verstorbene | `1 DEAT\n2 DATE 15 MAR 1850` (statisch) | Baseline: definitiv tot, immer sichtbar |
| `@P_DEAD_EXPLICIT@` | Explizit Verstorben | `1 DEAT Y` (kein Datum) | P08: isDead()-Erkennung |
| `@P_DEAD_DATED@` | Verstorben mit Datum | `1 DEAT\n2 DATE 1 JAN __YEAR_MINUS_15__` | P06, P08: Kürzlich verstorben |
| `@P_DEAD_PLACED@` | Verstorben mit Ort | `1 DEAT\n2 PLAC Berlin` (kein Datum) | P08: isDead() via PLAC |
| `@P_ALIVE_YOUNG@` | Definitiv Lebend | `1 BIRT\n2 DATE 5 JUN __YEAR_MINUS_30__` (kein DEAT) | Baseline: lebend, geschützt |
| `@P_ALIVE_NO_DATES@` | Keine Daten | Nur NAME, kein BIRT/DEAT, keine Verwandten | P10: Fallback → als lebend angenommen |
| `@P_BOUNDARY_EXACT@` | Grenze exakt | `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_120__` (kein DEAT) | P04, P09: Exakt auf MAX_ALIVE_AGE |
| `@P_BOUNDARY_MINUS1@` | Grenze −1 | `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_119__` (kein DEAT) | P04: Noch als lebend angenommen |
| `@P_BOUNDARY_PLUS1@` | Grenze +1 | `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_121__` (kein DEAT) | P04: Als tot angenommen |
| `@P_KEEP_BIRTH_INSIDE@` | KEEP_ALIVE Geburt (innerhalb) | `1 DEAT Y` + `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_9__` | P05: Verstorben, aber Geburt innerhalb KEEP_ALIVE=10 → geschützt |
| `@P_KEEP_BIRTH_BOUNDARY@` | KEEP_ALIVE Geburt (Grenze) | `1 DEAT Y` + `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_10__` | P05: Exakt auf Grenze |
| `@P_KEEP_BIRTH_OUTSIDE@` | KEEP_ALIVE Geburt (außerhalb) | `1 DEAT Y` + `1 BIRT\n2 DATE 1 JAN __YEAR_MINUS_11__` | P05: Außerhalb → nicht geschützt |
| `@P_KEEP_DEATH_INSIDE@` | KEEP_ALIVE Tod (innerhalb) | `1 DEAT\n2 DATE 1 JAN __YEAR_MINUS_9__` | P06: Tod innerhalb KEEP_ALIVE=10 → geschützt |
| `@P_KEEP_DEATH_BOUNDARY@` | KEEP_ALIVE Tod (Grenze) | `1 DEAT\n2 DATE 1 JAN __YEAR_MINUS_10__` | P06: Exakt auf Grenze |
| `@P_KEEP_DEATH_OUTSIDE@` | KEEP_ALIVE Tod (außerhalb) | `1 DEAT\n2 DATE 1 JAN __YEAR_MINUS_11__` | P06: Außerhalb → nicht geschützt |
| `@P_RESN_NONE@` | RESN none | `1 RESN none` + Lebend | P16: Explizit öffentlich trotz lebend |
| `@P_RESN_PRIVACY@` | RESN privacy | `1 RESN privacy` | P17: Nur Mitglieder+ |
| `@P_RESN_CONFIDENTIAL@` | RESN confidential | `1 RESN confidential` | P18: Nur Verwalter+ |
| `@P_FACT_RESN_BIRT@` | Fakt-RESN auf Geburt | `1 BIRT\n2 DATE ...\n2 RESN privacy` | P19: Person sichtbar, BIRT nur für Mitglieder+ |
| `@P_FACT_RESN_DEAT@` | Fakt-RESN auf Tod | `1 DEAT\n2 DATE ...\n2 RESN confidential` | P19: Person sichtbar, DEAT nur für Verwalter+ |
| `@P_INFER_PARENT@` | Inferenz: Eltern | Keine eigenen Daten; FAMC → Familie mit alten Eltern | P11: isDead() via Eltern |
| `@P_INFER_PARENT_BOUNDARY@` | Inferenz: Eltern (Grenze) | Keine eigenen Daten; Eltern-Event exakt an Grenze (MAX_ALIVE_AGE+45) | P11: Grenzwert |
| `@P_INFER_SPOUSE@` | Inferenz: Ehepartner | Keine eigenen Daten; FAMS → Familie mit alter Heirat oder altem Ehepartner | P12: isDead() via Ehepartner |
| `@P_INFER_CHILD@` | Inferenz: Kinder | Keine eigenen Daten; FAMS → Familie mit alten Kindern | P13: isDead() via Kinder |
| `@P_INFER_GRANDCHILD@` | Inferenz: Enkel | Keine eigenen Daten; Kinder haben Kinder mit alten Events | P13: isDead() via Enkel |
| `@P_REL_CLOSE@` | Nah verwandt | Über 2 Schritte mit Test-User verwandt | P22: Innerhalb Pfadlänge |
| `@P_REL_FAR@` | Entfernt verwandt | Über 6 Schritte mit Test-User verwandt | P22: Außerhalb Pfadlänge |
| `@P_REL_UNRELATED@` | Nicht verwandt | Keine Familienverbindung zum Test-User | P22: Komplett außerhalb |
| `@P_REL_USER@` | Test-User selbst | Verknüpft mit PREF_TREE_ACCOUNT_XREF | P22, P23: Ankerpunkt für Relationship |
| `@P_RESN_LOCKED@` | RESN locked | `1 RESN locked` (nur Verwalter editieren, Sichtbarkeit unbeschränkt) | P29: Edit-Lock-Test |
| `@P_RESN_PRIV_LOCKED@` | RESN privacy+locked | `1 RESN privacy, locked` (Mitglieder+ sehen, nur Verwalter editieren) | P29: Kombination Sichtbarkeit+Lock |
| `@P_EDIT_TARGET@` | Bearbeitungsziel | Historisch Verstorbene, für Edit-Tests | P27, P28: Fakt hinzufügen/akzeptieren |

### Benötigte Familien

| XREF | Verknüpfung | Zweck |
|---|---|---|
| `@F_INFER_PARENT@` | HUSB/WIFE = alte Person(en), CHIL = `@P_INFER_PARENT@` | P11: Eltern-Inferenz |
| `@F_INFER_PARENT_BOUNDARY@` | HUSB/WIFE = Grenz-Eltern, CHIL = `@P_INFER_PARENT_BOUNDARY@` | P11: Grenzwert |
| `@F_INFER_SPOUSE@` | HUSB = `@P_INFER_SPOUSE@`, WIFE = alte Person; MARR-Datum | P12: Ehepartner-Inferenz |
| `@F_INFER_CHILD@` | HUSB = `@P_INFER_CHILD@`, CHIL = Person mit alten Events | P13: Kinder-Inferenz |
| `@F_INFER_GRANDCHILD@` | Kinder-Familie mit Enkeln | P13: Enkel-Inferenz |
| `@F_REL_1@` | `@P_REL_USER@` ↔ `@P_REL_CLOSE@` (über 1 Familie) | P22: Nahe Beziehung |
| `@F_REL_CHAIN@` | Kette von Familien: User → ... → `@P_REL_FAR@` | P22: Entfernte Beziehung |

### Dynamische Generierung

#### In Teststufe 2 (PHPUnit)

```php
// In PrivacyTestCase.php (neue Basisklasse, erweitert MysqlTestCase)
protected function generatePrivacyGedcom(): string
{
    $template = file_get_contents(__DIR__ . '/../../fixtures/privacy-test-template.ged');
    $currentYear = (int) date('Y');

    $replacements = [];
    // Platzhalter __YEAR_MINUS_N__ → konkretes Jahr
    preg_match_all('/__YEAR_MINUS_(\d+)__/', $template, $matches);
    foreach ($matches[1] as $offset) {
        $replacements['__YEAR_MINUS_' . $offset . '__'] = (string) ($currentYear - (int) $offset);
    }

    return strtr($template, $replacements);
}

protected function createPrivacyTree(): Tree
{
    $gedcom = $this->generatePrivacyGedcom();
    // Temporäre Datei schreiben, importieren, Datei löschen
    $tmpFile = tempnam(sys_get_temp_dir(), 'privacy_') . '.ged';
    file_put_contents($tmpFile, $gedcom);
    $tree = $this->createTreeWithGedcom('privacy', 'Privacy Test', $tmpFile);
    unlink($tmpFile);
    return $tree;
}
```

#### In Teststufe 3 (Playwright)

Option A — Setup-Skript erweitern:

```bash
# In scripts/setup-webtrees.sh oder separates scripts/generate-privacy-fixture.sh
CURRENT_YEAR=$(date +%Y)
sed "s/__YEAR_MINUS_\([0-9]*\)__/$(echo "scale=0; $CURRENT_YEAR - \1" | bc)/g" \
    fixtures/privacy-test-template.ged > fixtures/privacy-test.ged
# Import als dritter Baum "privacy"
```

Option B — PHP-Generator im Container:

```bash
podman-compose exec webtrees php /fixtures/generate-privacy-fixture.php
```

**Empfehlung:** Option A — einfacher, Shell-basiert, kein PHP nötig für Generierung.
Der generierte `privacy-test.ged` wird als dritter Baum neben `demo` und `muster`
importiert.

---

## Test-Infrastruktur: Neue Dateien

### Teststufe 2 (Komponentenintegrationstest)

| Datei | Zweck |
|---|---|
| `layer3-integration/tests/PrivacyTestCase.php` | Neue Basisklasse (erweitert MysqlTestCase): GEDCOM-Generierung, Rollen-Setup-Helfer, Privacy-Tree |
| `layer3-integration/tests/PrivacyVisibilityTest.php` | P01–P07, P14–P15: Stammbaum-Sichtbarkeit, Verstorbene/Lebende, KEEP_ALIVE, Namen/Beziehungen |
| `layer3-integration/tests/IsDeadTest.php` | P08–P13: isDead()-Algorithmus, Grenzwertanalyse, Verwandten-Inferenz |
| `layer3-integration/tests/ResnPrivacyTest.php` | P16–P21: RESN-Tags (Record + Fact), default_resn |
| `layer3-integration/tests/RelationshipPrivacyTest.php` | P22–P23: Pfadlängen-basierte Sichtbarkeit |
| `layer3-integration/tests/PrivacySearchTest.php` | P24: Privacy in Suchergebnissen |
| `layer3-integration/tests/AccessControlTest.php` | P27–P29: Bearbeiter-Edit + DB-Persistenz, Moderator-Akzeptanz, RESN locked, auto_accept |

### Teststufe 3 (Systemtest)

| Datei | Zweck |
|---|---|
| `layer4-e2e/tests/privacy-visibility.spec.ts` | P01–P03, P14, P25: Seitenaufruf pro Rolle, Vertraulich-Platzhalter |
| `layer4-e2e/tests/privacy-resn.spec.ts` | P16–P19: RESN-Sichtbarkeit im Browser |
| `layer4-e2e/tests/privacy-search.spec.ts` | P24: Geschützte Personen in Suchergebnissen |
| `layer4-e2e/tests/privacy-charts.spec.ts` | P26: Vertrauliche Boxen in Charts |
| `layer4-e2e/tests/privacy-relationship.spec.ts` | P22: Relationship Privacy im Browser |
| `layer4-e2e/tests/access-control.spec.ts` | P27–P29: Edit-Buttons, Pending Changes, Rollensperre |
| `layer4-e2e/helpers/privacy-roles.ts` | Shared Utility: Rollen-Login-Helfer (Besucher/Mitglied/Bearbeiter/Moderator/Verwalter) |

### Fixture

| Datei | Zweck |
|---|---|
| `fixtures/privacy-test-template.ged` | GEDCOM-Template mit `__YEAR_MINUS_N__`-Platzhaltern |
| `scripts/generate-privacy-fixture.sh` | Shell-Skript: Template → `fixtures/privacy-test.ged` mit aktuellen Daten |

---

## Rollen-Setup-Strategie

### Teststufe 2 (PHPUnit)

```php
// In PrivacyTestCase.php
protected function createUserWithRole(string $role, Tree $tree): UserInterface
{
    static $counter = 0;
    $counter++;
    $user = $this->userService->create(
        "test-{$role}-{$counter}",
        "Test " . ucfirst($role),
        "test-{$role}-{$counter}@test.local",
        'password'
    );
    $user->setPreference('verified', '1');
    $user->setPreference('verified_by_admin', '1');

    // Rolle zuweisen
    $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, match($role) {
        'visitor'   => '', // Kein Eintrag = Besucher
        'member'    => UserInterface::ROLE_MEMBER,
        'editor'    => UserInterface::ROLE_EDITOR,
        'moderator' => UserInterface::ROLE_MODERATOR,
        'manager'   => UserInterface::ROLE_MANAGER,
        default     => throw new \InvalidArgumentException("Unknown role: {$role}"),
    });

    return $user;
}

protected function setAccessLevel(UserInterface $user): void
{
    Auth::login($user);
}
```

### Teststufe 3 (Playwright)

```typescript
// In helpers/privacy-roles.ts
export const privacyRoles = ['visitor', 'member', 'editor', 'moderator', 'manager'] as const;
export type PrivacyRole = typeof privacyRoles[number];

export async function loginAsRole(page: Page, role: PrivacyRole): Promise<void> {
    if (role === 'visitor') {
        // Nicht anmelden (oder abmelden)
        await page.goto('/logout');
        return;
    }
    await page.goto('/login');
    await page.fill('#username', `test-${role}`);
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');
}
```

**Setup-Skript muss erweitert werden** (`scripts/setup-webtrees.sh`):
Neben dem Admin-User werden 4 weitere User angelegt (member, editor, moderator, manager)
mit den entsprechenden Rollen im `privacy`-Baum.

---

## Geschätzte Testfallzahlen

### Teststufe 2 — Komponentenintegrationstest

| Testklasse | Features | Geschätzte Testmethoden | Methode |
|---|---|---|---|
| `PrivacyVisibilityTest` | P01–P07, P14–P15 | ~40 | DataProvider: Rollen × Einstellungen × Personenzustände |
| `IsDeadTest` | P08–P13 | ~30 | DataProvider: Grenzwerte × Inferenz-Pfade |
| `ResnPrivacyTest` | P16–P21 | ~25 | DataProvider: RESN-Stufen × Rollen × Record/Fact |
| `RelationshipPrivacyTest` | P22–P23 | ~10 | Pfadlängen-Variationen |
| `PrivacySearchTest` | P24 | ~10 | Suche × Personenzustand × Rolle |
| `AccessControlTest` | P27–P29 | ~20 | Edit/Accept/Lock × Rolle × DB-Prüfung × auto_accept |
| **Summe Teststufe 2** | | **~135** | |

### Teststufe 3 — Systemtest

| Spec-Datei | Features | Geschätzte Tests | Methode |
|---|---|---|---|
| `privacy-visibility.spec.ts` | P01–P03, P14, P25 | ~20 | Rollen × Seitentypen × Platzhalter-Prüfung |
| `privacy-resn.spec.ts` | P16–P19 | ~12 | RESN-Stufen × Rollen |
| `privacy-search.spec.ts` | P24 | ~8 | Suche × Rolle × Sichtbarkeit |
| `privacy-charts.spec.ts` | P26 | ~6 | Chart-Typ × Einstellung |
| `privacy-relationship.spec.ts` | P22 | ~6 | Pfadlänge × Person |
| `access-control.spec.ts` | P27–P29 | ~15 | Edit/Accept/Deny × Rolle |
| **Summe Teststufe 3** | | **~67** | |

### Gesamtschätzung

| | Testmethoden | Features |
|---|---|---|
| Teststufe 2 | ~135 | P01–P24, P27–P29 |
| Teststufe 3 | ~67 | P01–P03, P14–P19, P22, P24–P29 |
| **Gesamt** | **~202** | **P01–P29 (29 Features)** |

---

## Testorakel — Orakelquellen

| Orakel | Gilt für Feature-IDs | Methode |
|---|---|---|
| `privacy-test-template.ged` (bekannte Personen mit definierten Zuständen) | P01–P26 | Erwartete Sichtbarkeit pro Person × Rolle |
| webtrees-Quellcode (`Individual::canShowByType()`, `GedcomRecord::canShowRecord()`, `Fact::canShow()`) | P01–P24 | Code-Verhalten = Spezifikation (Code-first RE) |
| `Auth::accessLevel()` Mapping | P27–P29 | Rollen-Hierarchie → erwartete Schreibrechte |
| Erwartetes DOM (Playwright-Selektoren) | P25, P26, P29 | „Vertraulich"-Text, leere Boxen, fehlende Edit-Buttons |
| DB-Tabellen (`change`, `individuals`, `gedcom_chunk`) | P27, P28 | Pending Changes in DB, akzeptierte Änderungen im GEDCOM |

---

## Implementierungsreihenfolge (Vorschlag)

| Phase | Deliverable | Abhängigkeiten |
|---|---|---|
| Phase P1 — Fixture & Infrastruktur | `privacy-test-template.ged`, `generate-privacy-fixture.sh`, `PrivacyTestCase.php`, `privacy-roles.ts`, Setup-Skript-Erweiterung | — |
| Phase P2 — isDead()-Tests (Teststufe 2) | `IsDeadTest.php` (P08–P13, ~30 Tests) | P1 |
| Phase P3 — Visibility-Tests (Teststufe 2) | `PrivacyVisibilityTest.php` (P01–P07, P14–P15, ~40 Tests) | P1 |
| Phase P4 — RESN-Tests (Teststufe 2) | `ResnPrivacyTest.php` (P16–P21, ~25 Tests) | P1 |
| Phase P5 — Relationship & Suche (Teststufe 2) | `RelationshipPrivacyTest.php` + `PrivacySearchTest.php` (P22–P24, ~20 Tests) | P1 |
| Phase P6 — Zugriffskontrolle (Teststufe 2) | `AccessControlTest.php` (P27–P29, ~20 Tests: Edit, Accept, Lock, auto_accept) | P1 |
| Phase P7 — Systemtests (Teststufe 3) | Alle 6 Spec-Dateien (P01–P29 Browser-Tests, ~67 Tests) | P1, Setup-Erweiterung (User-Anlage) |
| Phase P8 — Testlauf & Fehlerbereinigung | `make test-all` grün, Iterationsrunden | P2–P7 |

---

## Abgrenzung

| In Scope | Nicht in Scope |
|---|---|
| Alle 6 Rollen (Besucher bis Administrator) | Admin-Panel-Seiten (Konfigurationsseiten) |
| Record-Level und Fact-Level Privacy | Medien-Privacy (OBJE-Sichtbarkeit separat) |
| isDead()-Algorithmus vollständig | Kalender-Privacy (komplexe Aggregation) |
| RESN-Tags + default_resn-Tabelle | GEDCOM-Privacy-Export (bereits in G16) |
| Relationship Privacy (Pfadlänge) | Multi-Tree-Privacy (Cross-Tree-Zugriff) |
| Bearbeiter-Edit + Moderator-Akzeptanz | Alle Editor-Formulare (nur Proof-of-Concept) |
| Suchergebnis-Privacy | AJAX-/TomSelect-Privacy (AutoComplete) |
| Theme-unabhängig (kein Theme-Loop) | Theme-spezifisches Rendering |

### Theme-Abdeckung

Privacy-Tests sind **theme-unabhängig** — die Privacy-Logik ist serverseitig und
funktional identisch über alle Themes. Kein Theme-Loop in den Privacy-Specs.
Ausnahme: Falls ein Theme den „Vertraulich"-Platzhalter unterschiedlich rendert,
wird das als Bug (nicht als Testfall) behandelt.

---

## Konventionen (Ergänzung zu bestehenden)

### Namenskonvention (PHPUnit)

```
test_<privacy_feature>_<zustand>_<rolle>_<erwartetes_ergebnis>

Beispiele:
test_show_dead_people_visitor_setting_visitor_sees_dead_person
test_max_alive_age_boundary_exact_visitor_person_is_dead
test_is_dead_inference_via_parents_old_parent_events_returns_true
test_resn_confidential_member_cannot_see_record
test_editor_adds_fact_creates_pending_change_in_db
```

### Namenskonvention (Playwright)

```
P<ID> — <Beschreibung> (<Rolle>)

Beispiele:
'P01 — tree requires authentication (visitor redirected)'
'P16 — RESN none overrides living privacy (visitor sees person)'
'P27 — editor adds fact (pending change visible)'
```

### Verfolgbarkeit

```php
/**
 * @covers \Fisharebest\Webtrees\Individual::canShowByType
 * @covers \Fisharebest\Webtrees\Individual::isDead
 * @see docs/plan-privacy-testing-prompt.md P04, P09
 */
class IsDeadTest extends PrivacyTestCase
```

```typescript
/**
 * @see docs/plan-privacy-testing-prompt.md P25
 */
test('P25 — person page shows private placeholder for visitor', async ({ page }) => {
```

---

## Geklärte Punkte (Code-Analyse 2026-03-28)

### 1. Pending-Change-Mechanismus und auto_accept

**Ergebnis:** Alle Edits — unabhängig von der Rolle — erzeugen **immer** einen Pending
Change in der DB-Tabelle `change` mit `status='pending'`. Ob dieser sofort akzeptiert
wird, hängt ausschließlich von der User-Preference `PREF_AUTO_ACCEPT_EDITS` ab.

**Quellen:** `GedcomRecord::updateRecord()` (Zeile 828–873), `Tree::createRecord()` (Zeile
433–468), `PendingChangesService::acceptChange()` (Zeile 192–215).

| Aspekt | Bearbeiter | Moderator | Verwalter |
|---|---|---|---|
| Edit erzeugt Pending Change | Ja (immer) | Ja (immer) | Ja (immer) |
| Eigener Edit auto-akzeptiert wenn Preference=1 | Ja | Ja | Ja |
| Kann **fremde** Changes akzeptieren/verwerfen | Nein | Ja | Ja |
| Role bestimmt auto_accept | Nein | Nein | Nein |

**Auswirkung auf Testplan:**
- P27 (Bearbeiter-Edit): Test mit `PREF_AUTO_ACCEPT_EDITS=''` → Pending Change bleibt
  stehen. Test mit `PREF_AUTO_ACCEPT_EDITS='1'` → Change wird sofort akzeptiert.
- P28 (Moderator-Akzeptanz): Moderator akzeptiert einen **fremden** Pending Change.
  Eigene Edits des Moderators unterliegen ebenfalls der Preference.
- Die Schreibrechte-Matrix im Abschnitt „Rollenmatrix" wurde korrigiert: Es gibt
  keinen Unterschied „pending vs. direkt" per Rolle, sondern nur per User-Preference.

### 2. RESN locked — Edit-Lock, keine Sichtbarkeitswirkung

**Ergebnis:** `RESN locked` hat **keinerlei Auswirkung auf die Sichtbarkeit**. Die
Methoden `canShowRecord()` und `Fact::canShow()` prüfen nur `confidential`, `privacy`
und `none` — `locked` wird in der Sichtbarkeitskette ignoriert.

`locked` wirkt ausschließlich auf **Bearbeitungsrechte**:
- `Fact::canEdit()` prüft `str_ends_with($resn, 'locked')` — gesperrte Fakten
  können nur von Verwaltern bearbeitet werden.
- `GedcomRecord::canEdit()` prüft `1 RESN ...locked` — gesperrter Record kann
  nur von Verwaltern bearbeitet werden.

**Kombinierbarkeit:** `locked` kann mit anderen RESN-Werten kombiniert werden.
Die kanonische Form ist immer `<VISIBILITY>, LOCKED`:
- `NONE, LOCKED` — Sichtbar für Besucher, nur Verwalter editieren
- `PRIVACY, LOCKED` — Sichtbar für Mitglieder, nur Verwalter editieren
- `CONFIDENTIAL, LOCKED` — Sichtbar für Verwalter, nur Verwalter editieren

**Quellen:** `RestrictionNotice.php` (Zeile 56–63), `Fact.php` (Zeile 372),
`GedcomRecord.php` (Zeile 284–287).

**Auswirkung auf Testplan:**
- RESN locked wird als **ergänzender Testfall in P29** (Zugriffskontrolle) integriert:
  Editor versucht, einen locked Record zu bearbeiten → Bearbeitung nicht möglich.
- Kein separates Feature-ID nötig — es ist eine Untervariante der Edit-Rechte.
- In der Fixture `privacy-test-template.ged` wird eine Person mit `1 RESN locked`
  und eine mit `1 RESN privacy, locked` ergänzt.

### 3. Playwright-URLs für den Privacy-Baum

**URL-Format (aus `WebRoutes.php`):**

| Seitentyp | URL-Pattern | Beispiel im `privacy`-Baum |
|---|---|---|
| Personenseite | `/tree/{name}/individual/{xref}` | `/tree/privacy/individual/P_RESN_NONE` |
| Familienseite | `/tree/{name}/family/{xref}` | `/tree/privacy/family/F_INFER_PARENT` |
| Ahnentafel | `/tree/{name}/pedigree` | `/tree/privacy/pedigree` |
| Allgemeine Suche | `/tree/{name}/search-general` | `/tree/privacy/search-general` |
| Erweiterte Suche | `/tree/{name}/search-advanced` | `/tree/privacy/search-advanced` |

**Verhalten bei fehlender Berechtigung:** Kein HTTP-Redirect, sondern
`HttpAccessDeniedException` → Fehlerseite mit Meldung
_"This individual does not exist or you do not have permission to view it."_

**Sonderfall Charts:** `Auth::checkIndividualAccess($individual, false, true)` —
der dritte Parameter `$chart=true` ermöglicht die Darstellung vertraulicher Boxen,
wenn `SHOW_PRIVATE_RELATIONSHIPS=1`.

**Auswirkung auf Testplan:**
- P25 (Vertraulich-Platzhalter): Prüfung auf Fehlerseite/Access-Denied-Text,
  **nicht** auf Redirect. Assertion: `page.locator('.alert')` oder HTTP-Status.
- P26 (Charts): Chart-URLs mit `$chart=true`-Modus testen — dort werden
  vertrauliche Boxen gerendert statt Access-Denied.
- XREF-Mapping: Die XREFs aus `privacy-test-template.ged` sind stabil (z.B.
  `P_RESN_NONE`, `P_ALIVE_YOUNG`) und können direkt in den Playwright-URLs
  referenziert werden.

### 4. Setup-Reihenfolge — Kein Interferenz-Risiko

**Ergebnis:** Der dritte Baum `privacy` wird als zusätzlicher Eintrag in die
`$fixtures`-Array-Liste in `setup-webtrees.sh` aufgenommen:

```php
$fixtures = [
    ['name' => 'demo',    'title' => 'Demo Tree',           'file' => $fixturesDir . '/demo.ged'],
    ['name' => 'muster',  'title' => 'Muster (GEDCOM-L)',   'file' => $fixturesDir . '/gedcom-l-muster.ged'],
    ['name' => 'privacy', 'title' => 'Privacy Test Tree',   'file' => $fixturesDir . '/privacy-test.ged'],
];
```

**Warum kein Interferenz-Risiko:**
1. **Idempotente Existenzprüfung:** `if (DB::table('gedcom')->where('gedcom_name', '=', $name)->count() > 0) { skip }`
2. **Tests verwenden Hash-Suffixe:** `MysqlTestCase::createTreeWithGedcom()` erzeugt
   `demo-a1b2c3d4` (eindeutiger Hash pro Testmethode). Setup-Bäume haben keinen Hash.
3. **Teststufe-2-Tests erstellen/löschen eigene Bäume:** Kein Zugriff auf Setup-Bäume.
4. **Teststufe-3-Tests (Playwright) verwenden dedizierte URLs:** `/tree/privacy/...`
   statt `/tree/demo/...` — keine Kollision mit bestehenden E2E-Tests.

**Reihenfolge der dynamischen Fixture-Generierung:**
```
make setup
  → setup-webtrees.sh
    → generate-privacy-fixture.sh (Template → privacy-test.ged)
    → PHP: Import demo.ged, muster.ged, privacy-test.ged
```

### 5. Upstream-Bug FamilyFactory — Bekannte Einschränkung

**Ergebnis:** Der Bug betrifft `FamilyFactory::mapper()`, der `null` zurückgibt,
wenn alle Familienmitglieder privat sind. Dies tritt bei `PRIV_NONE` und
`PRIV_USER` Access-Levels auf.

**Betroffen:** Export-Szenarien mit Privacy-Filterung (G16) und Citation-AutoComplete.
**Nicht betroffen:** `canShow()`/`canShowByType()`-Aufrufe, die den Mapper nicht nutzen.

**Auswirkung auf Privacy-Tests:**
- **P24 (Privacy in Suchergebnissen):** Nicht betroffen — Suche nutzt `SearchService`,
  nicht `FamilyFactory::mapper()`.
- **P16–P18 (RESN auf Familien):** Bei Tests, die `Family::canShow()` direkt prüfen:
  nicht betroffen. Bei Tests, die Familien über Export oder Collection-Mapping laden:
  potenziell betroffen.
- **Maßnahme:** In `ResnPrivacyTest.php` werden FAM-Privacy-Tests über
  `Registry::familyFactory()->make()` statt über den Mapper ausgeführt. Tests, die
  den Mapper-Bug auslösen, werden mit `@see FamilyFactory mapper() upstream bug`
  dokumentiert und als `markTestSkipped()` behandelt, bis der Bug upstream gefixt ist.
- **Kein Workaround in der Fixture nötig** — die Tests testen die Privacy-Logik,
  nicht die Export-Pipeline.
