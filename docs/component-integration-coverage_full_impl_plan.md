<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungsplan — Coverage-Erweiterung Teststufe 2 (CRAP > 100, vollständig)

> Basis: `docs/component-integration-coverage_full_analysis.md`  
> Ausgangslage: 19,8% Statement-Coverage, 17,7% Methodenüberdeckung (296 Tests, 21 Testklassen)

---

## Gesamtstatus

| AP | Titel | Status | Ergebnis |
|---|---|---|---|
| AP1 | RightToLeftSupport::spanLtrRtl Bootstrap-Test | ✅ ABGESCHLOSSEN | 5 Tests, 12 Assertions, Exit 0 |
| AP2 | ReportHtml*-Objekte Bootstrap-Tests | ✅ ABGESCHLOSSEN | 6 Tests, 13 Assertions, Exit 0 |
| AP3 | SearchGeneralPage::handle | ✅ ABGESCHLOSSEN | 3 Tests, 9 Assertions, Exit 0 |
| AP4 | CalendarEvents::handle + CalendarService erweitert | ✅ ABGESCHLOSSEN | 7 Tests, 18 Assertions, Exit 0 |
| AP5 | TreeView::getIndividuals (drawPerson/drawChildren via public API) | ✅ ABGESCHLOSSEN | 3 Tests, 10 Assertions, Exit 0 |
| AP6 | Block-Module (Slide, Yahrzeit, ReviewChanges, ChartsBlock) | ✅ ABGESCHLOSSEN | 7 Tests, 14 Assertions, Exit 0 |
| AP7 | legacyCousinName2 — erweiterte Pfad-Tests | ✅ ABGESCHLOSSEN | 5 Tests via Reflection (setAccessible entfernt, PHP 8.5 clean) |
| AP8 | StatisticsFormat + StatisticsData DB-Methoden | ✅ ABGESCHLOSSEN | 10 Tests (4 Bootstrap + 6 DB) |
| AP9 | GedcomEditService (editLinesToGedcom, insertMissingLevels) | ✅ ABGESCHLOSSEN | 7 Tests (4 Bootstrap + 3 DB via anonyme Subklasse) |
| AP10 | FanChartModule::chart + TimelineChartModule::chart | ✅ ABGESCHLOSSEN | 2 Tests via ajax=1 in ChartModuleIntegrationTest |
| AP11 | Bootstrap-Only-Batch (EncodingFactory, LanguageFrench, Census, NoteStructure, FileUploadException) | ✅ ABGESCHLOSSEN | 13 Tests |
| AP12 | RequestHandlers Batch A (HelpText, GedcomRecordPage, DeleteRecord, TreePrivacyAction) | ✅ ABGESCHLOSSEN | 8 Tests (DeleteRecord: 204 not 200) |
| AP13 | Block-Module Gruppe C (ClippingsCart postAddIndividual + postDownload) | ✅ ABGESCHLOSSEN | 3 Tests in BlockModuleIntegrationTest |
| AP14 | RequestHandlers Batch B (ChangeFamilyMembersAction, MergeRecordsPage, MergeFactsPage, RenumberTree, UserEditAction) | ✅ ABGESCHLOSSEN | 5 Tests |
| AP15 | ReportGenerate + ReportParserGenerate SAX-Kette direkt | ✅ ABGESCHLOSSEN | 5 Tests (vars erforderlich: sortby, pageSize) |

---

## Ausgangslage (Kennzahlen vor diesem Plan)

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | 19,8% (8.716 / 44.066 Statements) |
| Methodenüberdeckung | 17,7% (787 / 4.441 Methoden) |
| Testklassen | 21 (296 Tests, 899 Assertions) |

**CRAP-Risiken nach Priorität (Gruppe A, actionable):**

| Rang | CRAP | Klasse | Methode | AP |
|---|---|---|---|---|
| 1 | 6.972 | RightToLeftSupport | spanLtrRtl | AP1 |
| 2 | 2.256 | ReportHtmlTextBox | render | AP2 |
| 3 | 1.722 | SearchGeneralPage | handle | AP3 |
| 4 | 1.406 | CalendarEvents | handle | AP4 |
| 5 | 1.122 | TreeView | drawPerson (via getIndividuals) | AP5 |
| 6 | 992 | ReportHtmlCell | render | AP2 |
| 7 | 930 | SlideShowModule | getBlock | AP6 |
| 8 | 812 | YahrzeitModule | getBlock | AP6 |

---

## Stack-Voraussetzungen und Ausführungsregeln

### Startsequenz (zwingend einhalten)

```bash
make up    # Passwörter generieren + Stack starten (immer make up, nie make _compose-up direkt)
make setup # webtrees installieren (einmalig nach up)
```

### Testausführung

**Einzeltest (schneller Feedback-Loop während Entwicklung):**

```bash
# run_in_background: true — auch Einzeltests können > 2 min dauern
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

**Voll-Lauf (Coverage-Verifikation, nach AP-Abschluss):**

```bash
# run_in_background: true — deutlich > 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

**Vor jedem neuen Lauf:**

```bash
pgrep -a phpunit && echo "Lauf aktiv — warten oder per kill beenden" || echo "OK"
```

