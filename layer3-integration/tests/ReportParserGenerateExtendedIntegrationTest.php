<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\ParserGenerate;
use Fisharebest\Webtrees\Webtrees;

/**
 * Komponentenintegrationstest: ParserGenerate — erweiterte SAX-Handler.
 *
 * AP B-03: relativesStartHandler (552), addDescendancy (272), imageStartHandler (240),
 *          factsStartHandler (182), factsEndHandler (182), relativesEndHandler (182),
 *          addAncestors (156).
 *
 * Verwendet relative_ext_report.xml und individual_ext_report.xml,
 * die <relatives>, <facts> und <image> Elemente enthalten.
 *
 * @see docs/tds_conditions_ref.md S44
 * @covers \Fisharebest\Webtrees\Report\ParserGenerate
 */
class ReportParserGenerateExtendedIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();
    }

    /** @return array<string,string> */
    private function relativeReportVars(string $xref): array
    {
        return [
            'pid'       => $xref,
            'relatives' => 'direct-ancestors',
            'sortby'    => 'NAME',
            'pageSize'  => 'A4',
        ];
    }

    /** @return array<string,string> */
    private function individualExtReportVars(string $xref): array
    {
        return [
            'pid'      => $xref,
            'relatives'=> 'direct-ancestors',
            'maxgen'   => '2',
            'sortby'   => 'NAME',
            'sources'  => '1',
            'notes'    => '1',
            'photos'   => 'highlighted',
            'pageSize' => 'A4',
            'colors'   => '1',
        ];
    }

    private function firstIndiXref(): string
    {
        $xref = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->value('i_id');
        $this->assertNotNull($xref, 'Kein Individual in demo.ged vorhanden');
        return (string) $xref;
    }

    /**
     * relative_ext_report triggert relativesStartHandler, relativesEndHandler, addAncestors.
     * Output muss non-empty und HTML-Markup enthalten (EP1: Person mit Demo-Vorfahren).
     */
    public function test_relative_ext_report_triggers_relatives_handlers(): void
    {
        $xml  = Webtrees::ROOT_DIR . 'resources/xml/reports/relative_ext_report.xml';
        $this->assertFileExists($xml);

        $xref = $this->firstIndiXref();

        ob_start();
        try {
            new ParserGenerate($xml, new HtmlRenderer(), $this->relativeReportVars($xref), $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<', $output);
    }

    /**
     * relative_ext_report mit descendants — triggert addDescendancy.
     * Output muss non-empty und HTML-Markup enthalten (EP3: Person mit Demo-Nachfahren).
     */
    public function test_relative_ext_report_descendants_triggers_add_descendancy(): void
    {
        $xml  = Webtrees::ROOT_DIR . 'resources/xml/reports/relative_ext_report.xml';
        $xref = $this->firstIndiXref();
        $vars = [
            'pid'       => $xref,
            'relatives' => 'descendants',
            'sortby'    => 'NAME',
            'pageSize'  => 'A4',
        ];

        ob_start();
        try {
            new ParserGenerate($xml, new HtmlRenderer(), $vars, $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<', $output);
    }

    /**
     * individual_ext_report triggert factsStartHandler, factsEndHandler, imageStartHandler.
     * Output muss non-empty und HTML-Markup enthalten (EP7: Person mit BIRT/DEAT-Fakten).
     */
    public function test_individual_ext_report_triggers_facts_and_image_handlers(): void
    {
        $xml  = Webtrees::ROOT_DIR . 'resources/xml/reports/individual_ext_report.xml';
        $this->assertFileExists($xml);

        $xref = $this->firstIndiXref();

        ob_start();
        try {
            new ParserGenerate($xml, new HtmlRenderer(), $this->individualExtReportVars($xref), $this->tree);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<', $output);
    }
}
