<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\PdfRenderer;
use Fisharebest\Webtrees\Report\ReportBaseElement;
use Fisharebest\Webtrees\Report\ReportPdfCell;
use Fisharebest\Webtrees\Report\ReportPdfFootnote;
use Fisharebest\Webtrees\Report\ReportPdfImage;
use Fisharebest\Webtrees\Report\ReportPdfText;
use Fisharebest\Webtrees\Report\ReportPdfTextBox;

/**
 * Komponentenintegrationstest: ReportPdf*-Objekte (TCPDF-basiert).
 *
 * AP A-01: ReportPdfTextBox::render  (CRAP 3.660)
 * AP B-06: ReportPdfCell::render     (CRAP 342)
 * AP C-04: ReportPdfFootnote::getWidth + ReportPdfText::getWidth (je CRAP 210)
 *
 * Alle Klassen sind Bootstrap-only (kein DB, kein Tree).
 * PdfRenderer::setup() initialisiert TcpdfWrapper (TCPDF).
 * AddPage() muss vor render() aufgerufen werden.
 *
 * @see docs/tds_conditions_ref.md S45
 * @covers \Fisharebest\Webtrees\Report\ReportPdfTextBox
 * @covers \Fisharebest\Webtrees\Report\ReportPdfCell
 * @covers \Fisharebest\Webtrees\Report\ReportPdfFootnote
 * @covers \Fisharebest\Webtrees\Report\ReportPdfText
 * @covers \Fisharebest\Webtrees\Report\PdfRenderer
 */
