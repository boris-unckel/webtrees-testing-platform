<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Portierungs-Spezifikation: port-layer2-test-doubles → Layer 3

Verbindliche Spec für die maschinelle Portierung der 276 im Fork-Branch
`port-layer2-test-doubles` befüllten Test-Stubs in den L3-Bestand des
Testing-Platform-Repos. Diese Spec ist **byte-stabiler Input** für jeden
Worker-Lauf und darf zwischen Tests **nicht** verändert werden. Änderungen
nur im Konsens mit dem Hauptverantwortlichen.

## Kontext und Hintergrund

Im Upstream existieren ~280 leere Test-Stubs unter `tests/app/Http/...`,
`tests/app/Module/...`, `tests/app/Services/...`. Der Fork-Branch
`port-layer2-test-doubles` hat davon 276 Dateien KI-gestützt befüllt.
Upstream-Maintainer akzeptiert KI-generierten Code grundsätzlich nicht;
gleichzeitig hat Upstream-Commit `782151dd` die Stub/Mock-Taxonomie
(Domain-Objekte als `createStub()`, Services/Factories als `createMock()`)
übernommen.

Entscheidung: fachliche Substanz wird in das Testing-Platform-eigene
Layer 3 portiert. Layer 3 verliert dadurch die ISTQB-strikte
„Komponentenintegrationstest mit echten Abhängigkeiten"-Charakteristik
für die importierten Tests. Das ist bewusst akzeptiert; Begründung:
Laufzeit ist nicht der Engpass, fachliche Erkenntnis schlägt
Layer-Strenge.

## Scope

**In Scope:** Alle 276 Dateien aus `manifest.jsonl`.

**Out of Scope:** Andere Branches, neue Test-Ideen, Refactorings am
L3-Bestand jenseits der Anreicherung durch importierte Methoden.

## Quelle und Ziel

| Aspekt | Wert |
|---|---|
| Quell-Repo | `/home/borisunckel/phpprojects/webtrees-upstream/webtrees` |
| Quell-Branch | `port-layer2-test-doubles` |
| Ziel-Verzeichnis | `layer3-integration/tests/` (flach, Unterordner `Security/` zulässig) |
| Namespace Ziel | `DombrinksBlagen\WebtreesTests\Integration` |
| Lizenz-Header | `SPDX-License-Identifier: AGPL-3.0-or-later` (erste Zeile, Kommentar-Syntax `//` nach `<?php`) |

Quell-Dateien werden **nie** mutiert. Lesen erfolgt per `git show
port-layer2-test-doubles:<pfad>` oder direkt im ausgecheckten Branch
(read-only).

## Entscheidungsregel: anreichern vs. neue Datei

Pro Quell-Datei genau eine Entscheidung:

- **`enrich`** — es gibt eine bestehende L3-Testklasse, deren Thema
  die Quell-Datei abdeckt. Erkennung über:
  1. Klassenkommentar im L3-Bestand (siehe `l3-inventory.md`).
  2. `@covers`-Annotationen im L3-Bestand.
  3. Themenwörter im Quell-Dateinamen (z. B. `AccountDelete`,
     `AccountEdit`, `AccountUpdate` → `AccountSelfManagementIntegrationTest`).
  Aus der Quell-Datei werden **alle** sinnvollen Testmethoden als
  neue Methoden in die L3-Datei angefügt.
- **`new`** — keine themenverwandte L3-Datei vorhanden. Neue Datei
  unter `layer3-integration/tests/<Thema>IntegrationTest.php` anlegen.
  Namenskonvention: thematisch, nicht handler-spezifisch
  (`AccountSelfManagementIntegrationTest`, nicht
  `AccountDeleteIntegrationTest`). Wenn mehrere Quell-Dateien das
  Thema teilen, sammelt die neue Datei alle.

Im Zweifelsfall `new` wählen; spätere Konsolidierung ist günstiger
als Vermengung in der falschen Klasse.

## Idempotenz

Vor Beginn jeder Portierung **muss** der Worker prüfen, ob die
Quell-Datei bereits portiert ist:

- Heuristik 1: gibt es eine L3-Testmethode mit Inhalt, die einen
  Handler aus der Quell-Datei via `@covers` referenziert?
- Heuristik 2: gibt es eine L3-Testdatei, deren `@covers`-Liste
  oder Inhalt den primären Handler-Namespace der Quell-Datei
  bereits enthält?

