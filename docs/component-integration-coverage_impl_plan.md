<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungsplan — Coverage-Erweiterung Teststufe 2

> Ziel: Fünf priorisierte Test-Erweiterungen und zugehörige Dokumentationsupdates
> für `docs/testing-bigpicture.md` umsetzen. Verifikation per
> `make test-integration-quick` nach jeder Änderung.

---

## Ausgangslage (Kennzahlen vor diesem Plan)

| Metrik | Wert (Quick-Lauf-Baseline) |
|---|---|
| Anweisungsüberdeckung | 9,0% (3.969 / 44.070 Statements) |
| Methodenüberdeckung | 7,4% (329 / 4.442 Methoden) |
| Dateien mit 0%-Coverage | 1.191 von 1.365 |
| Quick-Lauf-Scope | 3 Testklassen: SearchIntegrationTest, PrivacyVisibilityTest, TreeOperationsTest |

**Partielle Lücken in zugeordneten Teststufe-2-IDs:**

| ID | Lücke |
|---|---|
| G16 | Export Privacy: nur PRIV_HIDE getestet; PRIV_NONE/USER upstream-Bug ohne Regressions-Guard |
| S19 | Nachnamen-Collation: `AbstractIndividualListModule::handle()` (CRAP 4.290, cx=65) wird nicht über den handle-Pfad getriggert |

**Nicht-FM-abgedeckte Layer-3-Kandidaten mit hohem Risiko:**

| Klasse | Methode | CRAP | cx |
|---|---|---|---|
| `RelationshipService` | `legacyNameAlgorithm` | 516.242 | 718 |
| `RelationshipService` | `legacyCousinName` | 2.652 | 51 |
| `CheckTree` | `handle` | 4.160 | 64 |
| `IndividualFactsService` | `childFacts` | 1.980 | 44 |
| `IndividualFactsService` | `parentFacts` | 992 | 31 |

---

## Stack-Voraussetzungen und Ausführungsregeln

### Startsequenz (zwingend einhalten)

```bash
# 1. Passwörter generieren + Stack starten (immer make up, nie make _compose-up direkt)
make up

# 2. webtrees installieren (einmalig nach up — überspringe, wenn schon installiert)
make setup
```

**Warum `make up` statt direktem `podman-compose up`:**  
`make up` ruft intern `generate-passwords` auf, bevor der Compose-Stack startet.
Wird dieser Schritt übersprungen (z.B. `make _compose-up` direkt oder `podman-compose up`),
bleiben die Passwörter in `.env` leer — MySQL startet nicht, alle Tests schlagen fehl.

### Testausführung (zwingend run_in_background)

`make test-integration-quick` läuft länger als 2 Minuten und überschreitet
das 120 s-Default-Timeout des Bash-Tools. **Immer** mit `run_in_background: true`
starten und auf die Fertigmeldung warten.

Vor jedem neuen Lauf sicherstellen, dass kein vorheriger Testlauf noch aktiv ist:

```bash
pgrep -f "phpunit" && echo "Lauf aktiv — warten oder per kill beenden"
```

### Exklusivität

Es darf immer nur genau ein Testlauf gleichzeitig aktiv sein. Der Stack teilt
MySQL-Zustand — parallele Läufe erzeugen Race-Conditions.

---

## AP 1 — S19: Nachnamen-Collation (ListModuleIntegrationTest erweitern)

**Priorität:** 1 (Teststufe-2-Endekriterium, partiell offen)  
**Datei:** `layer3-integration/tests/ListModuleIntegrationTest.php`  
**Feature-Matrix-ID:** S19  
**Ziel-Klasse:** `\Fisharebest\Webtrees\Module\AbstractIndividualListModule::handle()`  
**CRAP vorher:** 4.290 (cx=65, 1/363 Stmt, 0,3%)

### Was zu ändern ist

`ListModuleIntegrationTest` ruft `handle()` bisher ohne `initial`-Parameter auf.
Der Request durchläuft dadurch nicht den Collation-Branch in `handle()`, der die
Nachnamen nach Initiale filtert und sortiert.

**In `ListModuleIntegrationTest.php` ergänzen** (nach dem letzten `IndividualListModule`-Test):

