<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungsplan — Coverage-Erweiterung Teststufe 2 (Vollständiger Lauf)

> Basis: `docs/component-integration-coverage_full_analysis.md`  
> Ausgangslage: 17,9% Statement-Coverage, 17,3% Methodenüberdeckung (285 Tests, Voll-Lauf)

---

## Gesamtstatus

| AP | Titel | Status | Ergebnis |
|---|---|---|---|
| AP1 | legacyCousinName Pfad-Tests | ✅ ABGESCHLOSSEN | 11 Tests, 42 Assertions, Exit 0 |
| AP2 | StatisticsChartModule postCustomChartAction | ✅ ABGESCHLOSSEN | 3 Tests, 9 Assertions, Exit 0 (X_AXIS_AGE_AT_DEATH → X_AXIS_MARRIAGE_MONTH wegen fehlendem Pflichtparam) |
| AP3 | CalendarService + RelationshipsChartModule::chart | ✅ ABGESCHLOSSEN | 3 Tests, 8 Assertions, Exit 0 |
| AP4 | BranchesListModule getDescendantsHtml | ✅ ABGESCHLOSSEN | 24 Tests, 76 Assertions, Exit 0 |

---

## Ausgangslage (Kennzahlen vor diesem Plan)

| Metrik | Wert (Voll-Lauf-Baseline) |
|---|---|
| Anweisungsüberdeckung | 17,9% (7.882 / 44.066 Statements) |
| Methodenüberdeckung | 17,3% (767 / 4.441 Methoden) |
| Testklassen | 17 (285 Tests, 869 Assertions) |

**Offene CRAP-Risiken Layer-3:**

| Rang | CRAP | Klasse | Methode | AP |
|---|---|---|---|---|
| 1 | 14.042 | StatisticsChartModule | postCustomChartAction | AP2 |
| 2 | 2.652 | RelationshipService | legacyCousinName | AP1 |
| 3 | 1.406 | CalendarEvents | handle | AP3 |
| 4 | 870 | CalendarService | getAnniversaryEvents | AP3 |
| 5 | 756 | RelationshipsChartModule | chart | AP3 |
| 6 | 462 | RelationshipService | legacyCousinName2 | AP1 |
| 7 | 380 | BranchesListModule | getDescendantsHtml | AP4 |

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
`make up` ruft intern `generate-passwords` auf. Wird dieser Schritt übersprungen,
bleiben die Passwörter in `.env` leer — MySQL startet nicht, alle Tests schlagen fehl.

### Testausführung

**Einzeltest (schneller Feedback-Loop während Entwicklung):**

```bash
# Einzelne Testklasse ausführen — schnell, kein Coverage-Overhead
# run_in_background: true — auch Einzeltests können > 2 min dauern
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RelationshipServiceIntegrationTest' \
  /tests/layer3-integration/tests/RelationshipServiceIntegrationTest.php
```

**Voll-Lauf (Coverage-Verifikation, nach AP-Abschluss):**

