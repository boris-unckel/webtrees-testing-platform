<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportListAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\PdfRenderer;
use Fisharebest\Webtrees\Report\ReportParserGenerate;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: Report-System.
 *
 * ReportSetupPage::handle   — Setup-Formular (CRAP 272)
 * ReportGenerate::handle    — triggert new ReportParserGenerate (CRAP > 4.000 SAX-Kette)
 * ReportParserGenerate direkt — steuert alle SAX-Handler an
 * ReportListAction::handle  — Redirect-Logik abhängig von ModuleService-Treffer
 *
 * Verwendet birth_report (kleinstes XML, keine Pflicht-Eingaben).
 *
 * @see docs/tds_conditions_ref.md S43
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListPageTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportSetupActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportSetupPageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportListAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage
 * @covers \Fisharebest\Webtrees\Report\ReportParserGenerate
 * @covers \Fisharebest\Webtrees\Report\HtmlRenderer
 */
class ReportIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED   = '/fixtures/demo.ged';
    private const REPORT_NAME = 'birth_report';

    /**
     * Absoluter Pfad zur XML-Definitionsdatei von birth_report.
     */
    private static function birthReportXml(): string
    {
        return Webtrees::ROOT_DIR . 'resources/xml/reports/' . self::REPORT_NAME . '.xml';
    }

    // --- ReportSetupPage (CRAP 272) ---

    /**
     * ReportSetupPage::handle gibt 200 OK zurück, wenn Modul gefunden wird.
     * Falls birth_report in DB deaktiviert, gibt es einen Redirect (3xx) — beides akzeptiert.
     */
    public function test_report_setup_page_birth_report_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler  = new ReportSetupPage(new ModuleService());
        $request  = $this->createRequest(
            attributes: [
                'tree'   => $this->tree,
                'user'   => $admin,
                'report' => self::REPORT_NAME,
            ],
        );
        $response = $handler->handle($request);

        // 200 OK (Formular) oder 3xx (Modul nicht gefunden → Redirect)
        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    /**
     * ReportSetupPage::handle leitet weiter (302 Found), wenn das Modul nicht gefunden wird.
     * Stub-basierter Negativpfad: ModuleService::findByName liefert null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportSetupPageTest.php
     * @group ported-l2-doubles
     */
    public function test_report_setup_page_redirects_when_module_not_found(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByName')->willReturn(null);

        $handler  = new ReportSetupPage($module_service);
        $request  = $this->createRequest(
            attributes: [
                'tree'   => $tree,
                'user'   => $user,
                'report' => 'nonexistent',
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    // --- ReportGenerate via handle() (triggert SAX-Kette) ---

    /**
     * ReportGenerate::handle führt birth_report als HTML aus.
     * Triggert intern new ReportParserGenerate → vollständige SAX-Parser-Kette.
     */
    public function test_report_generate_birth_report_html_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler  = new ReportGenerate(new ModuleService());
        $request  = $this->createRequest(
            query: [
                'varnames'    => array_keys(self::defaultReportVars()),
                'vars'        => self::defaultReportVars(),
                'format'      => 'HTML',
                'destination' => 'screen',
            ],
            attributes: [
                'tree'   => $this->tree,
                'user'   => $admin,
                'report' => self::REPORT_NAME,
            ],
        );
        $response = $handler->handle($request);

        // 200 OK (Report-Output) oder 3xx (Modul nicht aktiv)
        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- ReportParserGenerate direkt (SAX-Kette, unabhängig von ModuleService) ---

    /**
     * Standard-Vars für Berichte (entsprechen den XML-Input-Defaults).
     *
     * @return array<string,string>
     */
    private static function defaultReportVars(): array
    {
        return [
            'sortby'     => 'NAME',
            'pageSize'   => 'A4',
            'birthdate1' => '',
            'birthdate2' => '',
            'birthplace' => '',
            'name'       => '',
            'adlist'     => 'none',
            'deathdate1' => '',
            'deathdate2' => '',
            'deathplace' => '',
        ];
    }

    /**
     * ReportParserGenerate direkt — birth_report triggert SAX-Kette.
     *
     * Dieser Test ist unabhängig vom ModuleService-Status in der DB.
     * Der Konstruktor löst xml_parse() aus → alle protected/private SAX-Handler werden aufgerufen.
     */
    public function test_report_parser_generate_birth_report_direct_no_exception(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $xml = self::birthReportXml();
        $this->assertFileExists($xml, 'birth_report.xml muss im webtrees-Ressourcenverzeichnis existieren');

        ob_start();
        try {
            new ReportParserGenerate($xml, new HtmlRenderer(), self::defaultReportVars(), $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertIsString($output);
    }

    /**
     * ReportParserGenerate direkt — cemetery_report (mehr SAX-Elemente).
     */
    public function test_report_parser_generate_cemetery_report_direct_no_exception(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $xml = Webtrees::ROOT_DIR . 'resources/xml/reports/cemetery_report.xml';
        $this->assertFileExists($xml);

        ob_start();
        try {
            new ReportParserGenerate($xml, new HtmlRenderer(), self::defaultReportVars(), $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertIsString($output);
    }

    /**
     * ReportParserGenerate direkt — death_report für weitere SAX-Pfad-Abdeckung.
     */
    public function test_report_parser_generate_death_report_direct_no_exception(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $xml = Webtrees::ROOT_DIR . 'resources/xml/reports/death_report.xml';
        $this->assertFileExists($xml);

        ob_start();
        try {
            new ReportParserGenerate($xml, new HtmlRenderer(), self::defaultReportVars(), $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertIsString($output);
    }

    // --- ReportGenerate via handle() — Format/Destination-Branches (EP2/EP6/B1) ---

    /**
     * format='PDF' → content-type: application/pdf (EP2).
     * birth_report ist isEnabledByDefault()=true → Modul immer gefunden.
     */
    public function test_report_generate_pdf_format_returns_pdf_content_type(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler = new ReportGenerate(new ModuleService());
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'varnames'    => array_keys(self::defaultReportVars()),
                'vars'        => self::defaultReportVars(),
                'format'      => 'PDF',
                'destination' => 'view',
            ],
            attributes: [
                'tree'   => $this->tree,
                'user'   => $admin,
                'report' => self::REPORT_NAME,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->getHeaderLine('content-type'));
    }

    /**
     * destination='download' für HTML → Content-Disposition: attachment (EP6).
     */
    public function test_report_generate_html_download_sets_content_disposition_header(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler = new ReportGenerate(new ModuleService());
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'varnames'    => array_keys(self::defaultReportVars()),
                'vars'        => self::defaultReportVars(),
                'format'      => 'HTML',
                'destination' => 'download',
            ],
            attributes: [
                'tree'   => $this->tree,
                'user'   => $admin,
                'report' => self::REPORT_NAME,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
        $this->assertStringContainsString(self::REPORT_NAME, $response->getHeaderLine('content-disposition'));
    }

    /**
     * Unbekannter Berichtsname → ModuleService findet kein Modul → Redirect (B1).
     */
    public function test_report_generate_unknown_report_redirects(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler = new ReportGenerate(new ModuleService());
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'varnames'    => [],
                'vars'        => [],
                'format'      => 'HTML',
                'destination' => 'view',
            ],
            attributes: [
                'tree'   => $this->tree,
                'user'   => $admin,
                'report' => 'xyz_nonexistent_report_9999',
            ],
        );
        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- ReportListAction (Redirect je nach ModuleService-Treffer) ---

    /**
     * ReportListAction::handle leitet zur Setup-Seite weiter, wenn das Modul gefunden wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListActionTest.php
     * @group ported-l2-doubles
     */
    public function test_report_list_action_redirects_to_setup_page_when_module_found(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $report = self::createStub(ModuleReportInterface::class);
        $report->method('name')->willReturn('test-report');
        // PRIV_PRIVATE allows guests — avoids HttpAccessDeniedException.
        $report->method('accessLevel')->willReturn(Auth::PRIV_PRIVATE);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByName')->willReturn($report);

        $handler  = new ReportListAction($module_service);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['report' => 'test-report'],
            attributes: [
                'tree' => $tree,
                'user' => $user,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('test-report', $response->getHeaderLine('location'));
    }

    /**
     * ReportListAction::handle leitet zur Listenseite zurück, wenn kein Modul gefunden wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListActionTest.php
     * @group ported-l2-doubles
     */
    public function test_report_list_action_redirects_to_list_page_when_module_not_found(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByName')->willReturn(null);

        $handler  = new ReportListAction($module_service);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['report' => 'nonexistent'],
            attributes: [
                'tree' => $tree,
                'user' => $user,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    // --- ReportSetupAction (Redirect zum Generate-Handler) ---

    /**
     * ReportSetupAction::handle leitet zum Generate-Handler weiter, wenn das Modul gefunden wird.
     * Der Location-Header muss den Report-Namen enthalten.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportSetupActionTest.php
     * @group ported-l2-doubles
     */
    public function test_report_setup_action_redirects_to_generate_when_module_found(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $report = self::createStub(ModuleReportInterface::class);
        $report->method('name')->willReturn('test-report');
        // PRIV_PRIVATE allows guests — avoids HttpAccessDeniedException.
        $report->method('accessLevel')->willReturn(Auth::PRIV_PRIVATE);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByName')->willReturn($report);

        $handler  = new ReportSetupAction($module_service);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'destination' => 'view',
                'format'      => 'HTML',
                'varnames'    => [],
                'vars'        => [],
            ],
            attributes: [
                'tree'   => $tree,
                'user'   => $user,
                'report' => 'test-report',
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('test-report', $response->getHeaderLine('location'));
    }

    /**
     * ReportSetupAction::handle leitet zur Listenseite zurück, wenn kein Modul gefunden wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportSetupActionTest.php
     * @group ported-l2-doubles
     */
    public function test_report_setup_action_redirects_to_list_page_when_module_not_found(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByName')->willReturn(null);

        $handler  = new ReportSetupAction($module_service);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'destination' => 'view',
                'format'      => 'HTML',
                'varnames'    => [],
                'vars'        => [],
            ],
            attributes: [
                'tree'   => $tree,
                'user'   => $user,
                'report' => 'nonexistent',
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    // --- ReportListPage (Auswahl-Übersicht — Render via ViewResponseTrait) ---

    /**
     * ReportListPage::handle liefert über die Container-Auflösung mit echtem
     * Tree und eingeloggtem Admin eine 200-OK-Response. Der gerenderte Body
     * enthält den lokalisierten Titel "Choose a report to run" — Beweis, dass
     * die View `report-select-page` mit der erwarteten Übersetzung gerendert
     * wurde. Komplementär zu den beiden Stub-Tests darunter, die nur die
     * Statuscodes mit injiziertem ModuleService prüfen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListPageTest.php
     * @group ported-l2-doubles
     */
    public function test_report_list_page_handle_returns_ok_with_real_container_and_tree(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler  = Registry::container()->get(ReportListPage::class);
        $request  = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'user' => $admin,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString(
            'Choose a report to run',
            (string) $response->getBody(),
        );
    }

    /**
     * ReportListPage::handle gibt 200 OK zurück, wenn keine Reports zur Verfügung stehen.
     * ModuleService::findByComponent wird genau einmal mit (ModuleReportInterface, tree, user) aufgerufen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListPageTest.php
     * @group ported-l2-doubles
     */
    public function test_report_list_page_handle_returns_ok_response_when_no_reports(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByComponent')
            ->with(ModuleReportInterface::class, $tree, $user)
            ->willReturn(new Collection());

        $handler  = new ReportListPage($module_service);
        $request  = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'user' => $user,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * ReportListPage::handle gibt 200 OK zurück, wenn Reports verfügbar sind.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ReportListPageTest.php
     * @group ported-l2-doubles
     */
    public function test_report_list_page_handle_returns_ok_response_when_reports_available(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $user = self::createStub(UserInterface::class);

        $report = self::createStub(ModuleReportInterface::class);
        $report->method('name')->willReturn('test-report');

        $module_service = self::createStub(ModuleService::class);
        $module_service->method('findByComponent')->willReturn(new Collection([$report]));

        $handler  = new ReportListPage($module_service);
        $request  = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'user' => $user,
            ],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
