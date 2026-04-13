<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\UpdateDatabaseSchema;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UpdateDatabaseSchema-Middleware (M08).
 *
 * Prüft, dass die Middleware den MigrationService korrekt aufruft und
 * den Request anschließend an den nächsten Handler weitergibt.
 *
 * @see docs/tds_conditions_ref.md M08
 * @covers \Fisharebest\Webtrees\Http\Middleware\UpdateDatabaseSchema
 */
class UpdateDatabaseSchemaMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: Schema ist aktuell — updateSchema() wird aufgerufen (No-Op), Handler wird ausgeführt.
     */
    public function test_schema_current_migration_service_called_and_handler_invoked(): void
    {
        $expectedResponse = response('OK');

        $migrationService = $this->createMock(MigrationService::class);
        $migrationService->expects($this->once())
            ->method('updateSchema')
            ->with(
                '\Fisharebest\Webtrees\Schema',
                'WT_SCHEMA_VERSION',
                Webtrees::SCHEMA_VERSION,
            )
            ->willReturn(true);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($expectedResponse);

        $middleware = new UpdateDatabaseSchema($migrationService);
        $request = $this->createRequest();

        $response = $middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * EP2: MigrationService wird immer vor dem Handler aufgerufen — Reihenfolge-Verifikation.
     */
    public function test_migration_runs_before_handler(): void
    {
        $tracker = new \stdClass();
        $tracker->order = [];

        $migrationService = $this->createMock(MigrationService::class);
        $migrationService->expects($this->once())
            ->method('updateSchema')
            ->willReturnCallback(function () use ($tracker): bool {
                $tracker->order[] = 'migration';

                return true;
            });

        $handler = new class ($tracker) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->tracker->order[] = 'handler';

                return response('OK');
            }
        };

        $middleware = new UpdateDatabaseSchema($migrationService);
        $request = $this->createRequest();

        $middleware->process($request, $handler);

        $this->assertSame(['migration', 'handler'], $tracker->order);
    }
}
