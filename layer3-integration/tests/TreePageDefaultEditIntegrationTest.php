<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultEdit;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePageDefaultEdit-Handler.
 *
 * Deckt den Admin-Handler ab, der die Default-Block-Konfiguration fuer
 * die TreePage anzeigt. Der Handler delegiert an den HomePageService,
 * pruegft die Existenz der Default-Bloecke und liefert die verfuegbaren
 * Bloecke (zwei Spalten + verfuegbare Liste) an das Edit-Formular.
 *
 * @see docs/tds_conditions_ref.md A17
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultEdit
 */
class TreePageDefaultEditIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageDefaultEdit: Container-Resolution + handle() → 200 mit realen Services.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE): ersetzt die ehemalige `class_exists`-Tautologie
     * durch einen vollstaendigen Request-Durchlauf gegen die real verdrahtete Klasse
     * aus dem DI-Container. Smoke-Aspekt (Auflösbarkeit) ist im 200-OK enthalten,
     * zusaetzlich wird die dokumentierte Side-Effect-Postcondition geprueft: nach
     * Aufruf von checkDefaultTreeBlocksExist() existieren in der block-Tabelle
     * Default-Block-Zeilen mit gedcom_id = -1.
     *
     * @group ported-l2-doubles
     */
    public function test_tree_page_default_edit_handles_request_via_container(): void
    {
        // Arrange: vor dem Lauf sicherstellen, dass keine Defaults existieren —
        // der Handler soll die Default-Block-Zeilen idempotent anlegen.
        DB::table('block')->where('gedcom_id', '=', -1)->delete();

        $handler = Registry::container()->get(TreePageDefaultEdit::class);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK — Handler ist real auflösbar und liefert die Edit-Blocks-Page aus.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition: HomePageService::checkDefaultTreeBlocksExist hat
        // Default-Block-Zeilen fuer den virtuellen Default-Tree (-1) angelegt.
        self::assertTrue(
            DB::table('block')->where('gedcom_id', '=', -1)->exists(),
            'Default-Block-Zeilen fuer gedcom_id = -1 wurden vom Handler nicht angelegt.',
        );
    }

    /**
     * TreePageDefaultEdit::handle liefert HTTP 200 und ruft am
     * HomePageService die Default-Block-Pruefung, beide Spalten und die
     * verfuegbaren Bloecke ab.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('checkDefaultTreeBlocksExist');
        $home_page_service->expects(self::exactly(2))
            ->method('treeBlocks')
            ->willReturn(new Collection());
        $home_page_service->expects(self::once())
            ->method('availableTreeBlocks')
            ->willReturn(new Collection());

        $handler = new TreePageDefaultEdit($home_page_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
