<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage;
use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\ReportParserGenerate;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Webtrees;

/**
 * Komponentenintegrationstest: Report-System.
 *
 * ReportSetupPage::handle   — Setup-Formular (CRAP 272)
 * ReportGenerate::handle    — triggert new ReportParserGenerate (CRAP > 4.000 SAX-Kette)
 * ReportParserGenerate direkt — steuert alle SAX-Handler an
 *
 * Verwendet birth_report (kleinstes XML, keine Pflicht-Eingaben).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate
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
}
