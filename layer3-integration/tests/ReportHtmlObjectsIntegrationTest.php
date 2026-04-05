<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\ReportHtmlCell;
use Fisharebest\Webtrees\Report\ReportHtmlFootnote;
use Fisharebest\Webtrees\Report\ReportHtmlImage;
use Fisharebest\Webtrees\Report\ReportHtmlText;
use Fisharebest\Webtrees\Report\ReportHtmlTextBox;

/**
 * Komponentenintegrationstest: ReportHtml*-Objekte Bootstrap-Tests.
 *
 * Alle Klassen sind Bootstrap-only (kein DB, kein Tree).
 * HtmlRenderer::run() und die render()-Methoden verwenden echo — Output-Buffering
 * im Test nötig.
 *
 * @see docs/testing-bigpicture.md S45
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlTextBox
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlCell
 * @covers \Fisharebest\Webtrees\Report\HtmlRenderer
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlText
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlFootnote
 * @covers \Fisharebest\Webtrees\Report\ReportHtmlImage
 */
class ReportHtmlObjectsIntegrationTest extends MysqlTestCase
{
    private HtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new HtmlRenderer();
        // getWidth() ruft getStyle() auf — Stile müssen im Renderer vorhanden sein
        $this->renderer->styles = [
            'normal'      => ['name' => 'normal',      'font' => 'dejavusans', 'style' => '', 'size' => 12.0],
            'footnote'    => ['name' => 'footnote',    'font' => 'dejavusans', 'style' => '', 'size' => 8.0],
            'footnotenum' => ['name' => 'footnotenum', 'font' => 'dejavusans', 'style' => '', 'size' => 8.0],
        ];
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
     * ReportHtmlTextBox::render läuft ohne Fehler durch.
     */
    public function test_report_html_text_box_render_runs_without_error(): void
    {
        $textBox = new ReportHtmlTextBox(
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
     * ReportHtmlCell::render läuft ohne Fehler durch.
     */
    public function test_report_html_cell_render_runs_without_error(): void
    {
        $cell = new ReportHtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     'left',
            bgcolor:   '',
            styleName: 'normal',
            newline:   0,
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
     * ReportHtmlText::getWidth gibt Array zurück.
     */
    public function test_report_html_text_get_width_returns_array(): void
    {
        $text = new ReportHtmlText('normal', '');
        $text->addText('Hello World');

        $result = $text->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    /**
     * ReportHtmlFootnote::getWidth gibt Array zurück.
     * setWrapWidth() muss vor getWidth() aufgerufen werden — initialisiert wrapWidthRemaining.
     */
    public function test_report_html_footnote_get_width_returns_array(): void
    {
        $footnote = new ReportHtmlFootnote('footnote');
        $footnote->setNum(1);
        $footnote->setWrapWidth(100.0, 100.0);

        $result = $footnote->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    /**
     * ReportHtmlImage::render läuft ohne Fehler durch.
     */
    public function test_report_html_image_render_runs_without_error(): void
    {
        $image = new ReportHtmlImage(
            src:    '',
            x:      0.0,
            y:      0.0,
            width:  100.0,
            height: 50.0,
            align:  'L',
            line:   'N',
        );

        ob_start();
        $image->render($this->renderer);
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    // --- Neue Assertion-Tests (Runde 3, S45) ---

    /**
     * ReportHtmlTextBox::render gibt background-color aus, wenn fill=true und bgcolor gesetzt (EP-HTML-TB1).
     */
    public function test_html_textbox_render_outputs_background_color_when_fill_enabled(): void
    {
        $textBox = new ReportHtmlTextBox(
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
     * ReportHtmlTextBox::render gibt border:solid aus, wenn border=true (EP-HTML-TB2).
     */
    public function test_html_textbox_render_outputs_border_when_border_is_true(): void
    {
        $textBox = new ReportHtmlTextBox(
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
     * ReportHtmlTextBox::render mit newline=false setzt X-Position auf cX + width (EP-HTML-TB3).
     * noMarginWidth muss > 0 gesetzt sein, damit getRemainingWidth() positiv bleibt
     * und width nicht auf 0 gecappt wird.
     */
    public function test_html_textbox_render_newline_false_advances_x_position(): void
    {
        // Standard-Seitenbreite ohne Margins (wird für getRemainingWidth() benötigt)
        $this->renderer->noMarginWidth = 200.0;

        $textBox = new ReportHtmlTextBox(
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

        // left=10, width=50 → setXy(60, 5) am Ende von render()
        $this->assertEqualsWithDelta(60.0, $this->renderer->getX(), 0.01);
    }

    /**
     * ReportHtmlCell::render gibt border:solid aus bei border='1' (EP-HTML-C1).
     */
    public function test_html_cell_render_full_border_when_border_is_one(): void
    {
        $cell = new ReportHtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     'L',
            bgcolor:   '',
            styleName: 'normal',
            newline:   1,
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
     * ReportHtmlCell::render gibt border-top:solid aus bei border='T' (EP-HTML-C2).
     * Nur oben-Rahmen → kein border-bottom.
     */
    public function test_html_cell_render_top_border_when_border_is_t(): void
    {
        $cell = new ReportHtmlCell(
            width:     100.0,
            height:    20.0,
            border:    'T',
            align:     'L',
            bgcolor:   '',
            styleName: 'normal',
            newline:   1,
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
     * ReportHtmlCell::render mit text={{:ptp:}} gibt keine Ausgabe (Early-Return EP-HTML-C3).
     */
    public function test_html_cell_render_no_output_when_text_is_ptp_placeholder(): void
    {
        $cell = new ReportHtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '1',
            align:     'L',
            bgcolor:   '',
            styleName: 'normal',
            newline:   1,
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
     * ReportHtmlCell::render gibt background-color aus, wenn bgcolor gesetzt (EP-HTML-C4).
     */
    public function test_html_cell_render_outputs_background_color_when_bgcolor_set(): void
    {
        $cell = new ReportHtmlCell(
            width:     100.0,
            height:    20.0,
            border:    '',
            align:     'L',
            bgcolor:   'ffeeee',
            styleName: 'normal',
            newline:   1,
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