Wenn ja → Status `done`, Decision `enrich` (oder `new` falls
eigenständige Datei existiert), Target gesetzt, keine
Code-Änderung. Audit-Eintrag mit `action="skip_already_ported"`.

Diese Idempotenz-Prüfung ist die Recovery-Strategie für
abgebrochene Läufe — sie erlaubt es, einen `in_progress`-Eintrag
gefahrlos zurück auf `pending` zu setzen.

## Stub/Mock-Konvention

Übernimmt die Taxonomie aus Upstream-Commit `782151dd`:

| Test-Double-Art | Methode | Beispiele |
|---|---|---|
| Stub (Domain-Objekt, Wert-orientiert) | `self::createStub(...)` | `Tree`, `Individual`, `Family`, `User`, `UserInterface` |
| Mock (Service/Factory, Verhalten-orientiert) | `$this->createMock(...)` | `TreeService`, `ModuleService`, `IndividualFactory`, `MessageService` |
| Interaktion verifizieren | `->expects($this->once())` etc. | nur bei Mocks, nur wenn fachlich sinnvoll |

Wenn die Quell-Datei eine abweichende Aufteilung wählt (z. B. alles
mit `createMock`), wird sie beim Import angeglichen. Stilistische
Konsistenz innerhalb einer L3-Datei hat Vorrang vor wörtlicher
Übernahme aus der Quelle.

## Konventionen (Pflicht)

Aus `tp_conventions_spec.md` und L3-Bestand:

- **AAA-Pattern**: jede Testmethode hat sichtbare Arrange / Act /
  Assert-Phasen, ggf. mit Leerzeilen oder Kommentaren getrennt.
- **FIRST-Prinzipien**: insbesondere Isolation — keine Reihenfolge-
  abhängigkeiten zwischen Methoden, keine Test-Fixture-Leaks.
- **`@covers`-Annotation** auf Klassen- oder Methodenebene mit voll
  qualifiziertem Klassennamen. Pflicht.
- **`@see`-Annotation** für Verfolgbarkeit zur Quell-Datei aus dem
  Branch:
  ```
  @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AccountDeleteTest.php
  ```
  Beim Anreichern auf Klassenebene als ergänzender Eintrag, beim
  Anlegen neuer Datei zusätzlich.
- **Klassenkommentar deutsch**, Identifiers und Code englisch.
  Methodennamen `test_handler_does_x_when_y` (snake_case) oder
  `testHandlerDoesXWhenY` (camelCase) — Konvention der aufnehmenden
  Datei beibehalten; bei neuer Datei: snake_case.
- **PHPUnit-Konfiguration**: Tests laufen unter
  `layer3-integration/phpunit-integration.xml`.

## Provenance-Kennzeichnung

Jeder importierte Test trägt am Methoden-Docblock einen Marker:

```
/**
 * @see Quelle: port-layer2-test-doubles:tests/.../AccountDeleteTest.php
 * @group ported-l2-doubles
 */
public function test_account_delete_admin_keeps_user(): void
```

Bei `enrich`: nur Methodenkommentar markieren, keine Mutation des
Klassenkommentars. Bei `new`: zusätzlich Klassenkommentar mit
Provenance-Block.

`@group ported-l2-doubles` ermöglicht spätere Selektion und
Sonderbehandlung dieser Tests (z. B. eigene Coverage-Auswertung,
gezielte Re-Review).

## Akzeptanzkriterien

Ein portierter Test gilt als `done`, wenn **alle** folgenden Punkte
zutreffen:

1. Test ist im Container ausführbar:
   `podman-compose exec webtrees vendor/bin/phpunit
   --configuration=/tests/layer3-integration/phpunit-integration.xml
   --filter='<NeueMethode|NeueKlasse>'` liefert grün.
2. Test enthält keine `@group skip` oder `markTestSkipped`-Aufrufe
   ohne dokumentierte Begründung.
3. `@covers`-Liste vollständig und korrekt.
4. `@see Quelle:` und `@group ported-l2-doubles` gesetzt.
5. Stub/Mock-Konvention angewendet.
6. PHPCS auf die geänderte Datei läuft fehlerfrei (PSR-12).
   **Ausnahme:** snake_case-Methodennamen erzeugen im gesamten L3-Bestand
   Warnungen (PSR1.Methods.CamelCapsMethodName). Diese Warnungen gelten
   als akzeptierte Baseline und werden nicht als Fehlschlag gewertet,
   solange die neue Datei keine über die Baseline hinausgehenden
   PHPCS-Fehler enthält.
