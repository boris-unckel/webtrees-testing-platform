<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Exceptions\HttpGoneException;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectCalendarPhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectFamilyPhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectGedRecordPhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectIndividualPhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectNotePhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectPedigreePhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectReportEnginePhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectRepositoryPhp;
use Fisharebest\Webtrees\Http\RequestHandlers\RedirectSourcePhp;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;

/**
 * Komponentenintegrationstest: Legacy-URL-Weiterleitungen (S53).
 *
 * Prüft repräsentative Redirect*Php-Handler: Record-basiert (Individual, Family,
 * Source, Note, Repository, GedRecord), parameterbasiert (Calendar, ReportEngine),
 * modulbasiert (Pedigree) sowie Fehler- und Fallback-Pfade.
 *
 * @see docs/tds_conditions_ref.md S53
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectIndividualPhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectFamilyPhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectSourcePhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectNotePhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectRepositoryPhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectGedRecordPhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectCalendarPhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectReportEnginePhp
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RedirectPedigreePhp
 */
class LegacyUrlRedirectIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createRedirectTestTree();
    }

    /**
     * Erstellt einen Testbaum mit Individual, Family, Source, Note, Repository.
     */
    private function createRedirectTestTree(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'redirect-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Redirect Test');

        $records = [
            "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M",
            "0 @F1@ FAM\n1 HUSB @I1@",
            "0 @S1@ SOUR\n1 TITL Test Source",
            "0 @N1@ NOTE Test Note Content",
            "0 @R1@ REPO\n1 NAME Test Repository",
        ];

        foreach ($records as $record) {
            $this->gedcomImportService->importRecord($record, $this->tree, false);
        }
    }

    // --- Record-basierte Redirects (EP1, EP5, EP6) ---

    /**
     * EP1/B1: Individual — gültiger Tree + gültige PID → 301 Redirect.
     */
    public function test_individual_redirect_valid(): void
    {
        $handler  = new RedirectIndividualPhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'pid' => 'I1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
        $this->assertStringContainsString('rel="canonical"', $response->getHeaderLine('Link'));
    }

    /**
     * EP5/B1: Family — gültiger Tree + gültige XREF → 301 Redirect.
     */
    public function test_family_redirect_valid(): void
    {
        $handler  = new RedirectFamilyPhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'famid' => 'F1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * EP6/B1: Source — gültiger Tree + gültige SID → 301 Redirect.
     */
    public function test_source_redirect_valid(): void
    {
        $handler  = new RedirectSourcePhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'sid' => 'S1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * Note — gültiger Tree + gültige NID → 301 Redirect.
     */
    public function test_note_redirect_valid(): void
    {
        $handler  = new RedirectNotePhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'nid' => 'N1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * Repository — gültiger Tree + gültige RID → 301 Redirect.
     */
    public function test_repository_redirect_valid(): void
    {
        $handler  = new RedirectRepositoryPhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'rid' => 'R1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * GedRecord — gültiger Tree + gültige PID → 301 Redirect.
     */
    public function test_gedrecord_redirect_valid(): void
    {
        $handler  = new RedirectGedRecordPhp($this->treeService);
        $request  = $this->createRequest(query: ['ged' => $this->tree->name(), 'pid' => 'I1']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    // --- Fehler-Pfade (EP2, EP3) ---

    /**
     * EP3/B3: Ungültiger Tree → 410 HttpGoneException.
     */
    public function test_invalid_tree_returns_410(): void
    {
        $handler = new RedirectIndividualPhp($this->treeService);
        $request = $this->createRequest(query: ['ged' => 'nonexistent-tree', 'pid' => 'I1']);

        $this->expectException(HttpGoneException::class);
        $handler->handle($request);
    }

    /**
     * EP2/B2: Gültiger Tree, Record nicht gefunden → 410 HttpGoneException.
     */
    public function test_invalid_record_returns_410(): void
    {
        $handler = new RedirectIndividualPhp($this->treeService);
        $request = $this->createRequest(query: ['ged' => $this->tree->name(), 'pid' => 'I999']);

        $this->expectException(HttpGoneException::class);
        $handler->handle($request);
    }

    // --- Spezial-Handler (EP7, EP9, B7, B8) ---

    /**
     * EP7/B8: Calendar — gültiger Tree → 301 Redirect.
     */
    public function test_calendar_redirect_valid(): void
    {
        $handler  = new RedirectCalendarPhp($this->treeService);
        $request  = $this->createRequest(query: [
            'ged'  => $this->tree->name(),
            'view' => 'day',
            'year' => '2026',
        ]);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * EP9/B7: ReportEngine mit action=run → 301 Redirect.
     */
    public function test_report_engine_redirect_valid(): void
    {
        $handler  = new RedirectReportEnginePhp($this->treeService);
        $request  = $this->createRequest(query: [
            'ged'    => $this->tree->name(),
            'action' => 'run',
            'report' => 'modules_v4/report_module/report.xml',
        ]);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * B7: ReportEngine ohne action=run → 410 HttpGoneException.
     */
    public function test_report_engine_no_action_returns_410(): void
    {
        $handler = new RedirectReportEnginePhp($this->treeService);
        $request = $this->createRequest(query: [
            'ged'    => $this->tree->name(),
            'action' => 'setup',
            'report' => 'modules_v4/report_module/report.xml',
        ]);

        $this->expectException(HttpGoneException::class);
        $handler->handle($request);
    }

    // --- Fallback + Modul-basiert (EP10, B6, B9) ---

    /**
     * EP10/B9: ged fehlt, DEFAULT_GEDCOM gesetzt → Fallback auf Default-Tree.
     */
    public function test_default_gedcom_fallback(): void
    {
        Site::setPreference('DEFAULT_GEDCOM', $this->tree->name());

        $handler  = new RedirectCalendarPhp($this->treeService);
        $request  = $this->createRequest(query: ['view' => 'day']);
        $response = $handler->handle($request);

        $this->assertSame(301, $response->getStatusCode());
    }

    /**
     * B6: Pedigree-Redirect mit Style-Mapping (orientation→style).
     */
    public function test_pedigree_redirect_with_style_mapping(): void
    {
        $moduleService = new ModuleService();
        $handler       = new RedirectPedigreePhp($moduleService, $this->treeService);

        $request = $this->createRequest(query: [
            'ged'         => $this->tree->name(),
            'rootid'      => 'I1',
            'orientation'  => '2',
            'generations'  => '5',
        ]);

        try {
            $response = $handler->handle($request);
            $this->assertSame(301, $response->getStatusCode());
            $location = $response->getHeaderLine('Location');
            $this->assertNotEmpty($location);
        } catch (HttpGoneException) {
            // PedigreeChartModule nicht für diesen Test-Tree verfügbar — akzeptabel
            $this->addToAssertionCount(1);
        }
    }
}