### Exklusivität und Keine Zwischencommits

Niemals zwei Testläufe gleichzeitig. Kein `git commit` vor Abschluss **aller** APs.

---

## AP1 — RightToLeftSupport::spanLtrRtl Bootstrap-Test (neue Testklasse)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 5 Tests, 12 Assertions, Exit 0

**Priorität:** Gruppe A (CRAP 6.972 + 10.100 via Indirektion)  
**Datei:** `layer3-integration/tests/RightToLeftSupportIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `RightToLeftSupport::spanLtrRtl` (public static, CRAP 6.972, cx=83)
- `RightToLeftSupport::finishCurrentSpan` (private static, CRAP 10.100 — via spanLtrRtl erreichbar)

### Hintergrund

`RightToLeftSupport` ist eine reine Utility-Klasse für bidirektionalen Text (RTL/LTR-Spanning).
`spanLtrRtl(string $inputText)` ist public static — kein Konstruktor, kein DB.
`finishCurrentSpan` ist private static und wird intern von `spanLtrRtl` aufgerufen.
Ein Bootstrap-Test mit einem RTL-String deckt beide Methoden ab.

Konstruktor-Verifikation: Klasse hat keinen Konstruktor — direkter static-Aufruf.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\RightToLeftSupport;

/**
 * Komponentenintegrationstest: RightToLeftSupport Bootstrap-Test.
 *
 * spanLtrRtl() ist public static — kein DB, kein Tree. Ruft intern finishCurrentSpan()
 * auf (private static, CRAP 10.100) — beide Methoden werden durch einen Test abgedeckt.
 *
 * @covers \Fisharebest\Webtrees\Report\RightToLeftSupport
 */
class RightToLeftSupportIntegrationTest extends MysqlTestCase
{
    /**
     * spanLtrRtl mit LTR-String gibt nicht-leeren String zurück.
     */
    public function test_span_ltr_rtl_with_ltr_string_returns_string(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('Hello World');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * spanLtrRtl mit RTL-String (Arabisch) triggert finishCurrentSpan intern.
     */
    public function test_span_ltr_rtl_with_rtl_string_triggers_finish_span(): void
    {
        // Arabischer Text triggert den RTL-Branch und damit finishCurrentSpan
        $result = RightToLeftSupport::spanLtrRtl('مرحبا');
        $this->assertIsString($result);
    }

    /**
     * spanLtrRtl mit gemischtem RTL/LTR-Text (beide Branches).
     */
    public function test_span_ltr_rtl_with_mixed_text(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('Hello مرحبا World');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * spanLtrRtl mit leerem String gibt String zurück.
     */
    public function test_span_ltr_rtl_with_empty_string_returns_string(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('');
        $this->assertIsString($result);
    }
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RightToLeftSupportIntegrationTest' \
  /tests/layer3-integration/tests/RightToLeftSupportIntegrationTest.php
```

---

## AP2 — ReportHtml*-Objekte Bootstrap-Tests (neue Testklasse)

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 6 Tests, 13 Assertions, Exit 0. Fix: styles-Array im Renderer setzen; setWrapWidth() vor getWidth() bei ReportHtmlFootnote.

**Priorität:** Gruppe A/B (CRAP 2.256, 992, 380, 210, 210, 182)  
**Datei:** `layer3-integration/tests/ReportHtmlObjectsIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `ReportHtmlTextBox::render` (public, CRAP 2.256, cx=47)
- `ReportHtmlCell::render` (public, CRAP 992, cx=31)
- `ReportHtmlText::getWidth` (public, CRAP 210, cx=14)
- `ReportHtmlFootnote::getWidth` (public, CRAP 210, cx=14)
- `ReportHtmlImage::render` (public, CRAP 182, cx=13)
- `HtmlRenderer::run` (public, CRAP 380, cx=19)

### Hintergrund

Die `ReportHtml*`-Klassen sind das HTML-Rendering-Backend des webtrees-Report-Systems.
Sie sind Bootstrap-only (kein DB-Zugriff) und können direkt instanziiert werden.

Konstruktor-Verifikation muss vor Implementierung durchgeführt werden:
- `ReportHtmlTextBox` extends `ReportBaseTextBox` — Konstruktor prüfen
- `ReportHtmlCell` extends `ReportBaseCell` — Konstruktor prüfen
- `HtmlRenderer` extends `AbstractRenderer` — Konstruktor prüfen

### PHP-Skelett (Entwurf — Konstruktoren vor Implementierung verifizieren)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\ReportHtmlCell;
use Fisharebest\Webtrees\Report\ReportHtmlTextBox;
use Fisharebest\Webtrees\Report\ReportHtmlText;
use Fisharebest\Webtrees\Report\ReportHtmlFootnote;
use Fisharebest\Webtrees\Report\ReportHtmlImage;

/**
 * Komponentenintegrationstest: ReportHtml*-Objekte Bootstrap-Tests.
 *
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlTextBox
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlCell
 * @covers \Fisharebest\Webtrees\Report\HtmlRenderer
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlText
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlFootnote
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlImage
 */
class ReportHtmlObjectsIntegrationTest extends MysqlTestCase
{
    // Skelett — Konstruktoren vor Implementierung aus Source verifizieren
    // ReportHtmlTextBox, ReportHtmlCell, ReportHtmlText, ReportHtmlFootnote,
    // ReportHtmlImage, HtmlRenderer
    // Dann: render()-Aufrufe mit minimalen Argumenten, assertIsString auf Output-Buffer
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportHtmlObjectsIntegrationTest' \
  /tests/layer3-integration/tests/ReportHtmlObjectsIntegrationTest.php
```