7. PHPStan auf die geänderte Datei läuft ohne neue Fehler
   (Baseline halten).
   **Ausnahme:** `phpstan-integration.neon` existiert im L3-Bestand nicht.
   Kriterium 7 ist strukturell nicht prüfbar und gilt für alle Portierungen
   als erfüllt, bis eine PHPStan-Konfiguration für Layer 3 eingerichtet wird.
8. Beim `enrich`: bestehende Methoden der Aufnahme-Datei unverändert.

Fehlschlag eines Punkts → Status `failed`, Audit-Eintrag mit
Begründung, Datei-Änderungen reverten oder klar markieren. Bei
`failed` wird das Manifest auf `failed` gesetzt, **nicht** zurück
auf `pending` — manuelle Sichtung erforderlich.

## Output-Format des Workers

Jeder Worker liefert exakt ein JSON-Objekt als Endergebnis:

```json
{
  "id": "042",
  "decision": "enrich",
  "target": "layer3-integration/tests/AccountSelfManagementIntegrationTest.php",
  "methods_added": 3,
  "lines_added": 87,
  "validated": true,
  "notes": "AccountDeleteTest: 3 Methoden, 2 Stubs angeglichen (User → createStub)"
}
```

Erlaubte Felder:

| Feld | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| `id` | string | ja | Manifest-ID |
| `decision` | enum | ja | `enrich` \| `new` \| `skip_already_ported` |
| `target` | string | ja | L3-Datei (existierend oder neu) |
| `methods_added` | int | ja | Anzahl neu hinzugefügter Testmethoden |
| `lines_added` | int | ja | Netto-Zeilen-Zuwachs in der Zieldatei |
| `validated` | bool | ja | Akzeptanzkriterium 1 erfüllt? |
| `notes` | string | optional | Kurz, eine Zeile, max 200 Zeichen |
| `failure_reason` | string | bei `validated=false` | Welches Akzeptanzkriterium gerissen |

Worker schreibt **nichts** ins Manifest oder Audit-Log — das macht
der Orchestrator. Worker schreibt nur die Zieldatei.

## Recovery-Strategie (ohne Git während des Laufs)

Git-Commits erfolgen erst am Schluss (GPG-Signing, manuelle
Sichtung). Damit ist Git **nicht** die Wahrheit während des Laufs.
Wahrheitsschichten in absteigender Priorität:

1. **Dateisystem-Zustand der L3-Dateien** (Worker hat geschrieben
   oder nicht).
2. **`manifest.jsonl`** (Orchestrator-Projektion).
3. **`audit.log`** (Aktivitätsspur).

Recovery-Routine beim Start des Orchestrators:

- Für jeden Eintrag mit `status="in_progress"`: Orchestrator hält
  an und meldet sich. Bei seriellem Lauf maximal 1 betroffener
  Eintrag.
- Vom Operator entschieden: entweder
  - manuell prüfen, ob Zieldatei den importierten Inhalt enthält,
    dann Status auf `done` setzen, oder
  - Idempotenz-Prüfung greift im nächsten Worker-Lauf — Status
    zurück auf `pending`, Worker erkennt „bereits portiert" und
    setzt `skip_already_ported`.

`failed` bleibt `failed` bis manuelle Behebung.

## Parallelität