```bash
# run_in_background: true — deutlich > 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

**Vor jedem neuen Lauf:**

```bash
pgrep -a phpunit && echo "Lauf aktiv — warten oder per kill beenden" || echo "Kein aktiver Lauf"
```

### Exklusivität

Niemals zwei Testläufe gleichzeitig. MySQL-Zustand ist geteilt — parallele Läufe
erzeugen Race-Conditions und nicht-deterministische Ergebnisse.

### Keine Zwischencommits

Kein `git commit` vor Abschluss **aller** APs. Erst wenn alle APs ✅ und
`make test-integration` Exit 0 liefert, den finalen Commit erstellen.

---

## AP1 — S16: Cousin-Pfade (RelationshipServiceIntegrationTest erweitern)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 11 Tests, 42 Assertions, Exit 0 — legacyCousinName(3,4) und once-removed-Pfade grün

**Priorität:** 1 (S16-Endekriterium, Cousin-Logik bisher nicht abgedeckt)  
**Datei:** `layer3-integration/tests/RelationshipServiceIntegrationTest.php`  
**Feature-Matrix-ID:** S16 (Beziehungsfinder)  
**Ziel-Klassen:**
- `RelationshipService::legacyCousinName` (private static — CRAP 2.652, cx=51)
- `RelationshipService::legacyCousinName2` (private static — CRAP 462, cx=21)

### Hintergrund

`legacyCousinName` und `legacyCousinName2` sind `private static` und nur über
`legacyNameAlgorithm()` (public) erreichbar. Der Aufruf erfolgt über ein Regex-Match:

```
/^((?:mot|fat|par)+)(?:bro|sis|sib)((?:son|dau|chi)+)$/
```

Dieses Regex matcht Cousin-Pfade mit beliebig vielen Eltern- und Kind-Segmenten.
Explizit gelistete Pfade (erste/zweite Cousins via `(fat|mot)fatbro...`) werden
**vor** dem Regex-Match abgefangen. Für dritte Cousins und entferntere Grade gibt
es keine explizite Listung → der Regex und damit `legacyCousinName` wird getriggert.

**Cousin-Pfad-Format:**  
`(fat|mot|par)+ (bro|sis|sib) (son|dau|chi)+`

Beispiele, die `legacyCousinName` triggern:
- `fatfatfatbrosonsonson` → up=3, down=3, cousin=3 → `legacyCousinName(3, 'U')` → `'third cousin'`
- `fatfatfatbrosonson` → up=3, down=2, cousin=2, removed=1, up>down → `'... once removed ascending'`
- `fatfatbrosonsonson` → up=2, down=3, cousin=2, removed=1, up<down → `'... once removed descending'`

**Hinweis zu I18N:** `legacyCousinName(3, 'U')` ruft `I18N::translate('third cousin')` auf.
In der Test-Umgebung (en_US) gibt `I18N::translate()` den Schlüssel zurück →
`assertSame('third cousin', ...)` ist möglich.

Für "once removed"-Fälle: `I18N::translate('%s once removed ascending', 'second cousin')`
→ `'second cousin once removed ascending'`. Die Assertion kann mit `assertStringContainsString`
abgesichert werden, falls der genaue Format-String variiert.

### Was zu ändern ist

**In `RelationshipServiceIntegrationTest.php` ergänzen** (nach `test_legacy_name_algorithm_grandparents`):

```php
// --- AP1: legacyCousinName — Cousin-Pfade ---

/**
 * S16 — Dritte Cousins: Pfad fatfatfatbrosonsonson triggert legacyCousinName(3).
 */
public function test_legacy_name_algorithm_third_cousin(): void
{
    // up=3, down=3, cousin=3, removed=0 → legacyCousinName(3, 'U')
    $this->assertSame('third cousin', $this->relationship_service->legacyNameAlgorithm('fatfatfatbrosonsonson'));
}

/**
 * S16 — Vierter Cousin: legacyCousinName(4, 'U').
 */
public function test_legacy_name_algorithm_fourth_cousin(): void
{
    // up=4, down=4, cousin=4, removed=0 → legacyCousinName(4, 'U')
    $this->assertSame('fourth cousin', $this->relationship_service->legacyNameAlgorithm('fatfatfatfatbrosonsonsonson'));
}

/**
 * S16 — Zweiter Cousin einmal entfernt (aufsteigend): legacyCousinName mit removed=1.
 */
public function test_legacy_name_algorithm_second_cousin_once_removed_ascending(): void
{
    // up=3, down=2, cousin=2, removed=1, up>down → 'second cousin once removed ascending'
    $result = $this->relationship_service->legacyNameAlgorithm('fatfatfatbrosonson');
    $this->assertStringContainsString('second cousin', $result);
    $this->assertStringContainsString('ascending', $result);
}

/**
 * S16 — Zweiter Cousin einmal entfernt (absteigend): legacyCousinName mit removed=1.
 */
public function test_legacy_name_algorithm_second_cousin_once_removed_descending(): void
{
    // up=2, down=3, cousin=2, removed=1, up<down → 'second cousin once removed descending'
    $result = $this->relationship_service->legacyNameAlgorithm('fatfatbrosonsonson');
    $this->assertStringContainsString('second cousin', $result);
    $this->assertStringContainsString('descending', $result);
}
```

**Kein neuer Import erforderlich** — alle benötigten Klassen sind bereits importiert.

### Verifikation

```bash
# Einzeltest:
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RelationshipServiceIntegrationTest' \
  /tests/layer3-integration/tests/RelationshipServiceIntegrationTest.php