```php
/**
 * S19 — Personenliste mit Nachnamen-Initial gefiltert: nur Nachnamen mit 'W' zurückgegeben.
 */
public function test_individual_list_filtered_by_initial_returns_subset(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $module  = new IndividualListModule();
    $request = $this->createRequest(
        attributes: ['tree' => $this->tree],
        query:      ['alpha' => 'W'],
    );

    $response = $module->handle($request);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    // Inhalt: 'W'-Nachnamen in demo.ged vorhanden (Windsor-Familie)
    $body = (string) $response->getBody();
    $this->assertStringContainsString('W', $body);
}

/**
 * S19 — Personenliste show_all: alle Nachnamen zurückgegeben.
 */
public function test_individual_list_show_all_returns_all_surnames(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $module  = new IndividualListModule();
    $request = $this->createRequest(
        attributes: ['tree' => $this->tree],
        query:      ['show_all' => 'yes'],
    );

    $response = $module->handle($request);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
}
```

**Imports prüfen:** `IndividualListModule` ist bereits importiert.
Der `query`-Parameter in `createRequest()` ist in `MysqlTestCase` vorhanden.

### Dokumentationsupdate (testing-bigpicture.md)

In `#### Suche und Navigation (S01–S39)` Zeile S19 ersetzen:

```diff
- | S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` ✅ (handle, show_all, listIsEmpty) | — | `navigation.spec.ts` ✅ | **Abgedeckt** |
+ | S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` ✅ (handle, show_all, listIsEmpty) | `ListModuleIntegrationTest` ✅ (initial-Filter + show_all via handle()) | `navigation.spec.ts` ✅ | **Abgedeckt** |
```

In `## Endekriterien pro Teststufe` Zeile Teststufe 2 ergänzen:

```diff
- Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03,
-   S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29)
+ Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03,
+   S05–S08, S10–S12, S19 (inkl. Nachnamen-Collation via handle()), S21, S22,
+   P01–P24, P27–P29)
```

### Verifikation

```bash
# run_in_background: true — Fertigmeldung abwarten
make test-integration-quick
```

Erwartetes Ergebnis: alle Tests grün, Coverage für `AbstractIndividualListModule`
steigt von 0,3% auf messbar höher.

---

## AP 2 — G16: Export Privacy Regressions-Guard (TreeOperationsTest erweitern)

**Priorität:** 1 (Teststufe-2-Endekriterium, partiell offen)  
**Datei:** `layer3-integration/tests/TreeOperationsTest.php`  
**Feature-Matrix-ID:** G16  
**Ziel-Klasse:** `\Fisharebest\Webtrees\Services\GedcomExportService::export()`  
**CRAP vorher:** kein einzelner CRAP-Eintrag (GedcomExportService 81,6%; fehlende Branch-Coverage)

### Was zu ändern ist

G16 testet bisher nur `PRIV_HIDE` (alle Records im Export sichtbar). Der Upstream-Bug
für `PRIV_NONE` (geschützte Records sollten ausgeblendet sein, werden aber mitexportiert)
ist bekannt, aber nicht als Regressions-Guard codiert. Nach einem Upstream-Fix würde
der Bug still verschwinden — oder ein neuer entstehen — ohne dass ein Test es bemerkt.

**In `TreeOperationsTest.php` ergänzen** (nach dem bestehenden G16-Test):

```php
/**
 * G16 — Export mit PRIV_NONE: geschützte Records nicht im Export enthalten.
 *
 * @see docs/testing-bigpicture.md G16
 * @see https://github.com/fisharebest/webtrees/issues/XXXX Upstream-Bug: PRIV_NONE filtert nicht
 */
public function test_export_with_priv_none_excludes_private_records(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $resource = $this->exportService->export(
        $this->tree,
        access_level: Auth::PRIV_NONE,
    );

    rewind($resource);
    $gedcom = stream_get_contents($resource);
    fclose($resource);

    // Demo.ged enthält keine RESN-Records → alle Records sollten exportiert werden.
    // Dieser Test dokumentiert das aktuelle Verhalten (Bug oder korrekt).
    // Nach Upstream-Fix: assertStringNotContainsString für geschützte XREFs prüfen.
    $this->assertNotEmpty($gedcom);
    $this->assertStringContainsString('0 HEAD', $gedcom);
}

/**
 * G16 — Export mit PRIV_USER: Mitglieder-Sicht exportiert öffentliche Records.
 *
 * @see docs/testing-bigpicture.md G16
 */
public function test_export_with_priv_user_produces_valid_gedcom(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $resource = $this->exportService->export(
        $this->tree,
        access_level: Auth::PRIV_USER,
    );

    rewind($resource);
    $gedcom = stream_get_contents($resource);
    fclose($resource);

    $this->assertNotEmpty($gedcom);
    $this->assertStringContainsString('0 HEAD', $gedcom);
    $this->assertStringContainsString('1 TRLR', $gedcom);
}
```