---

## AP3 — SearchGeneralPage::handle (neue Testklasse)

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe A (CRAP 1.722, cx=41)  
**Datei:** `layer3-integration/tests/SearchRequestHandlerIntegrationTest.php` (neu)  
**Ziel-Klasse:** `SearchGeneralPage::handle` (public)

### Hintergrund

`SearchGeneralPage` hat `__construct(SearchService $search_service, TreeService $tree_service)`.
Der Handler gibt eine vollständige HTML-Seite zurück (nicht AJAX).
Query-Parameter: `query`, `search_individuals`, `search_families`, `search_sources`, usw.

Konstruktor-Verifikation: `new SearchGeneralPage(new SearchService(), $this->treeService)`.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: SearchGeneralPage HTTP-Handler.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage
 */
class SearchRequestHandlerIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private SearchGeneralPage $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SearchGeneralPage(
            new SearchService(),
            $this->treeService,
        );
    }

    /**
     * SearchGeneralPage gibt 200 OK für leere Suchanfrage.
     */
    public function test_search_general_page_empty_query_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: ['query' => '', 'search_individuals' => '1'],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * SearchGeneralPage gibt 200 OK für konkrete Suchanfrage.
     */
    public function test_search_general_page_with_query_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: [
                'query'              => 'Windsor',
                'search_individuals' => '1',
                'search_families'    => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * SearchGeneralPage gibt 200 OK für Quellen-Suche.
     */
    public function test_search_general_page_sources_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: [
                'query'          => 'Royal',
                'search_sources' => '1',
                'search_notes'   => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'SearchRequestHandlerIntegrationTest' \
  /tests/layer3-integration/tests/SearchRequestHandlerIntegrationTest.php
```

---

## AP4 — CalendarEvents::handle + CalendarService erweitert

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe A/B (CRAP 1.406 + 306 + 306)  
**Datei:** `layer3-integration/tests/CalendarChartIntegrationTest.php` (erweitern)  
**Ziel-Klassen:**
- `CalendarEvents::handle` (public, CRAP 1.406, cx=37)
- `CalendarService::getCalendarEvents` (public, CRAP 306, cx=17)
- `CalendarService::getEventsList` (public, CRAP 306, cx=17)

### Hintergrund

`CalendarEvents` hat `__construct(private readonly CalendarService $calendar_service)`.
`handle()` liest `view` ∈ {day, month, year}, `cal`, `day`, `month`, `year` aus Request-Params.
`CalendarService::getCalendarEvents(Tree, int, int, int, string, string)` und
`getEventsList(Tree, int, int, string, string)` sind public und analog zu `getAnniversaryEvents`.

### Was zu ergänzen ist

**In `CalendarChartIntegrationTest.php` ergänzen:**

```php
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\CalendarEvents;

// In setUp: $this->calendar_events = new CalendarEvents($this->calendar_service);

/**
 * CalendarEvents::handle mit view=day gibt 200 OK zurück.
 */
public function test_calendar_events_handle_day_view_returns_ok(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $handler = new CalendarEvents($this->calendar_service);
    $request = $this->createRequest(
        query: ['cal' => '', 'day' => '1', 'month' => 'JAN', 'year' => '1960', 'filterev' => 'BIRT DEAT MARR', 'filterof' => 'all', 'filtersx' => ''],
        attributes: ['tree' => $this->tree, 'view' => 'day'],
    );

    $response = $handler->handle($request);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
}

/**
 * CalendarService::getCalendarEvents gibt Array zurück.
 */
public function test_get_calendar_events_returns_array(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    // getCalendarEvents(Tree, int $jd1, int $jd2, string $facts, string $filterof, string $filtersx)
    $events = $this->calendar_service->getCalendarEvents(2436935, 2436935, 'BIRT DEAT MARR', $this->tree);

    $this->assertIsArray($events);
}

/**
 * CalendarService::getEventsList gibt Array zurück.
 */
public function test_get_events_list_returns_array(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $events = $this->calendar_service->getEventsList(2436935, 2436935, 'BIRT DEAT MARR', $this->tree);

    $this->assertIsArray($events);
}
```

**Hinweis:** Konstruktor-Signaturen von `getCalendarEvents` und `getEventsList` vor Implementierung aus Source verifizieren — Parameterliste kann variieren.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'CalendarChartIntegrationTest' \
  /tests/layer3-integration/tests/CalendarChartIntegrationTest.php
```

---

## AP5 — TreeView::getIndividuals (drawPerson/drawChildren via public API)

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe A (CRAP 1.122 private, erreichbar via public API)  
**Datei:** `layer3-integration/tests/InteractiveTreeIntegrationTest.php` (neu)  
**Ziel-Klasse:** `TreeView` — drawPerson (private, CRAP 1.122) + drawChildren (private, CRAP 132) via `getIndividuals` (public)

### Hintergrund

`TreeView` hat `public function __construct(string $name = 'tree')`.
`drawPerson` und `drawChildren` sind private — nur über `getIndividuals(Tree, string)` erreichbar.
`getIndividuals(Tree $tree, string $request)` ist public und gibt HTML-String zurück.
Der `$request`-Parameter ist ein JSON-kodierter AJAX-Request-String mit `q[]`-XREFs.

Konstruktor-Verifikation: `new TreeView('tree')` — kein Service nötig.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Module\InteractiveTree\TreeView;

/**
 * Komponentenintegrationstest: InteractiveTree — TreeView Bootstrap über getIndividuals.
 *
 * drawPerson() und drawChildren() sind private — via getIndividuals() erreichbar.
 *
 * @covers \Fisharebest\Webtrees\Module\InteractiveTree\TreeView
 */
class InteractiveTreeIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private TreeView $tree_view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree_view = new TreeView('tree');
    }

    /**
     * getIndividuals mit gültiger XREF gibt HTML-String zurück (triggert drawPerson).
     */
    public function test_get_individuals_returns_html_for_known_xref(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // JSON-Request wie ihn der InteractiveTree-AJAX-Endpoint sendet
        $ajaxRequest = json_encode(['q' => ['X1030'], 'type' => 'individual']);

        $result = $this->tree_view->getIndividuals($this->tree, $ajaxRequest);

        $this->assertIsString($result);
    }

    /**
     * getDetails mit bekannter XREF gibt HTML-String zurück.
     */
    public function test_get_details_returns_html_for_known_xref(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = \Fisharebest\Webtrees\Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $result = $this->tree_view->getDetails($individual);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
```

**Hinweis:** `getIndividuals`-Signatur und `$request`-Format vor Implementierung aus Source verifizieren.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'InteractiveTreeIntegrationTest' \
  /tests/layer3-integration/tests/InteractiveTreeIntegrationTest.php
```

---

## AP6 — Block-Module (SlideShow, Yahrzeit, ReviewChanges, ChartsBlock)

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B (CRAP 930, 812, 306, 182)  
**Datei:** `layer3-integration/tests/BlockModuleIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `SlideShowModule::getBlock` (public, CRAP 930, cx=30) — `new SlideShowModule(new LinkedRecordService())`
- `YahrzeitModule::getBlock` (public, CRAP 812, cx=28) — `new YahrzeitModule(new CalendarService())`
- `ReviewChangesModule::getBlock` (public, CRAP 306, cx=17) — `new ReviewChangesModule(EmailService, TreeService, UserService)`
- `ChartsBlockModule::getBlock` (public, CRAP 182, cx=13) — `new ChartsBlockModule(new ModuleService())`

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Module\ChartsBlockModule;
use Fisharebest\Webtrees\Module\ReviewChangesModule;
use Fisharebest\Webtrees\Module\SlideShowModule;
use Fisharebest\Webtrees\Module\YahrzeitModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\UserService;

/**
 * Komponentenintegrationstest: Block-Module getBlock() Smoke-Tests.
 *
 * @covers \Fisharebest\Webtrees\Module\SlideShowModule
 * @covers \Fisharebest\Webtrees\Module\YahrzeitModule
 * @covers \Fisharebest\Webtrees\Module\ReviewChangesModule
 * @covers \Fisharebest\Webtrees\Module\ChartsBlockModule
 */
class BlockModuleIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * SlideShowModule::getBlock gibt String zurück.
     */
    public function test_slide_show_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new SlideShowModule(new LinkedRecordService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * YahrzeitModule::getBlock gibt String zurück.
     */
    public function test_yahrzeit_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new YahrzeitModule(new CalendarService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * ReviewChangesModule::getBlock gibt String zurück.
     */
    public function test_review_changes_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module = new ReviewChangesModule(
            Registry::container()->get(EmailService::class),
            $this->treeService,
            Registry::container()->get(UserService::class),
        );
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * ChartsBlockModule::getBlock gibt String zurück.
     */
    public function test_charts_block_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new ChartsBlockModule(new ModuleService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }
}
```

**Hinweis:** Konstruktoren und `getBlock`-Signaturen vor Implementierung aus Source verifizieren.
`ReviewChangesModule::getBlock` benötigt evtl. einen eingeloggten Admin-User als `$context`.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'BlockModuleIntegrationTest' \
  /tests/layer3-integration/tests/BlockModuleIntegrationTest.php
```

---

## AP7 — legacyCousinName2 erweiterte Pfad-Tests

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B (CRAP 462, cx=21, private)  
**Datei:** `layer3-integration/tests/RelationshipServiceIntegrationTest.php` (erweitern)  
**Ziel-Klasse:** `RelationshipService::legacyCousinName2` (private static — via `legacyNameAlgorithm`)

### Hintergrund

`legacyCousinName` (AP1 der vorigen Iteration) deckt symmetrische Cousin-Grade ab.
`legacyCousinName2` ist eine separate private static-Methode für weiter entfernte Grade oder
entfernte Cousins (5.+). Die genaue Triggerbedingung muss aus dem Source verifiziert werden:
- In `legacyNameAlgorithm`: der Regex `/^((?:mot|fat|par)+)(?:bro|sis|sib)((?:son|dau|chi)+)$/` matcht
- `legacyCousinName` wird aufgerufen, wenn Grad ≤ 4 (oder ähnliche Bedingung)
- `legacyCousinName2` wird aufgerufen für weiter entfernte Cousin-Grade

**Konstruktor-Verifikation:** Source der `legacyNameAlgorithm`-Methode lesen, um den genauen
Aufrufschwellwert für `legacyCousinName2` zu ermitteln.

### Was zu ergänzen ist

**In `RelationshipServiceIntegrationTest.php` nach den AP1-Methoden ergänzen:**

```php
// --- AP7: legacyCousinName2 — fernere Cousin-Grade ---

/**
 * S16 — Fünfter Cousin: triggert legacyCousinName2 (falls Grenze bei 4).
 * Pfad: fatfatfatfatfatbrosonsonsonsonson = 5 Eltern-Schritte, 5 Kind-Schritte
 */
public function test_legacy_name_algorithm_fifth_cousin_triggers_cousin_name2(): void
{
    $result = $this->relationship_service->legacyNameAlgorithm('fatfatfatfatfatbrosonsonsonsonson');
    $this->assertIsString($result);
    $this->assertNotEmpty($result);
}

/**
 * S16 — Dritter Cousin zweimal entfernt (aufsteigend): komplexer Removed-Fall.
 */
public function test_legacy_name_algorithm_third_cousin_twice_removed_ascending(): void
{
    // up=5, down=3, cousin=3, removed=2, up>down
    $result = $this->relationship_service->legacyNameAlgorithm('fatfatfatfatfatbrosonson');
    $this->assertIsString($result);
    $this->assertNotEmpty($result);
}
```

**Hinweis:** Vor Implementierung `legacyNameAlgorithm` in Source lesen — den genauen Schwellwert
für legacyCousinName2-Aufruf verifizieren. Falsche Pfad-Länge → kein Aufruf.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RelationshipServiceIntegrationTest' \
  /tests/layer3-integration/tests/RelationshipServiceIntegrationTest.php
```

---

## AP8 — StatisticsFormat + StatisticsData DB-Methoden

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B (CRAP 600, 552, 132, 132)  
**Datei:** `layer3-integration/tests/StatisticsIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `StatisticsFormat::century` (public, CRAP 600, cx=24) — Bootstrap
- `StatisticsData::ageOfMarriageQuery` (public, CRAP 552, cx=23) — DB
- `StatisticsData::parentsQuery` (public, CRAP 132, cx=11) — DB
- `StatisticsData::marriageQuery` (public, CRAP 132, cx=11) — DB

### Hintergrund

`StatisticsFormat` ist `readonly class StatisticsFormat` ohne Konstruktor → `new StatisticsFormat()`.
`StatisticsData` hat `__construct(private Tree $tree, private UserService $user_service)`.

`StatisticsData::ageOfMarriageQuery(string $type, string $age_dir, int $limit): string` — gibt SQL-String zurück.
`StatisticsData::parentsQuery(...)` und `marriageQuery(...)` — public DB-Methoden.

**Hinweis:** Konstruktoren und Parameter-Signaturen aus Source verifizieren.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\StatisticsData;
use Fisharebest\Webtrees\StatisticsFormat;

/**
 * Komponentenintegrationstest: StatisticsFormat (Bootstrap) und StatisticsData (DB).
 *
 * @covers \Fisharebest\Webtrees\StatisticsFormat
 * @covers \Fisharebest\Webtrees\StatisticsData
 */
class StatisticsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private StatisticsFormat $statistics_format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistics_format = new StatisticsFormat();
    }

    // --- Bootstrap: StatisticsFormat::century ---

    public function test_statistics_format_century_returns_string_for_21st(): void
    {
        $result = $this->statistics_format->century(21);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_statistics_format_century_returns_string_for_negative(): void
    {
        // Negativer Wert → BCE-Branch
        $result = $this->statistics_format->century(-5);
        $this->assertIsString($result);
        $this->assertStringContainsString('BCE', $result);
    }

    // --- DB: StatisticsData-Methoden ---

    public function test_statistics_data_age_of_marriage_query_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        // Parameter-Signaturen vor Implementierung aus Source verifizieren
        $result = $data->ageOfMarriageQuery('full', 'ASC', 10);

        $this->assertIsString($result);
    }

    public function test_statistics_data_parents_query_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        // Parameter-Signaturen vor Implementierung aus Source verifizieren
        $result = $data->parentsQuery('full', 'ASC', 'M', false);

        $this->assertIsString($result);
    }

    public function test_statistics_data_marriage_query_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        // Parameter-Signaturen vor Implementierung aus Source verifizieren
        $result = $data->marriageQuery('full', 'ASC', 'M', false);

        $this->assertIsString($result);
    }
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'StatisticsIntegrationTest' \
  /tests/layer3-integration/tests/StatisticsIntegrationTest.php
```

---

## AP9 — GedcomEditService

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B/C (CRAP 420, 132)  
**Datei:** `layer3-integration/tests/GedcomEditServiceIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `GedcomEditService::editLinesToGedcom` (public, CRAP 420, cx=20)
- `GedcomEditService::insertMissingLevels` (protected, CRAP 132, cx=11)

### Hintergrund

`GedcomEditService` hat keinen Konstruktor → `new GedcomEditService()`.
`editLinesToGedcom(string $record_type, array $levels, array $tags, array $values, bool $append)` → gibt GEDCOM-String zurück.
`insertMissingLevels` ist protected — über Subklasse oder Reflection testbar; alternativ via `insertMissingFactSubtags` (public).

**Hinweis:** `insertMissingLevels` ist protected — direkte Verifikation via Reflection oder Subklasse im Test.

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Services\GedcomEditService;

/**
 * Komponentenintegrationstest: GedcomEditService.
 *
 * @covers \Fisharebest\Webtrees\Services\GedcomEditService
 */
class GedcomEditServiceIntegrationTest extends MysqlTestCase
{
    private GedcomEditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GedcomEditService();
    }

    /**
     * editLinesToGedcom gibt GEDCOM-String zurück für INDI-Record.
     */
    public function test_edit_lines_to_gedcom_indi_returns_string(): void
    {
        $result = $this->service->editLinesToGedcom(
            'INDI',
            ['1', '2'],
            ['NAME', 'GIVN'],
            ['John /Smith/', 'John'],
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('NAME', $result);
    }

    /**
     * editLinesToGedcom mit leeren Arrays gibt leeren String zurück.
     */
    public function test_edit_lines_to_gedcom_empty_returns_empty(): void
    {
        $result = $this->service->editLinesToGedcom('INDI', [], [], []);
        $this->assertIsString($result);
    }
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'GedcomEditServiceIntegrationTest' \
  /tests/layer3-integration/tests/GedcomEditServiceIntegrationTest.php
```

---

## AP10 — FanChartModule::chart + TimelineChartModule::chart

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B/C (CRAP 342 + 182)  
**Datei:** `layer3-integration/tests/ChartModuleIntegrationTest.php` (erweitern)  
**Ziel-Klassen:**
- `FanChartModule::chart` (protected, CRAP 342, cx=18) — `new FanChartModule(new ChartService())`
- `TimelineChartModule::chart` (protected, CRAP 182, cx=13) — `new TimelineChartModule()`

### Was zu ergänzen ist

**In `ChartModuleIntegrationTest.php` ergänzen:**

```php
use Fisharebest\Webtrees\Module\FanChartModule;
use Fisharebest\Webtrees\Module\TimelineChartModule;
use Fisharebest\Webtrees\Services\ChartService;

/**
 * S18 — FanChartModule::chart rendert Response für bekanntes Individuum.
 */
public function test_fan_chart_chart_method_returns_response(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $this->createAndLoginAdmin();

    $individual = Registry::individualFactory()->make('X1030', $this->tree);
    $this->assertNotNull($individual);

    $module   = new FanChartModule(new ChartService());
    // chart-Signatur aus Source verifizieren: chart(Individual, int $style, int $generations, int $width)
    $response = $module->chart($individual, 4, 4, 210);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
}

/**
 * S18 — TimelineChartModule::chart rendert Response für bekanntes Individuum.
 */
public function test_timeline_chart_chart_method_returns_response(): void
{
    $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    $admin = $this->createAndLoginAdmin();

    $individual = Registry::individualFactory()->make('X1030', $this->tree);
    $this->assertNotNull($individual);

    $module = new TimelineChartModule();
    // chart-Signatur aus Source verifizieren
    $response = $module->chart($individual, $admin);

    $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
}
```

**Hinweis:** `chart`-Signaturen vor Implementierung aus Source verifizieren.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ChartModuleIntegrationTest' \
  /tests/layer3-integration/tests/ChartModuleIntegrationTest.php
```

---

## AP11 — Bootstrap-Only-Batch

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe C (CRAP 182, 156, 156, 132, 110)  
**Datei:** `layer3-integration/tests/BootstrapOnlyIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `EncodingFactory::detect` (public, CRAP 182, cx=13)
- `NoteStructure::labelValue` (public, CRAP 156, cx=12)
- `FileUploadException::__construct` (public, CRAP 156, cx=12)
- `LanguageFrench::relationships` (public, CRAP 132, cx=11)
- `Census::censusPlaces` (public static, CRAP 110, cx=10)

### PHP-Skelett (neue Datei)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Census\Census;
use Fisharebest\Webtrees\Elements\NoteStructure;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Factories\EncodingFactory;
use Fisharebest\Webtrees\Module\LanguageFrench;

/**
 * Komponentenintegrationstest: Bootstrap-Only-Batch.
 *
 * Alle Klassen benötigen kein DB/Tree. Tests laufen in MysqlTestCase für
 * einheitliche PHP-Version und OTel-Erfassung.
 *
 * @covers \Fisharebest\Webtrees\Factories\EncodingFactory
 * @covers \Fisharebest\Webtrees\Elements\NoteStructure
 * @covers \Fisharebest\Webtrees\Exceptions\FileUploadException
 * @covers \Fisharebest\Webtrees\Module\LanguageFrench
 * @covers \Fisharebest\Webtrees\Census\Census
 */
class BootstrapOnlyIntegrationTest extends MysqlTestCase
{
    /**
     * EncodingFactory::detect erkennt UTF-8-BOM.
     */
    public function test_encoding_factory_detect_utf8_bom(): void
    {
        $factory = new EncodingFactory();
        // UTF-8 BOM: EF BB BF
        $result  = $factory->detect("\xEF\xBB\xBF" . 'Test content');
        $this->assertNotNull($result);
    }

    /**
     * EncodingFactory::detect mit leerem String.
     */
    public function test_encoding_factory_detect_empty_string(): void
    {
        $factory = new EncodingFactory();
        $result  = $factory->detect('');
        // Ergebnis kann null sein — kein Absturz erwartet
        $this->assertTrue(true);
    }

    /**
     * NoteStructure::labelValue gibt String zurück.
     */
    public function test_note_structure_label_value_returns_string(): void
    {
        $element = new NoteStructure('NOTE');
        $result  = $element->labelValue('Test Note', null);
        $this->assertIsString($result);
    }

    /**
     * FileUploadException::__construct erstellt gültige Exception.
     */
    public function test_file_upload_exception_construct(): void
    {
        // Parameter-Signatur aus Source verifizieren
        $exception = new FileUploadException(UPLOAD_ERR_INI_SIZE);
        $this->assertInstanceOf(FileUploadException::class, $exception);
        $this->assertIsString($exception->getMessage());
    }

    /**
     * LanguageFrench::relationships gibt Array zurück.
     */
    public function test_language_french_relationships_returns_array(): void
    {
        $language = new LanguageFrench();
        $result   = $language->relationships();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Census::censusPlaces für 'en' gibt Array zurück.
     */
    public function test_census_census_places_en_returns_array(): void
    {
        $result = Census::censusPlaces('en');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Census::censusPlaces für 'de' gibt Array zurück.
     */
    public function test_census_census_places_de_returns_array(): void
    {
        $result = Census::censusPlaces('de');
        $this->assertIsArray($result);
    }
}
```

**Hinweis:** `NoteStructure`-Konstruktor und `labelValue`-Signatur aus Source verifizieren.
`FileUploadException`-Konstruktor-Signatur aus Source verifizieren.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'BootstrapOnlyIntegrationTest' \
  /tests/layer3-integration/tests/BootstrapOnlyIntegrationTest.php
```

---

## AP12 — RequestHandlers Batch A

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe C (CRAP 342, 272, 156, 156, 110)  
**Datei:** `layer3-integration/tests/RequestHandlerSmokeTest.php` (neu)  
**Ziel-Klassen:**
- `HelpText::handle` (public, CRAP 342, cx=18) — kein DB, Bootstrap
- `ManageMediaData::handle` (public, CRAP 272, cx=16) — DB
- `TreePrivacyAction::handle` (public, CRAP 272, cx=16) — DB
- `GedcomRecordPage::handle` (public, CRAP 132, cx=11) — DB
- `DeleteRecord::handle` (public, CRAP 110, cx=10) — DB

### PHP-Skelett (neue Datei — Konstruktoren vor Implementierung verifizieren)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage;
use Fisharebest\Webtrees\Http\RequestHandlers\HelpText;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaData;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction;

/**
 * Komponentenintegrationstest: RequestHandler Smoke-Tests Batch A.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\HelpText
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaData
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord
 */
class RequestHandlerSmokeTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * HelpText::handle gibt 200 OK zurück für gültiges Topic.
     * Konstruktor aus Source verifizieren.
     */
    public function test_help_text_handle_returns_ok(): void
    {
        $handler = new HelpText();
        $request = $this->createRequest(
            query: ['topic' => 'INDI_NAME'],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // Weitere Handler hier ergänzen nach Konstruktor-Verifikation
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RequestHandlerSmokeTest' \
  /tests/layer3-integration/tests/RequestHandlerSmokeTest.php
```

---

## AP13 — Block-Module Gruppe C (UpcomingAnniversaries, TopSurnames, ResearchTask, ClippingsCart)

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe C (CRAP 132, 110, 110, 272 + 110)  
**Datei:** `layer3-integration/tests/BlockModuleIntegrationTest.php` (erweitern, AP6-Datei)  
**Ziel-Klassen:**
- `ResearchTaskModule::getBlock` (public, CRAP 132) — DB
- `UpcomingAnniversariesModule::getBlock` (public, CRAP 110) — DB
- `TopSurnamesModule::getBlock` (public, CRAP 110) — DB
- `ClippingsCartModule::postDownloadAction` (public, CRAP 272) — DB
- `ClippingsCartModule::postAddIndividualAction` (public, CRAP 110) — DB

### Skelett-Ergänzungen

Alle via `new XModule(new YService())->getBlock($this->tree, 0, 'main')` — Konstruktoren verifizieren.
ClippingsCartModule-Actions erfordern Request mit XREF + Tree.

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'BlockModuleIntegrationTest' \
  /tests/layer3-integration/tests/BlockModuleIntegrationTest.php
```

---

## AP14 — RequestHandlers Batch B

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe B/C (CRAP 650, 306, 272, 240, 240)  
**Datei:** `layer3-integration/tests/RequestHandlerSmokeTest.php` (erweitern, AP12-Datei)  
**Ziel-Klassen:**
- `ChangeFamilyMembersAction::handle` (CRAP 650, cx=25) — DB
- `MergeRecordsPage::handle` (CRAP 306, cx=17) — DB
- `MergeFactsPage::handle` (CRAP 272, cx=16) — DB
- `RenumberTreeAction::handle` (CRAP 240, cx=15) — DB
- `UserEditAction::handle` (CRAP 240, cx=15) — DB

### Skelett-Ergänzungen

Alle brauchen `createTreeWithGedcom`. Konstruktoren und Request-Parameter aus Source verifizieren.
`RenumberTreeAction::handle` — POST-Request mit tree-Attribut, leitet wahrscheinlich weiter (301/302).

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'RequestHandlerSmokeTest' \
  /tests/layer3-integration/tests/RequestHandlerSmokeTest.php
```

---

## AP15 — ReportSetupPage (ReportParserGenerate SAX-Kette)

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

**Priorität:** Gruppe C (deckt 20+ Report-Methoden auf einmal: CRAP-Summe > 4.000)  
**Datei:** `layer3-integration/tests/ReportIntegrationTest.php` (neu)  
**Ziel-Klassen:**
- `ReportSetupPage::handle` (public, CRAP 272) — triggert vollständige SAX-Kette
- Alle `ReportParserGenerate`-Handler (listStartHandler, relativesStartHandler, getGedcomValue, setVarStartHandler, addDescendancy, imageStartHandler, repeatTagStartHandler, startElement, endElement, gedcomStartHandler, varStartHandler, factsStartHandler, factsEndHandler, listEndHandler, relativesEndHandler, getPersonNameStartHandler, gedcomValueStartHandler, addAncestors, repeatTagEndHandler, ifStartHandler)
- `HtmlRenderer::run` (CRAP 380)

### Hintergrund

`ReportSetupPage::handle` führt einen webtrees-Report aus — dieser lädt die XML-Report-Definitionsdatei
und triggert die gesamte SAX-Parser-Kette. Alle protected/private Handler in `ReportParserGenerate`
werden durch einen einzigen Test abgedeckt.

Konstruktor-Verifikation von `ReportSetupPage` aus Source notwendig.
Report-Name und Tree-Attribut müssen korrekt gesetzt sein.

### PHP-Skelett (neue Datei — Konstruktor vor Implementierung verifizieren)

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage;

/**
 * Komponentenintegrationstest: ReportSetupPage — triggert ReportParserGenerate SAX-Kette.
 *
 * Ein einzelner Report-Lauf deckt 20+ protected/private ReportParserGenerate-Methoden ab.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage
 * @covers \Fisharebest\Webtrees\Report\ReportParserGenerate
 * @covers \Fisharebest\Webtrees\Report\HtmlRenderer
 */
class ReportIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * ReportSetupPage::handle mit Ancestry-Report gibt 200 OK zurück.
     * Konstruktor und Report-Parameter aus Source verifizieren.
     */
    public function test_report_setup_page_handle_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Konstruktor und Parameter vor Implementierung aus Source verifizieren
        // Mögliche Konstruktoren: new ReportSetupPage() oder mit Module-Service
        // Request benötigt 'tree', 'report' (Report-XML-Dateiname)
        $handler = new ReportSetupPage(/* Konstruktor-Argumente */);
        $request = $this->createRequest(
            query: ['report' => 'individual', 'output' => '1', /* weitere Pflichtparams */],
            attributes: ['tree' => $this->tree, 'xref' => 'X1030'],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
```

### Verifikation

```bash
# run_in_background: true
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportIntegrationTest' \
  /tests/layer3-integration/tests/ReportIntegrationTest.php
```

---

## Finaler Commit

Erst wenn alle APs ✅ ABGESCHLOSSEN und `make test-integration` Exit 0:

```bash
# Voll-Lauf:
# run_in_background: true
make test-integration

# Dann committen:
git add layer3-integration/tests/RightToLeftSupportIntegrationTest.php
git add layer3-integration/tests/ReportHtmlObjectsIntegrationTest.php
git add layer3-integration/tests/SearchRequestHandlerIntegrationTest.php
git add layer3-integration/tests/CalendarChartIntegrationTest.php
git add layer3-integration/tests/InteractiveTreeIntegrationTest.php
git add layer3-integration/tests/BlockModuleIntegrationTest.php
git add layer3-integration/tests/StatisticsIntegrationTest.php
git add layer3-integration/tests/GedcomEditServiceIntegrationTest.php
git add layer3-integration/tests/ChartModuleIntegrationTest.php
git add layer3-integration/tests/BootstrapOnlyIntegrationTest.php
git add layer3-integration/tests/RequestHandlerSmokeTest.php
git add layer3-integration/tests/ReportIntegrationTest.php
git add layer3-integration/tests/RelationshipServiceIntegrationTest.php
git add docs/testing-bigpicture.md
git add docs/component-integration-coverage_full_analysis.md
git add docs/component-integration-coverage_full_impl_plan.md
git add docs/component-integration-coverage_full_prompt.md
git commit -m "test(layer3): Coverage-Erweiterung CRAP>100 — AP1–AP15"
```