**Seriell.** Begründung: L3 läuft auf gemeinsamem MySQL-Container
(siehe `CLAUDE.md`, „exklusive Ausführung"). Validierung pro
Worker-Lauf nutzt diesen Container; parallele Worker würden
einander stören.

## Umgebungs-Lifecycle (Preflight, Sanity, Recovery)

Die Testplattform existiert ausschließlich im Container — PHP,
PHPUnit, PHPCS, PHPStan, MySQL stehen lokal nicht zur Verfügung.
Jeder Worker- und Orchestrator-Schritt setzt einen laufenden,
gesunden, eingerichteten Stack voraus.

### Preflight (einmalig beim Orchestrator-Start)

Schrittweise, **nicht als verkettete Befehlszeile abfeuern**.
Jeder Make-Schritt wird einzeln ausgeführt; der Orchestrator
wartet auf Rückkehr und prüft Exit-Code (= 0) sowie stdout/stderr
auf Fehler (z. B. „Error response from daemon", „cannot
connect", Restart-Loops, Wizard-Fehler, DB-Schreibfehler). Erst
bei sauberem Abschluss wird der nächste Schritt gestartet. Bei
Auffälligkeiten: Orchestrator hält an, kein Worker-Lauf,
Operator-Eingriff.

1. `make up` — Stack hochfahren.
2. `make setup` — webtrees installieren (legt initiale
   DB-Struktur und Test-User an).
3. `make status` als finale Verifikation. Erst jetzt darf der
   Orchestrator als „Umgebung bereit" loggen
   (`action="preflight_ok"`).

### Sanity-Pin

Fester L3-Test, der bei jedem Worker-Fail vorgeschaltet zur
Diagnose ausgeführt wird:

```
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='BootstrapOnlyIntegrationTest'
```

Kriterien: klein, DB-arm, zuletzt zuverlässig grün, fängt
Bootstrap-Probleme sicher ab.

- **Sanity grün** → echter Test-Fail des portierten Codes →
  Worker-Output bleibt `validated=false`, Orchestrator markiert
  Eintrag als `failed`, weiter mit nächstem.
- **Sanity rot** → Umgebung kaputt → Recovery (siehe unten),
  danach denselben Eintrag erneut starten (`status="pending"`).

### Recovery (einstufig, sequenziell)

Nur ausgelöst, wenn Sanity-Pin rot. Volle Neueinrichtung — kein
schrittweises Hochtasten, weil die Erfahrung zeigt, dass
Zwischenstufen oft nicht reichen. **Jeder Make-Schritt wird
einzeln ausgeführt**; der Orchestrator wartet auf Rückkehr und
prüft Exit-Code (= 0) sowie stdout/stderr auf Fehler. Erst bei
sauberem Abschluss wird der nächste Schritt gestartet. Keine
Verkettung mit `&&` in einer Zeile.

1. Prüfen, dass kein PHPUnit-Prozess mehr im Container läuft
   (sonst killen):
   ```
   podman-compose exec webtrees pgrep -a -f phpunit
   ```
   Wenn vorhanden: `kill <PID>` im Container.
2. `make down`.
3. `make clean`. Achtung: löscht Volumes und regeneriert
   Passwörter beim nächsten `up`. Bestehende `.env`-Anpassungen
   werden überschrieben.
4. `make up`.
5. `make setup`.
6. `make status` finale Verifikation.

Erst nach Schritt 6 grün: Sanity-Pin erneut ausführen.

- **Sanity-Pin nach Recovery grün** → Worker für denselben
  Eintrag erneut starten. Audit-Eintrag
  `action="recovery_success"`.
- **Sanity-Pin nach Recovery rot** → Orchestrator hält an,
  `action="recovery_failed"` loggen, Operator-Eingriff.

Recovery wird **nicht** automatisch wiederholt. Eine Runde, dann
Stop.

### Abschluss-Voll-Lauf

Nach Verarbeitung des 276. Eintrags führt der Orchestrator einen
einmaligen Voll-Lauf der gesamten L3-Suite aus:

```
make test-integration
```

Dieser Lauf prüft Cross-Test-Interferenz (DB-State-Leaks,
Fixture-Konflikte) zwischen den importierten und den
bestehenden Tests. Ergebnis wird als
`action="final_full_run"` mit `result="pass|fail"` und der
Anzahl roter Tests geloggt. Bei `fail`: Operator analysiert die
roten Tests gezielt; keine automatische Rückabwicklung.

## Was kein Worker tun darf

- Den Quell-Branch verändern (read-only).
- Außerhalb von `layer3-integration/tests/` schreiben.
- `composer.json`, `phpunit-integration.xml`, `bootstrap.php`
  modifizieren. Wenn ein Test eine neue Dependency oder
  Bootstrap-Anpassung braucht, → Status `failed` mit
  `failure_reason="needs_infrastructure_change"`.
- Bestehende L3-Testmethoden mutieren.
- Eigene Testlogik dazu-erfinden, die nicht in der Quell-Datei
  vorkommt.
- Ins Manifest oder Audit-Log schreiben.