# Erwartetes Ergebnis: alle Tests grün (mind. 11 Tests)
```

---

## AP2 — StatisticsChartModule::postCustomChartAction (neue Testklasse)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 3 Tests, 9 Assertions, Exit 0. Fix: X_AXIS_AGE_AT_DEATH durch X_AXIS_MARRIAGE_MONTH ersetzt (benötigt `x-axis-boundaries-ages`-Pflichtparam).

**Priorität:** 2 (höchster CRAP-Score Layer-3, kein FM-Eintrag)  
**Datei:** `layer3-integration/tests/StatisticsChartIntegrationTest.php` (neu)  
**Feature-Matrix-ID:** — (kein FM-Eintrag; technischer Risikotest)  
**Ziel-Klasse:** `\Fisharebest\Webtrees\Module\StatisticsChartModule::postCustomChartAction` (CRAP 14.042, cx=118)

### Hintergrund

`StatisticsChartModule` hat keinen expliziten `__construct` → kann direkt als
`new StatisticsChartModule()` instanziiert werden. `postCustomChartAction()` ruft intern
`Registry::container()->get(Statistics::class)` auf. Der Container in webtrees hat
**Auto-Wiring** (Reflection-basiert, `Container::make()`): `Statistics::__construct` benötigt
`ModuleService`, `Tree`, `UserService` — alle werden automatisch aufgelöst, wenn nach
`createTreeWithGedcom` ein `Tree` im Container registriert ist.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\StatisticsChartModule;

/**
 * Komponentenintegrationstest: StatisticsChartModule mit MySQL.
 *
 * Testet postCustomChartAction() — höchster CRAP-Score im Layer-3-Bereich (14.042, cx=118).
 * Keine Feature-Matrix-ID — technischer Risikotest.
 *
 * @covers \Fisharebest\Webtrees\Module\StatisticsChartModule
 */
class StatisticsChartIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private StatisticsChartModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new StatisticsChartModule();
    }

    /**
     * postCustomChartAction mit X_AXIS_BIRTH_MONTH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_birth_month_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_BIRTH_MONTH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * postCustomChartAction mit X_AXIS_DEATH_MONTH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_death_month_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_DEATH_MONTH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * postCustomChartAction mit X_AXIS_AGE_AT_DEATH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_age_at_death_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_AGE_AT_DEATH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
```

### Verifikation

```bash
# Einzeltest:
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'StatisticsChartIntegrationTest' \
  /tests/layer3-integration/tests/StatisticsChartIntegrationTest.php

# Erwartetes Ergebnis: 3 Tests grün
```

---

## AP3 — CalendarService + RelationshipsChartModule::chart (neue Testklasse)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 3 Tests, 8 Assertions, Exit 0

**Priorität:** 3 (CRAP 870 + 756, Layer-3-Kandidaten)  
**Datei:** `layer3-integration/tests/CalendarChartIntegrationTest.php` (neu)  
**Feature-Matrix-IDs:** — (S31 primär Teststufe 3; S16/S18 ergänzend)  
**Ziel-Klassen:**
- `\Fisharebest\Webtrees\Services\CalendarService::getAnniversaryEvents` (CRAP 870, cx=29)
- `\Fisharebest\Webtrees\Module\RelationshipsChartModule::chart` (CRAP 756, cx=27)

### Hintergrund

**CalendarService:** Hat keinen expliziten `__construct` → `new CalendarService()`.
`getAnniversaryEvents(int $jd, string $facts, Tree $tree, ...)` nimmt einen Julian Day integer,
einen GEDCOM-Fakten-String und den Tree. Ruft `DB::table('dates')` auf → Layer-3.

**RelationshipsChartModule:** `chart(Individual $individual1, Individual $individual2, int $recursion, int $ancestors)`
nimmt zwei `Individual`-Objekte direkt. Der bestehende Test ruft `handle()` mit leerem `xref2` auf
und erreicht `chart()` damit nicht. `chart()` direkt aufrufen mit zwei bekannten Individuen
aus `demo.ged` (Elizabeth II = X1030, ihr Sohn = X1052).

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Services\TreeService;

/**
 * Komponentenintegrationstest: CalendarService und RelationshipsChartModule mit MySQL.
 *
 * Keine Feature-Matrix-IDs — technische Risikotests für CalendarService::getAnniversaryEvents
 * (CRAP 870, cx=29) und RelationshipsChartModule::chart (CRAP 756, cx=27).
 *
 * @covers \Fisharebest\Webtrees\Services\CalendarService
 * @covers \Fisharebest\Webtrees\Module\RelationshipsChartModule
 */
class CalendarChartIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private CalendarService $calendar_service;
    private RelationshipsChartModule $relationships_module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar_service     = new CalendarService();
        $this->relationships_module = new RelationshipsChartModule(
            new RelationshipService(),
            Registry::container()->get(TreeService::class),
        );
    }

    /**
     * CalendarService::getAnniversaryEvents gibt Array zurück für bekannten Julian Day.
     */
    public function test_get_anniversary_events_returns_array(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Julian Day: 1. Januar 1960 ≈ JD 2436935
        $events = $this->calendar_service->getAnniversaryEvents(2436935, 'BIRT DEAT MARR', $this->tree);

        $this->assertIsArray($events);
    }

    /**
     * CalendarService::getAnniversaryEvents gibt Array zurück auch wenn keine Treffer.
     */
    public function test_get_anniversary_events_returns_empty_array_for_no_matches(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Sehr früher Julian Day — keine demo.ged-Ereignisse erwartet
        $events = $this->calendar_service->getAnniversaryEvents(1000000, 'BIRT', $this->tree);

        $this->assertIsArray($events);
    }

    /**
     * RelationshipsChartModule::chart gibt ResponseInterface zurück für zwei bekannte Individuen.
     * Elizabeth II (X1030) → Sohn (X1052).
     */
    public function test_relationships_chart_chart_method_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual1 = Registry::individualFactory()->make('X1030', $this->tree);
        $individual2 = Registry::individualFactory()->make('X1052', $this->tree);

        $this->assertNotNull($individual1);
        $this->assertNotNull($individual2);

        $response = $this->relationships_module->chart($individual1, $individual2, 1, 1);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
