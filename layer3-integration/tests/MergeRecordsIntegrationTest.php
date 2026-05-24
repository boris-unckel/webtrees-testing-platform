<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\GedcomRecordFactoryInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: Datensatz-Zusammenführung — P41.
 *
 * Tests:
 * - MergeRecordsPage GET mit zwei gültigen XREFs → 200
 * - MergeRecordsPage GET mit leeren XREFs → 200 (null-Records in View)
 * - MergeRecordsAction POST: zwei INDIs → 302 zu MergeFactsPage
 * - MergeRecordsAction Guard-Pfade (stub-basiert): record null, tag-mismatch, pending-deletion
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsAction
 * @see docs/tds_conditions_ref.md P41
 * @see docs/testquality_improve_P41.md
 */
class MergeRecordsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p41-merge', 'P41 Merge', self::DEMO_GED);
    }

    /**
     * EP1: MergeRecordsPage GET mit zwei gültigen INDI-XREFs → 200.
     */
    public function test_merge_records_page_returns_200_with_valid_xrefs(): void
    {
        $handler = new MergeRecordsPage();

        $request = $this->createRequest(
            query: [
                'xref1' => 'X1030',
                'xref2' => 'X1031',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: MergeRecordsPage GET mit leeren XREFs → 200 (null-Records, kein Fehler).
     * isXref() mit Default '' → make('', $tree) gibt null zurück → null-Felder in View.
     */
    public function test_merge_records_page_returns_200_with_empty_xrefs(): void
    {
        $handler = new MergeRecordsPage();

        $request = $this->createRequest(
            query: [],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: MergeRecordsAction POST mit zwei gültigen INDIs → 302 (zu MergeFactsPage).
     * Beide Records existieren, gleicher Typ → redirect zu MergeFactsPage.
     */
    public function test_merge_records_action_redirects_for_matching_records(): void
    {
        $handler = new MergeRecordsAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'xref1' => 'X1030',
                'xref2' => 'X1031',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Record-Factory liefert für beide XREFs null → Guard-Redirect (302) zurück zu MergeRecordsPage.
     *
     * @group ported-l2-doubles
     */
    public function test_merge_records_action_redirects_back_when_record1_is_null(): void
    {
        // Arrange
        $record_factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $record_factory
            ->expects($this->exactly(2))
            ->method('make')
            ->willReturn(null);

        Registry::gedcomRecordFactory($record_factory);

        $handler = new MergeRecordsAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['xref1' => 'X1', 'xref2' => 'X2'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Guard-Redirect zurück zu MergeRecordsPage
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Beide Records existieren, aber unterschiedliche tag (INDI vs FAM) → Guard-Redirect (302).
     *
     * @group ported-l2-doubles
     */
    public function test_merge_records_action_redirects_back_when_tags_differ(): void
    {
        // Arrange — Stub-Records mit unterschiedlichen Tags
        $record1 = self::createStub(GedcomRecord::class);
        $record1->method('xref')->willReturn('X1');
        $record1->method('tree')->willReturn($this->tree);
        $record1->method('tag')->willReturn('INDI');
        $record1->method('isPendingDeletion')->willReturn(false);
        $record1->method('canShow')->willReturn(true);

        $record2 = self::createStub(GedcomRecord::class);
        $record2->method('xref')->willReturn('X2');
        $record2->method('tree')->willReturn($this->tree);
        $record2->method('tag')->willReturn('FAM');
        $record2->method('isPendingDeletion')->willReturn(false);
        $record2->method('canShow')->willReturn(true);

        $record_factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $record_factory
            ->expects($this->exactly(2))
            ->method('make')
            ->willReturnCallback(static fn (string $xref) => match ($xref) {
                'X1'    => $record1,
                'X2'    => $record2,
                default => null,
            });

        Registry::gedcomRecordFactory($record_factory);

        $handler = new MergeRecordsAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['xref1' => 'X1', 'xref2' => 'X2'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Tag-Mismatch erzwingt Redirect
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Record1 ist pending deletion → Guard-Redirect (302) zurück zu MergeRecordsPage.
     *
     * @group ported-l2-doubles
     */
    public function test_merge_records_action_redirects_back_when_record_is_pending_deletion(): void
    {
        // Arrange — Record1 mit isPendingDeletion=true
        $record1 = self::createStub(GedcomRecord::class);
        $record1->method('xref')->willReturn('X1');
        $record1->method('tree')->willReturn($this->tree);
        $record1->method('tag')->willReturn('INDI');
        $record1->method('isPendingDeletion')->willReturn(true);
        $record1->method('canShow')->willReturn(true);

        $record2 = self::createStub(GedcomRecord::class);
        $record2->method('xref')->willReturn('X2');
        $record2->method('tree')->willReturn($this->tree);
        $record2->method('tag')->willReturn('INDI');
        $record2->method('isPendingDeletion')->willReturn(false);
        $record2->method('canShow')->willReturn(true);

        $record_factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $record_factory
            ->expects($this->exactly(2))
            ->method('make')
            ->willReturnCallback(static fn (string $xref) => match ($xref) {
                'X1'    => $record1,
                'X2'    => $record2,
                default => null,
            });

        Registry::gedcomRecordFactory($record_factory);

        $handler = new MergeRecordsAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['xref1' => 'X1', 'xref2' => 'X2'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Pending-Deletion erzwingt Redirect
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * MergeRecordsPage GET mit leeren XREFs und Factory-Mock (null-Records) → 200.
     *
     * Isolations-Variante zur bestehenden DB-basierten Variante: hier wird die
     * Record-Factory durch einen Mock ersetzt, der für beide XREFs null zurückgibt.
     *
     * @group ported-l2-doubles
     */
    public function test_merge_records_page_returns_200_with_factory_mock_no_records(): void
    {
        // Arrange — Factory liefert für beide XREFs null
        $record_factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $record_factory
            ->expects($this->exactly(2))
            ->method('make')
            ->willReturn(null);

        Registry::gedcomRecordFactory($record_factory);

        $handler = new MergeRecordsPage();
        $request = $this->createRequest(
            query: ['xref1' => '', 'xref2' => ''],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — View tolerant gegen null-Records
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * MergeRecordsPage GET mit zwei Individual-Stubs via Factory-Mock → 200.
     *
     * Isolations-Variante: Records werden über Stubs simuliert, statt aus
     * dem geladenen GEDCOM zu kommen. Verifiziert, dass der Handler korrekt
     * auf zwei via Factory bereitgestellte Domain-Objekte reagiert.
     *
     * @group ported-l2-doubles
     */
    public function test_merge_records_page_returns_200_with_factory_mock_individual_stubs(): void
    {
        // Arrange — zwei Individual-Stubs mit minimalem Verhalten
        $individual1 = self::createStub(Individual::class);
        $individual1->method('xref')->willReturn('I1');
        $individual1->method('tree')->willReturn($this->tree);
        $individual1->method('canShow')->willReturn(true);

        $individual2 = self::createStub(Individual::class);
        $individual2->method('xref')->willReturn('I2');
        $individual2->method('tree')->willReturn($this->tree);
        $individual2->method('canShow')->willReturn(true);

        $record_factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $record_factory
            ->expects($this->exactly(2))
            ->method('make')
            ->willReturnCallback(static fn (string $xref) => match ($xref) {
                'I1'    => $individual1,
                'I2'    => $individual2,
                default => null,
            });

        Registry::gedcomRecordFactory($record_factory);

        $handler = new MergeRecordsPage();
        $request = $this->createRequest(
            query: ['xref1' => 'I1', 'xref2' => 'I2'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
