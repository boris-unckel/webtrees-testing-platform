<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\CellAlign;
use Fisharebest\Webtrees\Report\CellNewline;
use Fisharebest\Webtrees\Report\HtmlCell;
use Fisharebest\Webtrees\Report\HtmlFootnote;
use Fisharebest\Webtrees\Report\HtmlImage;
use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\HtmlText;
use Fisharebest\Webtrees\Report\HtmlTextBox;
use Fisharebest\Webtrees\Report\ImageContinuation;
use Fisharebest\Webtrees\Report\PageOrientation;
use Fisharebest\Webtrees\Report\PageSize;
use Fisharebest\Webtrees\Report\ReportConfig;
use Fisharebest\Webtrees\Report\Style;

/**
 * Komponentenintegrationstest: Html*-Report-Objekte Bootstrap-Tests.
 *
 * Alle Klassen sind Bootstrap-only (kein DB, kein Tree).
 * HtmlRenderer::run() und die render()-Methoden verwenden echo — Output-Buffering
 * im Test nötig.
 *
 * Upstream-Umbau (PR #5389): Die Report-Element-Klassen verloren den `Report`-Prefix
 * (ReportHtmlCell → HtmlCell …), Konstruktoren nehmen jetzt Enums (CellAlign,
 * CellNewline, ImageContinuation) und Style-Value-Objects statt Primitiven, das
 * Style-Array wurde durch addStyle(new Style(...)) ersetzt (font-Key entfallen), und
 * sowohl run() als auch render() benötigen eine via setup(ReportConfig) gesetzte Config.
 *
 * @see docs/tds_conditions_ref.md S45
 * @covers \Fisharebest\Webtrees\Report\HtmlTextBox
 * @covers \Fisharebest\Webtrees\Report\HtmlCell
 * @covers \Fisharebest\Webtrees\Report\HtmlRenderer
 * @covers \Fisharebest\Webtrees\Report\HtmlText
 * @covers \Fisharebest\Webtrees\Report\HtmlFootnote
 * @covers \Fisharebest\Webtrees\Report\HtmlImage
 */
