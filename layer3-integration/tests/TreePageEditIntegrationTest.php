<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageEdit;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePageEdit-Handler.
 *
 * Deckt den Handler ab, der die TreePage-Block-Konfiguration zum Editieren
 * anzeigt. Der Handler delegiert an den HomePageService, ruft die main- und
 * side-Bloecke des Baums fuer den aktuellen Benutzer ab und liefert die
 * verfuegbaren Bloecke an das Edit-Formular.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageEdit
 */
class TreePageEditIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageEdit: Container-Resolution + handle() → 200 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-072): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf gegen
     * die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt (Auflöesbarkeit)
     * ist im 200-OK enthalten; zusaetzlich wird geprueft, dass das gerenderte
     * Edit-Formular zum erwarteten POST-Endpunkt (TreePageUpdate-Route) zeigt —
     * die dokumentierte Side-Effect-Postcondition des Handlers.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_edit_handles_request_via_container(): void
    {
        // Arrange: Tree fuer den Request-Kontext anlegen — der Handler liest
        // Validator::attributes($request)->tree() und rendert die Edit-Page.
        $this->tree = $this->treeService->create('tree-edit-l3sp072', 'Tree Edit L3SP-072');
        $admin      = $this->createAndLoginAdmin();

        $handler = Registry::container()->get(TreePageEdit::class);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'user' => $admin,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK — Handler ist real auflöesbar und liefert die Edit-Blocks-Page aus.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition: das gerenderte Edit-Formular bindet den konkreten Tree-Namen
        // in die Save-Route ein (webtrees nutzt Pretty-URLs, d. h. `/<tree>/manage-trees`
        // bzw. URL-encodiert `%2F<tree>%2Fmanage-trees`). Beweis, dass der Handler die
        // Route-Parameter aus dem Request-Attribut aufloesst und nicht nur eine
        // generische Seite liefert.
        $body         = (string) $response->getBody();
        $save_segment = '%2F' . rawurlencode($this->tree->name()) . '%2Ftree-page-update';
        self::assertStringContainsString(
            $save_segment,
            $body,
            'Edit-Formular sollte den TreePageUpdate-Endpunkt (tree-page-update) fuer den konkreten Baum referenzieren.',
        );
    }

    /**
     * TreePageEdit::handle liefert HTTP 200 und ruft am HomePageService die
     * main- und side-Bloecke sowie die verfuegbaren Bloecke fuer den Baum ab.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $tree       = $this->treeService->create('tree-edit', 'Tree Edit');
        $this->tree = $tree;

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('treeBlocks')->willReturn(new Collection());
        $home_page_service->method('availableTreeBlocks')->willReturn(new Collection());

        $handler = new TreePageEdit($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
