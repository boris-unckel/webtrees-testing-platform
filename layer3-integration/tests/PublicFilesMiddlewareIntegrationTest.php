<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Middleware\PublicFiles;
use Psr\Http\Server\RequestHandlerInterface;

use function response;

/**
 * Komponentenintegrationstest: PublicFiles-Middleware.
 *
 * Prüft die Delegation an den inneren Handler für Pfade ausserhalb von
 * `/public/`, für Pfade mit Path-Traversal-Marker sowie für nicht
 * existierende `/public/*`-Dateien. Statische Auslieferung existierender
 * Public-Dateien ist nicht abgedeckt — Mime-Erkennung und Filesystem-Zugriff
 * sind nicht im Fokus dieser Integrationsstufe.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/PublicFilesTest.php
 * @group ported-l2-doubles
 * @covers \Fisharebest\Webtrees\Http\Middleware\PublicFiles
 */
class PublicFilesMiddlewareIntegrationTest extends MysqlTestCase
{
    private PublicFiles $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new PublicFiles();
    }

    /**
     * Klassenexistenz-Smoke.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/PublicFilesTest.php
     * @group ported-l2-doubles
     */
    public function test_class_exists(): void
    {
        self::assertTrue(class_exists(PublicFiles::class));
    }

    /**
     * Pfad ohne `/public/`-Präfix wird an den inneren Handler delegiert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/PublicFilesTest.php
     * @group ported-l2-doubles
     */
    public function test_non_public_path_delegates_to_handler(): void
    {
        // Arrange
        $request = $this->createRequest();
        $request = $request->withUri($request->getUri()->withPath('/some/page'));

        $innerHandler = $this->createMock(RequestHandlerInterface::class);
        $innerHandler->expects(self::once())
            ->method('handle')
            ->willReturn(response('OK', StatusCodeInterface::STATUS_OK));

        // Act
        $response = $this->middleware->process($request, $innerHandler);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Pfad mit Path-Traversal-Marker (`..`) wird an den inneren Handler delegiert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/PublicFilesTest.php
     * @group ported-l2-doubles
     */
    public function test_public_path_with_traversal_delegates_to_handler(): void
    {
        // Arrange
        $request = $this->createRequest();
        $request = $request->withUri($request->getUri()->withPath('/public/../etc/passwd'));

        $innerHandler = $this->createMock(RequestHandlerInterface::class);
        $innerHandler->expects(self::once())
            ->method('handle')
            ->willReturn(response('OK', StatusCodeInterface::STATUS_OK));

        // Act
        $response = $this->middleware->process($request, $innerHandler);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * `/public/*`-Pfad auf nicht existierende Datei wird an den Handler delegiert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/PublicFilesTest.php
     * @group ported-l2-doubles
     */
    public function test_public_path_file_not_found_delegates_to_handler(): void
    {
        // Arrange
        $request = $this->createRequest();
        $request = $request->withUri($request->getUri()->withPath('/public/nonexistent-file.js'));

        $innerHandler = $this->createMock(RequestHandlerInterface::class);
        $innerHandler->expects(self::once())
            ->method('handle')
            ->willReturn(response('Not Found', StatusCodeInterface::STATUS_NOT_FOUND));

        // Act
        $response = $this->middleware->process($request, $innerHandler);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }
}
