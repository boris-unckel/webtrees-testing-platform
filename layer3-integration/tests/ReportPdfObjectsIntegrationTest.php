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
     * ReportPdfTextBox::render auf leerem TextBox mit newline=true pinnt
     * den Newline-Zweig (line 318-321 in ReportPdfTextBox::render):
     *   - tcpdf->setY($cY + $cH) — Y wird um die Höhe des Box vorgerückt
     *   - lastCellHeight = 0 — Newline-Zweig setzt den Cell-Footprint zurück
     * Ohne Elements bleibt $cH === $this->height (= 20.0); $cY === $this->top (= 0.0).
     *
     * Komplementär zu test_report_pdf_text_box_render_with_border_and_fill (newline=false),
     * der den Else-Zweig (line 313-316) pinnt: setXY + lastCellHeight=$cH.
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

        // Vorbedingung: lastCellHeight ist im setUp-Zustand 0.0.
        self::assertSame(0.0, $this->renderer->lastCellHeight);

        // render() schreibt direkt in TCPDF — kein Echo, kein ob_start nötig
        $textBox->render($this->renderer);

        // Postcondition 1: Newline-Zweig hat Y um height (20.0) vorgerückt (cY=top=0.0 → cY+cH=20.0).
        self::assertEqualsWithDelta(20.0, $this->renderer->tcpdf->GetY(), 0.001);
        // Postcondition 2: Newline-Zweig setzt lastCellHeight auf 0 (Reset für nachfolgende Elemente).
        self::assertSame(0.0, $this->renderer->lastCellHeight);
        // Postcondition 3: render() initialisiert largestFontHeight auf 0 (line 115).
        self::assertSame(0.0, $this->renderer->largestFontHeight);
    }

    /**
     * ReportPdfTextBox::render mit Rahmen, Hintergrundfarbe und newline=false pinnt
     * den Else-Zweig (line 313-316 in ReportPdfTextBox::render):
     *   - tcpdf->setXY($cX + $cW, $cY) — Y bleibt auf $cY (= top = 0.0), X wandert hinter die Box
     *   - lastCellHeight = $cH — der Else-Zweig speichert die Box-Höhe als Cell-Footprint
     * Ohne Elements ($cE === 0) bleibt $cH === $this->height (= 15.0).
     *
     * Komplementär zu test_report_pdf_text_box_render_empty_box (newline=true),
     * der den Newline-Zweig (line 318-321) pinnt: setY($cY+$cH) + lastCellHeight=0.
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

        // Vorbedingung: lastCellHeight ist im setUp-Zustand 0.0.
        self::assertSame(0.0, $this->renderer->lastCellHeight);

        $textBox->render($this->renderer);

        // Postcondition 1: Else-Zweig hält Y auf $cY (= top = 0.0) — kein Vorrücken um $cH.
        self::assertEqualsWithDelta(0.0, $this->renderer->tcpdf->GetY(), 0.001);
        // Postcondition 2: Else-Zweig setzt lastCellHeight auf $cH (= height = 15.0) — speichert Box-Footprint.
        self::assertEqualsWithDelta(15.0, $this->renderer->lastCellHeight, 0.001);
    }

    /**
     * ReportPdfTextBox::render mit pagecheck=true und reseth=true pinnt die
     * Kombination zweier branch-spezifischer Effekte (line 219-225 + line 305-310):
     *   - Pagecheck-Zweig (line 220-221): `$renderer->lastCellHeight = 0` wird
     *     explizit VOR dem Pagebreak-Check zurückgesetzt — unabhängig vom newline-Zweig.
     *   - Reseth-Zweig (line 305-306): `$cH = 0` nach dem Rendering der Elemente.
     *   - Newline-Zweig (line 316-321): `setY($cY + $cH)` — mit $cH=0 (reseth) und
     *     $cY=top=0.0 → Y bleibt auf 0.0 (kein Vorrücken trotz height=10.0).
     *
     * Komplementär zu L3SP-048 (newline=true, reseth=false): dort rückt setY um
     * $cH = $this->height = 20.0 vor. Hier zeigt reseth=true den Cell-Reset-Effekt:
     * setY(0+0)=0 statt setY(0+10)=10.
     *
     * Kein Seitenumbruch erwartet: height=10.0 passt auf die frische Seite (AddPage
     * im setUp), also bleibt der Seitenzähler bei 1.
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

        // Vorbedingung: frische Seite nach setUp, lastCellHeight=0.0
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        self::assertSame(0.0, $this->renderer->lastCellHeight);

        $textBox->render($this->renderer);

        // Postcondition 1: Kein Seitenumbruch — height=10.0 passt auf frische Seite.
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        // Postcondition 2: reseth=true + newline=true → setY($cY + 0) = setY(0.0).
        // Der Reseth-Zweig (line 306) setzt $cH=0, sodass der Newline-Zweig (line 319)
        // setY($cY + $cH) = setY(0.0 + 0.0) = setY(0.0) ausführt — kein Vorrücken um height.
        self::assertEqualsWithDelta(0.0, $this->renderer->tcpdf->GetY(), 0.001);
        // Postcondition 3: lastCellHeight=0 — sowohl Pagecheck-Reset (line 221) als auch
        // Newline-Reset (line 320) führen zum Wert 0 (komplementäre Reset-Pfade).
        self::assertSame(0.0, $this->renderer->lastCellHeight);
        // Postcondition 4: largestFontHeight wird in line 115 auf 0 initialisiert.
        self::assertSame(0.0, $this->renderer->largestFontHeight);
    }

    // --- ReportPdfCell (AP B-06, CRAP 342) ---

    /**
     * ReportPdfCell::render mit newline=1 und leerem Text pinnt den Newline-Zweig
     * (line 136-137 in ReportPdfCell::render):
     *   - `$this->newline >= 1` → `lastCellHeight = 0` (Reset für nachfolgende Zellen).
     * Der elseif-Zweig (line 138-140), der `lastCellHeight = max(prev, getLastH())`
     * speichert, wird bewusst NICHT ausgelöst — das ist Komplement L3SP-052.
     *
     * Weitere branch-spezifische Effekte:
     *   - top=0.0 (!= CURRENT_POSITION) → line 96 `tcpdf->setY(0.0)`.
     *   - left=0.0 (!= CURRENT_POSITION) → line 85 `cX = addMarginX(0.0)`.
     *   - Leerer Text → Pagebreak-Block (line 104-119) wird übersprungen, kein
     *     Seitenumbruch trotz height=10.0.
     *   - MultiCell mit newline=1 rückt Y um height (10.0) vor.
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

        // Vorbedingung: frische Seite nach setUp, lastCellHeight=0.0
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        self::assertSame(0.0, $this->renderer->lastCellHeight);

        $cell->render($this->renderer);

        // Postcondition 1: Newline-Zweig (line 137) setzt lastCellHeight = 0.
        // Gegenstück zu L3SP-052 (newline=0), wo der elseif-Zweig (line 138-140)
        // lastCellHeight = getLastH() setzt.
        self::assertSame(0.0, $this->renderer->lastCellHeight);
        // Postcondition 2: Kein Seitenumbruch — leerer Text überspringt den
        // Pagebreak-Block (line 104-119), height=10.0 passt auf die frische Seite.
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        // Postcondition 3: MultiCell mit newline=1 rückt Y um height vor
        // (setY($this->top=0.0) gefolgt von MultiCell mit cur_y_offset).
        self::assertEqualsWithDelta(10.0, $this->renderer->tcpdf->GetY(), 0.001);
    }

    /**
     * ReportPdfCell::render mit newline=0 und nicht-leerem Text pinnt den elseif-Zweig
     * (line 138-140 in ReportPdfCell::render):
     *   - `$this->newline >= 1` ist FALSE (newline=0) → erster Zweig (line 136-137) übersprungen
     *   - `$renderer->lastCellHeight < $renderer->tcpdf->getLastH()` ist TRUE (Vorzustand 0.0
     *     < getLastH() einer gerenderten Zelle) → `lastCellHeight = tcpdf->getLastH()`
     * Komplementär zu L3SP-051 (newline=1), der den Reset-Zweig (line 137) pinnt:
     * dort `lastCellHeight = 0`, hier `lastCellHeight = getLastH()`.
     *
     * Weitere branch-spezifische Effekte:
     *   - top=0.0 (!= CURRENT_POSITION) → line 96 `tcpdf->setY(0.0)`.
     *   - left=0.0 (!= CURRENT_POSITION) → line 85 `cX = addMarginX(0.0)`.
     *   - Nicht-leerer Text → Pagebreak-Block (line 104-119) wird durchlaufen
     *     und füllt getLastH() mit einer deterministischen positiven Höhe.
     *   - MultiCell mit newline=0 lässt Y auf $this->top stehen (Cursor wandert
     *     horizontal hinter die Zelle, nicht in die nächste Zeile).
     */
    public function test_report_pdf_cell_render_no_newline_pins_elseif_branch(): void
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
        // Text befüllen, damit der Pagebreak-Block (line 104-119) durchläuft
        // und MultiCell deterministisch getLastH() > 0 setzt — Voraussetzung
        // für den elseif-Zweig (line 138).
        $cell->addText('Zellinhalt');

        // Vorbedingung: frische Seite nach setUp, lastCellHeight=0.0
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        self::assertSame(0.0, $this->renderer->lastCellHeight);

        $cell->render($this->renderer);

        // Postcondition 1: elseif-Zweig (line 138-140) hat lastCellHeight auf
        // getLastH() der gerade gerenderten MultiCell gehoben — also > 0 und
        // exakt gleich dem TCPDF-internen Wert.
        $lastH = $this->renderer->tcpdf->getLastH();
        self::assertGreaterThan(0.0, $lastH);
        self::assertSame($lastH, $this->renderer->lastCellHeight);
        // Postcondition 2: Kein Seitenumbruch — Text passt auf frische Seite.
        self::assertSame(1, $this->renderer->tcpdf->getPage());
        // Postcondition 3: MultiCell mit newline=0 lässt Y auf $this->top
        // stehen (Cursor wandert horizontal, nicht in die nächste Zeile).
        // Komplement zu L3SP-051, wo newline=1 → GetY() um height vorrückt.
        self::assertEqualsWithDelta(0.0, $this->renderer->tcpdf->GetY(), 0.001);
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