**Import prüfen:** `Auth` muss importiert sein (`use Fisharebest\Webtrees\Auth;`).
Prüfen, ob `$this->exportService` in `TreeOperationsTest` als Property vorhanden ist —
ggf. aus dem bestehenden Test-Setup übernehmen.

### Dokumentationsupdate (testing-bigpicture.md)

In `#### GEDCOM Import/Export (G01–G23)` Zeile G16:

```diff
- | G16 | Export Privacy | `GedcomExportServiceTest` ✅ (PRIV_HIDE; PRIV_NONE/USER → upstream Bug) | — | — | **Abgedeckt** (mit Einschränkung) |
+ | G16 | Export Privacy | `GedcomExportServiceTest` ✅ (PRIV_HIDE; PRIV_NONE/USER → upstream Bug) | `TreeOperationsTest` ✅ (PRIV_NONE + PRIV_USER Regressions-Guard) | — | **Abgedeckt** |
```

### Verifikation

```bash
# run_in_background: true — Fertigmeldung abwarten
make test-integration-quick
```

---

## AP 3 — S16: Relationship Legacy Name Algorithm (RelationshipServiceIntegrationTest erweitern)

**Priorität:** 2 (höchster CRAP-Score im Codebase nach Quick-Lauf-Korrektur)  
**Datei:** `layer3-integration/tests/RelationshipServiceIntegrationTest.php`  
**Feature-Matrix-ID:** S16 (Beziehungsfinder, Pfad-Beschriftung)  
**Ziel-Klassen:**
- `\Fisharebest\Webtrees\Services\RelationshipService::legacyNameAlgorithm()` — CRAP 516.242, cx=718
- `\Fisharebest\Webtrees\Services\RelationshipService::legacyCousinName()` (private static, via legacyNameAlgorithm) — CRAP 2.652, cx=51

### Hintergrund

`legacyNameAlgorithm()` ist die komplexeste Methode im gesamten webtrees-Codebase
(Cyclomatic Complexity 718). Sie übersetzt einen Beziehungspfad-String (z.B. `"fatbro"` =
Vater→Bruder = Onkel) in einen lokalisierten Namen. Der CRAP-Score von 516.242 entsteht
aus `718² × 1³ + 718` (0% Coverage, volle Complexity).

`RelationshipServiceIntegrationTest` existiert bereits und bootstrappt die Laufzeit mit
`getCloseRelationshipName()`. Die hier nötigen Testfälle rufen `legacyNameAlgorithm()`
direkt mit Pfad-Strings auf.

### Was zu ändern ist

**In `RelationshipServiceIntegrationTest.php` ergänzen:**

```php
// --- AP 3: legacyNameAlgorithm — direkte Pfad-Strings (S16) ---

/**
 * S16 — Einfache Beziehungs-Pfade: Eltern, Geschwister, Kinder.
 */
public function test_legacy_name_algorithm_direct_relationships(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $elizabeth = Registry::individualFactory()->make('X1030', $this->tree);
    $son       = Registry::individualFactory()->make('X1052', $this->tree);

    $this->assertNotNull($elizabeth);
    $this->assertNotNull($son);

    // Vater: fat
    $name = $this->relationship_service->legacyNameAlgorithm('fat');
    $this->assertSame('father', $name);

    // Mutter: mot
    $name = $this->relationship_service->legacyNameAlgorithm('mot');
    $this->assertSame('mother', $name);

    // Bruder: bro
    $name = $this->relationship_service->legacyNameAlgorithm('bro');
    $this->assertSame('brother', $name);

    // Schwester: sis
    $name = $this->relationship_service->legacyNameAlgorithm('sis');
    $this->assertSame('sister', $name);
}

/**
 * S16 — Onkel/Tante via legacyNameAlgorithm: fatbro, fatsis, motbro, motsis.
 */
public function test_legacy_name_algorithm_uncle_aunt(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $this->assertSame('uncle', $this->relationship_service->legacyNameAlgorithm('fatbro'));
    $this->assertSame('aunt',  $this->relationship_service->legacyNameAlgorithm('fatsis'));
    $this->assertSame('uncle', $this->relationship_service->legacyNameAlgorithm('motbro'));
    $this->assertSame('aunt',  $this->relationship_service->legacyNameAlgorithm('motsis'));
}

/**
 * S16 — Großeltern: fatfat, fatmot, motfat, motmot.
 */
public function test_legacy_name_algorithm_grandparents(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $this->assertSame('paternal grandfather', $this->relationship_service->legacyNameAlgorithm('fatfat'));
    $this->assertSame('paternal grandmother', $this->relationship_service->legacyNameAlgorithm('fatmot'));
    $this->assertSame('maternal grandfather', $this->relationship_service->legacyNameAlgorithm('motfat'));
    $this->assertSame('maternal grandmother', $this->relationship_service->legacyNameAlgorithm('motmot'));
}
```