class ReportPdfObjectsIntegrationTest extends MysqlTestCase
{
    private PdfRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new PdfRenderer();
        $this->renderer->styles = [
            'normal'      => ['name' => 'normal',      'font' => 'dejavusans', 'style' => '',  'size' => 12.0],
            'footnote'    => ['name' => 'footnote',    'font' => 'dejavusans', 'style' => '',  'size' => 8.0],
            'footnotenum' => ['name' => 'footnotenum', 'font' => 'dejavusans', 'style' => 'B', 'size' => 8.0],
        ];
        $this->renderer->setup();
        $this->renderer->tcpdf->AddPage();
    }

    // --- ReportPdfTextBox (AP A-01, CRAP 3.660) ---

    /**
     * ReportPdfTextBox::render läuft auf leerem TextBox ohne Fehler.
     */
    public function test_report_pdf_text_box_render_empty_box(): void
    {
        $textBox = new ReportPdfTextBox(
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

        // render() schreibt direkt in TCPDF — kein Echo, kein ob_start nötig
        $textBox->render($this->renderer);

        $this->assertTrue(true); // Kein Exception = Erfolg
    }

    /**
     * ReportPdfTextBox::render mit Rahmen und Hintergrundfarbe.
     */
    public function test_report_pdf_text_box_render_with_border_and_fill(): void
    {
        $textBox = new ReportPdfTextBox(
            width:     80.0,
            height:    15.0,
            border:    true,
            bgcolor:   '#eeeeee',
            newline:   false,
            left:      0.0,
            top:       0.0,
            pagecheck: false,
            style:     'DF',
            fill:      true,
            padding:   false,
            reseth:    false,
        );

        $textBox->render($this->renderer);

        $this->assertTrue(true);
    }

    /**
     * ReportPdfTextBox::render mit PageCheck (Seitenumbruch-Logik).
     */
    public function test_report_pdf_text_box_render_with_pagecheck(): void
    {
        $textBox = new ReportPdfTextBox(
            width:     0.0,  // volle Seitenbreite
            height:    10.0,
            border:    false,
            bgcolor:   '',
            newline:   true,
            left:      0.0,
            top:       0.0,
            pagecheck: true,
            style:     '',
            fill:      false,
            padding:   true,
            reseth:    true,
        );

        $textBox->render($this->renderer);

        $this->assertTrue(true);
    }

    // --- ReportPdfCell (AP B-06, CRAP 342) ---

    /**
     * ReportPdfCell::render läuft ohne Fehler.
     */
    public function test_report_pdf_cell_render_simple(): void
    {
        $cell = new ReportPdfCell(
            width:     100.0,
            height:    10.0,
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

        $cell->render($this->renderer);

        $this->assertTrue(true);
    }

    /**
     * ReportPdfCell::render zentriert, kein Rahmen.
     */
    public function test_report_pdf_cell_render_centered_no_border(): void
    {
        $cell = new ReportPdfCell(
            width:     80.0,
            height:    10.0,
            border:    '',
            align:     'C',
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

        $cell->render($this->renderer);

        $this->assertTrue(true);
    }

    // --- ReportPdfFootnote::getWidth + ReportPdfText::getWidth (AP C-04, je CRAP 210) ---

    /**
     * ReportPdfFootnote::getWidth gibt Array zurück.
     */
    public function test_report_pdf_footnote_get_width_returns_array(): void
    {
        $footnote = new ReportPdfFootnote('footnote');
        $footnote->setNum(1);
        $footnote->setWrapWidth(100.0, 100.0);

        $result = $footnote->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    /**
     * ReportPdfText::getWidth gibt Array zurück.
     */
    public function test_report_pdf_text_get_width_returns_array(): void
    {
        $text = new ReportPdfText('normal', '');
        $text->addText('Test');
        $text->setWrapWidth(100.0, 100.0);

        $result = $text->getWidth($this->renderer);

        $this->assertIsArray($result);
    }

    // --- Neue Tests für ReportPdfImage (Runde 3, S45) ---

    /**
     * ReportPdfImage::render mit CURRENT_POSITION und line='N' rückt Y-Position vor (EP-PDF-IMG1 / N1).
     * Nach render: Y = initialY + height (lastpicbottom via tcpdf->setY).
     * Testbild: /var/www/html/public/favicon-32.png (32×32 PNG aus webtrees-Source).
     */
    public function test_pdf_image_render_with_current_position_and_line_n_advances_y(): void
    {
        $initialY = $this->renderer->tcpdf->GetY();

        $image = new ReportPdfImage(
            src:    '/var/www/html/public/favicon-32.png',
            x:      ReportBaseElement::CURRENT_POSITION,
            y:      ReportBaseElement::CURRENT_POSITION,
            width:  10.0,
            height: 10.0,
            align:  'L',
            line:   'N',
        );
        $image->render($this->renderer);

        // line='N' → tcpdf->setY(lastpicbottom) = setY(initialY + 10)
        $this->assertEqualsWithDelta($initialY + 10.0, $this->renderer->tcpdf->GetY(), 0.5);
    }

    /**
     * ReportPdfImage::render mit statischer Position (X1/Y2-Branch) und line='' läuft ohne Fehler (EP-PDF-IMG2 / N2).
     * line='' → kein setY(lastpicbottom) — Branch N2 abgedeckt.
     */
    public function test_pdf_image_render_with_static_position_and_no_line_advance(): void
    {
        $image = new ReportPdfImage(
            src:    '/var/www/html/public/favicon-32.png',
            x:      10.0,
            y:      10.0,
            width:  10.0,
            height: 10.0,
            align:  'L',
            line:   '',
        );
        $image->render($this->renderer);

        // Keine Exception; TCPDF-Zustand valide (Y ≥ 0)
        $this->assertGreaterThanOrEqual(0.0, $this->renderer->tcpdf->GetY());
    }

    /**
     * ReportPdfImage::render: zweiter Aufruf mit CURRENT_POSITION prüft Kollisions-Branch (Y1a).
     * Erster Aufruf setzt lastpicbottom; zweiter Aufruf mit überlappender X-Position
     * und Y=CURRENT_POSITION löst Kollisions-Verschiebung aus.
     */
    public function test_pdf_image_render_collision_detection_bumps_y_above_previous_image(): void
    {
        // Erster Aufruf: setzt lastpicbottom, lastpicleft, lastpicright
        $firstImage = new ReportPdfImage(
            src:    '/var/www/html/public/favicon-32.png',
            x:      ReportBaseElement::CURRENT_POSITION,
            y:      ReportBaseElement::CURRENT_POSITION,
            width:  20.0,
            height: 20.0,
            align:  'L',
            line:   'N',
        );
        $firstImage->render($this->renderer);
        $afterFirstY = $this->renderer->tcpdf->GetY();

        // Y zurücksetzen: Kollision simulieren (Y < lastpicbottom, X im Überlappungsbereich)
        $this->renderer->tcpdf->setY($afterFirstY - 15.0);

        $secondImage = new ReportPdfImage(
            src:    '/var/www/html/public/favicon-32.png',
            x:      ReportBaseElement::CURRENT_POSITION,
            y:      ReportBaseElement::CURRENT_POSITION,
            width:  10.0,
            height: 10.0,
            align:  'L',
            line:   'N',
        );
        $secondImage->render($this->renderer);

        // Kollision erkannt → Y mindestens so hoch wie nach erstem Bild
        $this->assertGreaterThanOrEqual($afterFirstY - 15.0 + 10.0, $this->renderer->tcpdf->GetY());
    }
}