class ReportHtmlObjectsIntegrationTest extends MysqlTestCase
{
    private HtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new HtmlRenderer();
        // run() und render() greifen auf $this->config zu — setup() ist Pflicht.
        // setup() leitet daraus noMarginWidth = 210 - 10 - 10 = 190 ab.
        $this->renderer->setup(self::reportConfig());
        // getWidth()/render() rufen getStyle() auf — Stile müssen registriert sein.
        $this->renderer->addStyle(new Style('normal', '', 12.0));
        $this->renderer->addStyle(new Style('footnote', '', 8.0));
        $this->renderer->addStyle(new Style('footnotenum', '', 8.0));
    }

    /**
     * Minimal-Config (A4 Portrait, LTR) — genügt für den standalone betriebenen Renderer.
     */
    private static function reportConfig(): ReportConfig
    {
        return new ReportConfig(
            page_width:        210.0,
            page_height:       297.0,
            left_margin:       10.0,
            right_margin:      10.0,
            top_margin:        10.0,
            bottom_margin:     10.0,
            header_margin:     5.0,
            footer_margin:     5.0,
            orientation:       PageOrientation::Portrait,
            page_size:         PageSize::A4,
            show_generated_by: false,
            rtl:               false,
            generated_by:      '',
            author:            '',
            title:             '',
            description:       '',
            align_rtl:         'left',
            entity_rtl:        'right',
            font:              'dejavusans',
        );
    }

    /**
     * HtmlRenderer::run gibt HTML-Ausgabe aus (kein Absturz).
     */
    public function test_html_renderer_run_outputs_html(): void
    {
        ob_start();
        $this->renderer->run();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('<style>', $output);
    }

    /**
     * HtmlTextBox::render läuft ohne Fehler durch.
     */
    public function test_report_html_text_box_render_runs_without_error(): void
    {
        $textBox = new HtmlTextBox(
            width:     100.0,
            height:    20.0,
            border:    false,
            bgcolor:   '',
            newline:   true,
            left:      0.0,
            top:       0.0,
            pagecheck: false,
            style:     '',
            fill:      false,
            padding:   true,
            reseth:    false,
        );

        ob_start();
        $textBox->render($this->renderer);
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    /**
     * HtmlCell::render läuft ohne Fehler durch.
     */
    public function test_report_html_cell_render_runs_without_error(): void
    {
        $cell = new HtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     CellAlign::Left,
            bgcolor:   '',
            style:     $this->renderer->getStyle('normal'),
            newline:   CellNewline::Right,
            top:       0.0,
            left:      0.0,
            fill:      false,
            stretch:   0,
            bocolor:   '',
            tcolor:    '',
            reseth:    false,
        );

        ob_start();
        $cell->render($this->renderer);
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    /**
     * HtmlText::getWidth gibt Array zurück.
     */
    public function test_report_html_text_get_width_returns_array(): void
    {
        $text = new HtmlText(style: $this->renderer->getStyle('normal'), color: '');
        $text->addText('Hello World');

        $result = $text->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    /**
     * HtmlFootnote::getWidth gibt Array zurück.
     * setWrapWidth() muss vor getWidth() aufgerufen werden — initialisiert wrapWidthRemaining.
     */
    public function test_report_html_footnote_get_width_returns_array(): void
    {
        $footnote = new HtmlFootnote(style: $this->renderer->getStyle('footnote'));
        $footnote->setNumAndLink(1, '');
        $footnote->setWrapWidth(100.0, 100.0);

        $result = $footnote->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    /**
     * HtmlImage::render läuft ohne Fehler durch.
     */
    public function test_report_html_image_render_runs_without_error(): void
    {
        $image = new HtmlImage(
            src:    '',
            x:      0.0,
            y:      0.0,
            width:  100.0,
            height: 50.0,
            align:  CellAlign::Left,
            line:   ImageContinuation::NextLine,
        );

        ob_start();
        $image->render($this->renderer);
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    // --- Neue Assertion-Tests (Runde 3, S45) ---

    /**
     * HtmlTextBox::render gibt background-color aus, wenn bgcolor gesetzt (EP-HTML-TB1).
     */
    public function test_html_textbox_render_outputs_background_color_when_fill_enabled(): void
    {
        $textBox = new HtmlTextBox(
            width:     100.0,
            height:    20.0,
            border:    false,
            bgcolor:   'ffeeee',
            newline:   true,
            left:      0.0,
            top:       0.0,
            pagecheck: false,
            style:     '',
            fill:      true,
            padding:   false,
            reseth:    false,
        );

        ob_start();
        $textBox->render($this->renderer);
        $output = ob_get_clean();

        $this->assertStringContainsString('background-color:ffeeee', $output);
    }

    /**
     * HtmlTextBox::render gibt border:solid aus, wenn border=true (EP-HTML-TB2).
     */
    public function test_html_textbox_render_outputs_border_when_border_is_true(): void
    {
        $textBox = new HtmlTextBox(
            width:     100.0,
            height:    20.0,
            border:    true,
            bgcolor:   '',
            newline:   true,
            left:      0.0,
            top:       0.0,
            pagecheck: false,
            style:     '',
            fill:      false,
            padding:   false,
            reseth:    false,
        );

        ob_start();
        $textBox->render($this->renderer);
        $output = ob_get_clean();

        $this->assertStringContainsString('border:solid black 1pt', $output);
    }

    /**
     * HtmlTextBox::render mit newline=false setzt X-Position auf cX + width (EP-HTML-TB3).
     * getRemainingWidth() = noMarginWidth (190) - X muss > width (50) bleiben, damit
     * width nicht gecappt wird.
     */
    public function test_html_textbox_render_newline_false_advances_x_position(): void
    {
        $textBox = new HtmlTextBox(
            width:     50.0,
            height:    20.0,
            border:    false,
            bgcolor:   '',
            newline:   false,
            left:      10.0,
            top:       5.0,
            pagecheck: false,
            style:     '',
            fill:      false,
            padding:   false,
            reseth:    false,
        );

        ob_start();
        $textBox->render($this->renderer);
        ob_end_clean();

        // left=10, width=50 → setXY(60, 5) am Ende von render()
        $this->assertEqualsWithDelta(60.0, $this->renderer->getX(), 0.01);
    }

    /**
     * HtmlCell::render gibt border:solid aus bei border='1' (EP-HTML-C1).
     */
    public function test_html_cell_render_full_border_when_border_is_one(): void
    {
        $cell = new HtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     CellAlign::Left,
            bgcolor:   '',
            style:     $this->renderer->getStyle('normal'),
            newline:   CellNewline::NextLine,
            top:       0.0,
            left:      0.0,
            fill:      false,
            stretch:   0,
            bocolor:   '',
            tcolor:    '',
            reseth:    false,
        );

        ob_start();
        $cell->render($this->renderer);
        $output = ob_get_clean();

        $this->assertStringContainsString('border:solid', $output);
    }

    /**
     * HtmlCell::render gibt border-top:solid aus bei border='T' (EP-HTML-C2).
     * Nur oben-Rahmen → kein border-bottom.
     */
    public function test_html_cell_render_top_border_when_border_is_t(): void
    {
        $cell = new HtmlCell(
            width:     100.0,
            height:    20.0,
            border:    'T',
            align:     CellAlign::Left,
            bgcolor:   '',
            style:     $this->renderer->getStyle('normal'),
            newline:   CellNewline::NextLine,
            top:       0.0,
            left:      0.0,
            fill:      false,
            stretch:   0,
            bocolor:   '',
            tcolor:    '',
            reseth:    false,
        );

        ob_start();
        $cell->render($this->renderer);
        $output = ob_get_clean();

        $this->assertStringContainsString('border-top:solid', $output);
        $this->assertStringNotContainsString('border-bottom', $output);
    }

    /**
     * HtmlCell::render mit text={{:ptp:}} gibt keine Ausgabe (Early-Return EP-HTML-C3).
     */
    public function test_html_cell_render_no_output_when_text_is_ptp_placeholder(): void
    {
        $cell = new HtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     CellAlign::Left,
            bgcolor:   '',
            style:     $this->renderer->getStyle('normal'),
            newline:   CellNewline::NextLine,
            top:       0.0,
            left:      0.0,
            fill:      false,
            stretch:   0,
            bocolor:   '',
            tcolor:    '',
            reseth:    false,
        );
        $cell->addText('{{:ptp:}}');

        ob_start();
        $cell->render($this->renderer);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /**
     * HtmlCell::render gibt background-color aus, wenn bgcolor gesetzt (EP-HTML-C4).
     */
    public function test_html_cell_render_outputs_background_color_when_bgcolor_set(): void
    {
        $cell = new HtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '',
            align:     CellAlign::Left,
            bgcolor:   'ffeeee',
            style:     $this->renderer->getStyle('normal'),
            newline:   CellNewline::NextLine,
            top:       0.0,
            left:      0.0,
            fill:      false,
            stretch:   0,
            bocolor:   '',
            tcolor:    '',
            reseth:    false,
        );

        ob_start();
        $cell->render($this->renderer);
        $output = ob_get_clean();

        $this->assertStringContainsString('background-color:ffeeee', $output);
    }
}