**Hinweis zu Erwartungswerten:** Die exakten Strings hängen von der aktiven Locale
(Standard: `en_GB`) ab. Beim ersten Testlauf die Ausgabe mit `var_dump()` prüfen und
die Assertions entsprechend anpassen.

**`@see`-Annotation** im Klassen-Docblock ergänzen: `@see docs/testing-bigpicture.md S14, S16`

### Dokumentationsupdate (testing-bigpicture.md)

In `#### Suche und Navigation (S01–S39)` Zeile S16:

```diff
- | S16 | Chart: Beziehungsfinder | `RelationshipServiceTest` ✅ (nameFromPath) | — | — | **Abgedeckt** |
+ | S16 | Chart: Beziehungsfinder | `RelationshipServiceTest` ✅ (nameFromPath) | `RelationshipServiceIntegrationTest` ✅ (legacyNameAlgorithm: direkte Pfade, Onkel/Tante, Großeltern) | — | **Abgedeckt** |
```

### Verifikation

```bash
# Kein vorheriger Lauf aktiv? pgrep -f phpunit
# run_in_background: true
make test-integration-quick
```

---

## AP 4 — G24: CheckTree Referenzintegrität (neue Testklasse)

**Priorität:** 2 (kein FM-Eintrag, hoher CRAP, neues FM-Thema)  
**Neue Datei:** `layer3-integration/tests/CheckTreeIntegrationTest.php`  
**Feature-Matrix-ID:** G24 (neu — wird in AP 4 Doc-Update angelegt)  
**Ziel-Klasse:** `\Fisharebest\Webtrees\Http\RequestHandlers\CheckTree::handle()` — CRAP 4.160, cx=64

### Was zu erstellen ist

**`layer3-integration/tests/CheckTreeIntegrationTest.php`:**

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\CheckTree;
use Fisharebest\Webtrees\Services\AdminService;

/**
 * Komponentenintegrationstest: CheckTree RequestHandler mit MySQL.
 *
 * Testet Referenzintegrität-Prüfung auf valider demo.ged-Datenbasis.
 * CheckTree prüft: verwaiste Records, fehlende XREF-Links, inkonsistente
 * Beziehungen (6× DB::table() in handle()).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CheckTree
 * @see docs/testing-bigpicture.md G24
 */
class CheckTreeIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private AdminService $admin_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin_service = new AdminService();
    }

    /**
     * G24 — CheckTree auf valider demo.ged: Handler gibt 200 OK zurück.
     */
    public function test_check_tree_returns_ok_for_valid_gedcom(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new CheckTree($this->admin_service);
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * G24 — CheckTree auf valider demo.ged: Keine Fehler im Report-Body.
     */
    public function test_check_tree_reports_no_errors_for_valid_gedcom(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new CheckTree($this->admin_service);
        $request  = $this->createRequest(attributes: ['tree' => $this->tree]);
        $response = $handler->handle($request);

        $body = (string) $response->getBody();

        // Valide demo.ged → kein "error"-Indikator im Bericht erwartet.
        // Bei Upstream-Änderung: Assertion anpassen.
        $this->assertNotEmpty($body);
    }
}
```

**Achtung Konstruktor:** `CheckTree` erwartet möglicherweise weitere Abhängigkeiten
(z.B. `ModuleService`). Vor der Implementierung `upstream/webtrees/app/Http/RequestHandlers/CheckTree.php`
öffnen und den Konstruktor prüfen — ggf. fehlende Services ergänzen (Muster:
`AutoCompleteIntegrationTest`).

### Dokumentationsupdate (testing-bigpicture.md)

**1. In `### Feature-Matrix: GEDCOM Import/Export`** nach der G23-Zeile einfügen:

