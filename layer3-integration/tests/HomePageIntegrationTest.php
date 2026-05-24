<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: HomePage Request-Handler.
 *
 * Wenn keine Stammbaeume existieren und der Benutzer ein Gast ist, leitet der
 * Handler auf die Login-Seite weiter (HTTP 302).
 *
 * @see docs/tds_conditions_ref.md S40
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\HomePage
 */
class HomePageIntegrationTest extends MysqlTestCase
{
    /**
     * Keine Baeume vorhanden, Aufrufer ist Gast: Redirect zur Login-Seite.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_no_trees_guest_redirects_to_login(): void
    {
        // Arrange
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::atLeastOnce())
            ->method('all')
            ->willReturn(new Collection());

        $handler = new HomePage($tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
