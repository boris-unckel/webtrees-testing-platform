<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\LoginPage;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: LoginPage Request-Handler.
 *
 * Tests:
 * - Standard-Aufruf ohne Baum und ohne Anmeldung → 200 (Formular).
 * - Bereits angemeldeter Benutzer → 302 (Weiterleitung zur UserPage).
 * - Aufruf mit bereits gewähltem Baum → 200 (Formular).
 * - Kein Baum-Attribut, aber TreeService liefert einen Default → 302 (Self-Redirect).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LoginPage
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/LoginPageTest.php
 */
class LoginPageIntegrationTest extends MysqlTestCase
{
    /**
     * Ohne Baum-Attribut, ohne angemeldeten Benutzer und ohne verfügbaren
     * Default-Baum rendert der Handler das Anmeldeformular (200).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/LoginPageTest.php
     * @group ported-l2-doubles
     */
    public function test_login_page_renders_form_when_no_trees_and_guest(): void
    {
        // Arrange
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->method('all')->willReturn(new Collection());

        $handler = new LoginPage($tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Ist der Aufrufer bereits angemeldet (Request-Attribut `user` ist eine
     * `User`-Instanz), leitet der Handler zur UserPage weiter (302).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/LoginPageTest.php
     * @group ported-l2-doubles
     */
    public function test_login_page_redirects_when_user_already_logged_in(): void
    {
        // Arrange
        $tree_service = $this->createMock(TreeService::class);

        $user    = self::createStub(User::class);
        $handler = new LoginPage($tree_service);
        $request = $this->createRequest()->withAttribute('user', $user);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Mit gesetztem Baum-Attribut rendert der Handler das Anmeldeformular
     * direkt (200) — kein Default-Baum-Lookup, kein Self-Redirect.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/LoginPageTest.php
     * @group ported-l2-doubles
     */
    public function test_login_page_renders_form_when_tree_attribute_present(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('demo');

        $tree_service = $this->createMock(TreeService::class);

        $handler = new LoginPage($tree_service);
        $request = $this->createRequest()->withAttribute('tree', $tree);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Ohne Baum-Attribut liefert der TreeService einen Default-Baum; der
     * Handler erzeugt daraufhin einen Self-Redirect mit Baum-Parameter (302).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/LoginPageTest.php
     * @group ported-l2-doubles
     */
    public function test_login_page_redirects_to_default_tree_when_attribute_missing(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('default');

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->method('all')->willReturn(new Collection(['default' => $tree]));

        $handler = new LoginPage($tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect zur LoginPage mit Baum-Parameter.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