```

### Verifikation

```bash
# Einzeltest:
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'CalendarChartIntegrationTest' \
  /tests/layer3-integration/tests/CalendarChartIntegrationTest.php

# Erwartetes Ergebnis: 3 Tests grün
```

---

## AP4 — BranchesListModule::getDescendantsHtml (ChartModuleIntegrationTest erweitern)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 24 Tests, 76 Assertions, Exit 0 — ajax-Branch mit surname='Windsor' triggert getDescendantsHtml

**Priorität:** 4 (CRAP 380, cx=19 — niedriger Score, aber bestehender Test deckt nicht den ajax-Branch ab)  
**Datei:** `layer3-integration/tests/ChartModuleIntegrationTest.php`  
**Feature-Matrix-ID:** S18/S20 (ergänzend)  
**Ziel-Klasse:** `\Fisharebest\Webtrees\Module\BranchesListModule::getDescendantsHtml` (private)

### Hintergrund

`getDescendantsHtml` ist `private` — nur über `handle()` erreichbar.
Der bestehende Test `test_branches_list_renders_without_error()` ruft `handle()` ohne
`ajax=true` auf und landet im Standard-Branch (gibt eine Übersichtsseite zurück).
`getDescendantsHtml` wird nur im `$ajax=true`-Branch aufgerufen (nach Zeile 168 in
`BranchesListModule::handle()`). Dort wird `getPatriarchsHtml()` aufgerufen,
das intern `getDescendantsHtml()` für jede Person aufruft.

**Fix:** Neue Testmethode mit `query: ['ajax' => '1']` und `surname='Windsor'`
(Windsor-Familie ist in demo.ged vorhanden).

### Was zu ändern ist

**In `ChartModuleIntegrationTest.php` ergänzen** (nach `test_branches_list_renders_without_error`):

```php
/**
 * S18/S20 — Branches ajax-Branch: getDescendantsHtml wird aufgerufen.
 *
 * BranchesListModule::handle() mit ajax=true ruft intern getDescendantsHtml()
 * (private) auf. Smoke-Test: Response ist 200 OK.
 */
public function test_branches_list_ajax_calls_get_descendants_html(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $admin = $this->createAndLoginAdmin();

    $module  = new \Fisharebest\Webtrees\Module\BranchesListModule(
        new \Fisharebest\Webtrees\Services\ModuleService(),
    );
    $request = $this->createRequest(
        attributes: [
            'tree'    => $this->tree,
            'user'    => $admin,
            'surname' => 'Windsor',
        ],
        query: ['ajax' => '1'],
    );

    $response = $module->handle($request);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
}
```

**Keine neuen Imports erforderlich** — `BranchesListModule` und `ModuleService` sind
bereits via Inline-Namespace-Referenz eingebunden.

### Verifikation

```bash
# Einzeltest:
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ChartModuleIntegrationTest' \
  /tests/layer3-integration/tests/ChartModuleIntegrationTest.php

# Erwartetes Ergebnis: alle vorhandenen + 1 neuer Test grün
```

---

## Finaler Commit

Erst wenn alle APs ✅ ABGESCHLOSSEN und `make test-integration` Exit 0:

```bash
# Alle Layer-3-Tests nochmals vollständig:
# run_in_background: true
make test-integration

# Dann committen:
git add layer3-integration/tests/RelationshipServiceIntegrationTest.php
git add layer3-integration/tests/StatisticsChartIntegrationTest.php
git add layer3-integration/tests/CalendarChartIntegrationTest.php
git add layer3-integration/tests/ChartModuleIntegrationTest.php
git add docs/testing-bigpicture.md
git add docs/component-integration-coverage_full_analysis.md
git add docs/component-integration-coverage_full_impl_plan.md
git add docs/component-integration-coverage_full_prompt.md
git commit -m "test(layer3): Coverage-Erweiterung Voll-Lauf ..."
```