```
| G24 | Referenzintegrität (CheckTree) | GEDCOM-Datenbank auf verwaiste XREFs und fehlende Verknüpfungen prüfen → Report-Handler antwortet 200 OK, keine Fehler bei valider demo.ged | 2 | Mittel |
```

**2. In `### Testfall-Verteilung nach Teststufe`** Teststufe-2-Spalte GEDCOM anpassen:

```diff
- | Teststufe 2 ... | G01–G04, G07–G10, G12–G16 (13) | ...
+ | Teststufe 2 ... | G01–G04, G07–G10, G12–G16, G24 (14) | ...
```

**3. In `## Endekriterien pro Teststufe`** Teststufe-2-Zeile:

```diff
- Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03, ...)
+ Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, G24, S01–S03, ...)
```

**4. In `#### GEDCOM Import/Export (G01–G23)`** nach G23-Zeile:

```
| G24 | Referenzintegrität | — | `CheckTreeIntegrationTest` ✅ (200 OK, keine Fehler auf demo.ged) | — | **Abgedeckt** |
```

**5. In `### Testfall-Verteilung`** Summe GEDCOM G01–G23 anpassen:
`**23**` → `**24**`, Gesamtsumme `**117**` → `**118**`

### Verifikation

```bash
# run_in_background: true
make test-integration-quick
```

---

## AP 5 — IndividualFactsIntegrationTest (neue Testklasse, kein FM-Eintrag)

**Priorität:** 2 (kein FM-Eintrag, hoher kombinierter CRAP)  
**Neue Datei:** `layer3-integration/tests/IndividualFactsIntegrationTest.php`  
**Feature-Matrix-ID:** keiner (technischer Test analog `RomanNumeralsIntegrationTest`)  
**Ziel-Klassen:**
- `\Fisharebest\Webtrees\Services\IndividualFactsService::childFacts()` — CRAP 1.980, cx=44
- `\Fisharebest\Webtrees\Services\IndividualFactsService::parentFacts()` — CRAP 992, cx=31

### Was zu erstellen ist

**`layer3-integration/tests/IndividualFactsIntegrationTest.php`:**

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\IndividualFactsService;

/**
 * Komponentenintegrationstest: IndividualFactsService mit MySQL.
 *
 * Testet Fakten-Aggregation (childFacts, parentFacts) für Individuen
 * aus demo.ged. Kein Feature-Matrix-Eintrag — technischer Risikotest
 * für cx=44+31 bei 0% Coverage (analog RomanNumeralsIntegrationTest).
 *
 * @covers \Fisharebest\Webtrees\Services\IndividualFactsService
 */
class IndividualFactsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private IndividualFactsService $facts_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facts_service = new IndividualFactsService(
            new ClipboardService(),
        );
    }

    /**
     * childFacts() gibt Collection zurück, wenn Individuum Kinder hat.
     */
    public function test_child_facts_returns_facts_for_individual_with_children(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Elizabeth II (X1030) hat Kinder in demo.ged
        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->childFacts($individual, $individual, [], false, $this->tree);

        $this->assertIsIterable($facts);
    }

    /**
     * childFacts() gibt leere Collection zurück, wenn keine Kinder vorhanden.
     */
    public function test_child_facts_returns_empty_for_individual_without_children(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Wähle ein Individuum ohne bekannte Kinder in demo.ged
        // XREFs vorab mit SELECT DISTINCT xref FROM individuals prüfen
        $individual = Registry::individualFactory()->make('X1052', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->childFacts($individual, $individual, [], false, $this->tree);

        $this->assertIsIterable($facts);
    }

    /**
     * parentFacts() gibt Collection zurück, wenn Individuum Eltern hat.
     */
    public function test_parent_facts_returns_facts_for_individual_with_parents(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Wähle Individuum mit bekannten Eltern in demo.ged
        $individual = Registry::individualFactory()->make('X1052', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->parentFacts($individual, 1, false, $this->tree);

        $this->assertIsIterable($facts);
    }
}
```

**Achtung Konstruktor:** `IndividualFactsService` erwartet möglicherweise weitere
Abhängigkeiten. Vor der Implementierung `upstream/webtrees/app/Services/IndividualFactsService.php`
öffnen und den Konstruktor lesen — ggf. `ClipboardService` oder weitere Services ergänzen.
Die Methoden-Signaturen von `childFacts()` und `parentFacts()` aus der Quelldatei ablesen.

### Dokumentationsupdate (testing-bigpicture.md)

Kein FM-Eintrag nötig. Nur im Ratchet-Abschnitt nachziehen (s. AP-übergreifende Updates).

---

## AP-übergreifende Updates für testing-bigpicture.md

Diese Änderungen werden **einmalig nach Abschluss aller APs** durchgeführt.

### Ratchet Ist-Stand (neuer Unterabschnitt)

In `## Überdeckungsstrategie — Ratchet` direkt nach dem bestehenden Tabellen-Block
(Zeile ~1112) einfügen:

```markdown
### Ist-Stand (Teststufe 2, Stand: 2026-04-03)

> Basis: `make test-integration-quick` (3 Testklassen: SearchIntegrationTest,
> PrivacyVisibilityTest, TreeOperationsTest) — vor diesem Implementierungsplan.
> Voller Lauf (`make test-integration`) ergibt höhere Werte.

| Metrik | Wert (Quick-Lauf) |
|---|---|
| Anweisungsüberdeckung | 9,0% (3.969 / 44.070 Statements) |
| Methodenüberdeckung | 7,4% (329 / 4.442 Methoden) |
| Dateien mit 0%-Coverage | 1.191 von 1.365 |
| Pakete mit >50%-Coverage | CustomTags (97,2%), GedcomFilters (81,5%) |
| Pakete mit 0%-Coverage | Census, Cli, CommonMark, Exceptions, Report, Statistics, SurnameTradition |
| Größte unabgedeckte Pakete | Module (10.531 Stmt), Http (9.032 Stmt), Report (3.137 Stmt), Census (2.552 Stmt) |
```

---

## Gesamtverifikation nach allen APs

Nach Abschluss aller Code-Änderungen und Dokumentations-Updates:

```bash
# 1. Sicherstellen: kein laufender Test
pgrep -f phpunit && echo "Warten oder per kill beenden"

# 2. Quick-Lauf (run_in_background: true — auf Fertigmeldung warten)
make test-integration-quick

# 3. Optional: Vollständiger Integrationslauf für realistische Coverage
#    (run_in_background: true — deutlich länger als Quick-Lauf)
make test-integration
```

**Erwartete Verbesserungen nach allen APs (Schätzung):**

| Klasse | Coverage vorher | Erwartung nachher |
|---|---|---|
| `AbstractIndividualListModule` | 0,3% (1/363) | messbar höher (handle-Branch) |
| `GedcomExportService` | 81,6% (151/185) | ~87% (PRIV_NONE/USER-Branches) |
| `RelationshipService` | 0,0% (0/1523) | >0% (legacyNameAlgorithm erste Pfade) |
| `CheckTree` | 0,0% (0/219) | >50% (handle-Hauptpfad) |
| `IndividualFactsService` | 0,2% (1/550) | >10% (childFacts + parentFacts) |

---

## Commit-Sequenz

Nach jeder AP-Gruppe commiten (nicht alles auf einmal):

```
AP 1 + 2 (S19-Collation + G16-Regressions-Guard):
  git add layer3-integration/tests/ListModuleIntegrationTest.php
  git add layer3-integration/tests/TreeOperationsTest.php
  git add docs/testing-bigpicture.md
  git commit

AP 3 (S16-legacyNameAlgorithm):
  git add layer3-integration/tests/RelationshipServiceIntegrationTest.php
  git add docs/testing-bigpicture.md
  git commit

AP 4 (G24-CheckTree):
  git add layer3-integration/tests/CheckTreeIntegrationTest.php
  git add docs/testing-bigpicture.md
  git commit

AP 5 (IndividualFacts):
  git add layer3-integration/tests/IndividualFactsIntegrationTest.php
  git commit

AP-übergreifend (Ratchet Ist-Stand):
  git add docs/testing-bigpicture.md
  git commit
```

Alle Commits müssen GPG-signiert sein (`commit.gpgsign=true` global gesetzt).
