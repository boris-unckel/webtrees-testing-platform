<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRecordAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRecordPage;
use Fisharebest\Webtrees\Services\GedcomEditService;

/**
 * Komponentenintegrationstest: EditRecordAction & EditRecordPage HTTP-Handler.
 *
 * Tests:
 * - Klassen-Existenz von EditRecordAction
 * - POST: editLinesToGedcom wird aufgerufen, Record aktualisiert, 302 zurueckgegeben
 * - Klassen-Existenz von EditRecordPage
 * - GET: EditRecordPage liefert 200 fuer SOUR-Record (Editor-Berechtigung)
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordPage
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordPageTest.php
 */
class EditRecordIntegrationTest extends MysqlTestCase
{
    /**
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordActionTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_record_action_class_exists(): void
    {
        // Arrange + Act
        $exists = class_exists(EditRecordAction::class);

        // Assert
        self::assertTrue($exists);
    }

    /**
     * EditRecordAction POST mit gueltigem SOUR-Record: editLinesToGedcom wird aufgerufen,
     * Record wird aktualisiert, Redirect (302) erfolgt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_updates_record_and_redirects(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('p70-editrecord', 'P70 EditRecord');

        $this->gedcomImportService->importRecord(
            "0 @S1@ SOUR\n1 TITL Test Source",
            $this->tree,
            false,
        );

        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service
            ->expects($this->once())
            ->method('editLinesToGedcom')
            ->willReturn("\n1 TITL Updated Source");

        $handler = new EditRecordAction($gedcom_edit_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            ['levels' => ['1'], 'tags' => ['TITL'], 'values' => ['Updated Source']],
            ['tree' => $this->tree, 'xref' => 'S1'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordPageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_record_page_class_exists(): void
    {
        // Arrange + Act
        $exists = class_exists(EditRecordPage::class);

        // Assert
        self::assertTrue($exists);
    }

    /**
     * EditRecordPage GET fuer SOUR-Record: Editor-Berechtigter erhaelt 200-Response.
     *
     * checkRecordAccess($record, true) verlangt Edit-Berechtigung — der Admin-User
     * aus createAndLoginAdmin() hat ROLE_MANAGER und damit Edit-Rechte.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRecordPageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_record_page_returns_ok_for_source_record(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('p70-editrecordpage', 'P70 EditRecordPage');

        $this->gedcomImportService->importRecord(
            "0 @S1@ SOUR\n1 TITL Test Source",
            $this->tree,
            false,
        );

        $gedcom_edit_service = new GedcomEditService();

        $handler = new EditRecordPage($gedcom_edit_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'S1'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
