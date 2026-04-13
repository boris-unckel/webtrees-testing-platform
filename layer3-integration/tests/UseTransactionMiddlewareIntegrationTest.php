<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Middleware\UseTransaction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UseTransaction-Middleware (M27).
 *
 * Prüft Transaction-Wrapping: Commit bei Erfolg, Rollback bei Exception.
 * Deadlock-Retry-Logik wird pragmatisch getestet (nur Erfolgs-/Exception-Pfad).
 *
 * @see docs/tds_conditions_ref.md M27
 * @covers \Fisharebest\Webtrees\Http\Middleware\UseTransaction
 */
class UseTransactionMiddlewareIntegrationTest extends MysqlTestCase
{
    private UseTransaction $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new UseTransaction();
    }

    /**
     * EP1/B1: Handler erfolgreich → Transaktion committed, Response zurückgegeben.
     */
    public function test_handler_success_commits_transaction(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Schreibe innerhalb der Transaktion in die DB
                DB::table('site_setting')->updateOrInsert(
                    ['setting_name' => 'test_transaction_m27'],
                    ['setting_value' => 'committed'],
                );

                return response('OK');
            }
        };

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertSame('OK', (string) $response->getBody());

        // Verifizieren, dass der Wert tatsächlich committed wurde
        $value = DB::table('site_setting')
            ->where('setting_name', '=', 'test_transaction_m27')
            ->value('setting_value');
        $this->assertSame('committed', $value);

        // Aufräumen
        DB::table('site_setting')
            ->where('setting_name', '=', 'test_transaction_m27')
            ->delete();
    }

    /**
     * EP2/B2: Handler wirft Exception → Transaktion rollback, Exception propagiert.
     */
    public function test_handler_exception_rolls_back_transaction(): void
    {
        // Vorher sicherstellen, dass der Wert nicht existiert
        DB::table('site_setting')
            ->where('setting_name', '=', 'test_rollback_m27')
            ->delete();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Schreibe innerhalb der Transaktion
                DB::table('site_setting')->insert([
                    'setting_name'  => 'test_rollback_m27',
                    'setting_value' => 'should-be-rolled-back',
                ]);

                throw new \RuntimeException('Test-Exception für Rollback');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test-Exception für Rollback');

        try {
            $this->middleware->process($this->createRequest(), $handler);
        } finally {
            // Verifizieren, dass der Wert NICHT in der DB steht (Rollback)
            $value = DB::table('site_setting')
                ->where('setting_name', '=', 'test_rollback_m27')
                ->value('setting_value');
            $this->assertNull($value);
        }
    }

    /**
     * EP1 Response-Verifikation: Die Middleware gibt die korrekte Response zurück.
     */
    public function test_response_returned_after_commit(): void
    {
        $expectedBody = 'Transaction-Test-Response-' . bin2hex(random_bytes(4));

        $handler = new class ($expectedBody) implements RequestHandlerInterface {
            public function __construct(private readonly string $body)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return response($this->body);
            }
        };

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertSame($expectedBody, (string) $response->getBody());
    }
}
