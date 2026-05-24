<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage;
use Fisharebest\Webtrees\Http\RequestHandlers\PhpInformation;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDelete;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDownload;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SiteLogsService;

/**
 * Komponentenintegrationstest: Protokolle & Monitoring — A10.
 *
 * Tests:
 * - PendingChangesLogPage GET → 200
 * - SiteLogsDownload GET → 200 + text/csv
 * - PhpInformation GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDelete
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PhpInformation
 * @see docs/tds_conditions_ref.md A10
 * @see docs/testquality_improve_A10.md
 */
class LogsMonitoringIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a10-logs', 'A10 Logs', self::DEMO_GED);
    }

    /**
     * EP1: PendingChangesLogPage GET → 200.
     */
    public function test_pending_changes_log_page_returns_200(): void
    {
        $handler = new PendingChangesLogPage(
            $this->treeService,
            $this->userService,
        );

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: SiteLogsDownload GET → 200 + text/csv Content-Type.
     */
    public function test_site_logs_download_returns_csv(): void
    {
        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );

        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => '',
                'text'     => '',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/csv', $response->getHeaderLine('content-type'));
    }

    /**
     * EP3: SiteLogsDownload GET → Content-Disposition ist attachment mit Dateinamen.
     */
    public function test_site_logs_download_has_attachment_disposition(): void
    {
        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );

        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => '',
                'text'     => '',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
        self::assertStringContainsString('webtrees-logs.csv', $response->getHeaderLine('content-disposition'));
    }

    /**
     * EP4: PhpInformation GET → 200 + enthält phpinfo-Output.
     */
    public function test_php_information_returns_200(): void
    {
        $handler  = new PhpInformation();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP5: PhpInformation GET → 200 mit nicht-leerem phpinfo-Body.
     *
     * @group ported-l2-doubles
     */
    public function test_php_information_body_is_not_empty(): void
    {
        $handler  = new PhpInformation();
        $request  = $this->createRequest();

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * EP6: SiteLogsDelete::handle() liefert 204 No Content auch bei leerer
     * Treffermenge und lässt nicht passende Log-Einträge unangetastet.
     *
     * Komplementär zu EP7: dort wird der Treffer-Pfad gepinnt (Marker
     * verschwindet). Hier wird der Nicht-Treffer-Pfad gepinnt — die Filter-
     * Query darf keine fremden Einträge löschen, und die Antwort ist
     * weiterhin 204 (response() mit leerem Body).
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_delete_with_no_matches_returns_no_content_and_preserves_logs(): void
    {
        // Arrange: Fremder Log-Eintrag, der NICHT vom Filter erfasst werden darf.
        $foreign_marker = 'integration-test-foreign-marker L3SP-018 ' . uniqid('', true);
        \Fisharebest\Webtrees\DB::table('log')->insert([
            'log_type'    => 'config',
            'log_message' => $foreign_marker,
            'ip_address'  => '127.0.0.1',
            'user_id'     => null,
            'gedcom_id'   => null,
        ]);
        $before_foreign = \Fisharebest\Webtrees\DB::table('log')
            ->where('log_message', '=', $foreign_marker)
            ->count();
        self::assertSame(1, $before_foreign);

        $handler = new SiteLogsDelete(
            Registry::container()->get(SiteLogsService::class),
        );
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'error',
                'text'     => 'no-such-text-' . uniqid('', true),
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content und der fremde Eintrag ist unverändert vorhanden.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        $after_foreign = \Fisharebest\Webtrees\DB::table('log')
            ->where('log_message', '=', $foreign_marker)
            ->count();
        self::assertSame(1, $after_foreign);
    }

    /**
     * EP7: SiteLogsDelete::handle() liefert 204 No Content und entfernt
     * passende Log-Einträge.
     *
     * Anders als die L2-Vorlage mit Mocks/Stubs nutzt der L3-Test echte
     * Service-Instanz und MySQL-Backing-Store: SiteLogsService::logsQuery
     * baut die Delete-Query selbst, wir prüfen den Effekt am DB-State.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_delete_returns_no_content_and_removes_logs(): void
    {
        // Arrange: Service auflösen, einen synthetischen Log-Eintrag schreiben.
        $site_logs_service = Registry::container()->get(SiteLogsService::class);
        \Fisharebest\Webtrees\DB::table('log')->insert([
            'log_type'    => 'error',
            'log_message' => 'integration-test-marker SiteLogsDelete',
            'ip_address'  => '127.0.0.1',
            'user_id'     => null,
            'gedcom_id'   => null,
        ]);
        $before = \Fisharebest\Webtrees\DB::table('log')
            ->where('log_message', '=', 'integration-test-marker SiteLogsDelete')
            ->count();
        self::assertSame(1, $before);

        $handler = new SiteLogsDelete($site_logs_service);
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'error',
                'text'     => 'integration-test-marker SiteLogsDelete',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content und der Marker-Eintrag wurde gelöscht.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        $after = \Fisharebest\Webtrees\DB::table('log')
            ->where('log_message', '=', 'integration-test-marker SiteLogsDelete')
            ->count();
        self::assertSame(0, $after);
    }

    /**
     * EP8: SiteLogsDownload mappt NULL-Werte aus log.user_id und log.gedcom_id
     * im CSV-Body auf den Marker "<none>" (SiteLogsService::logsQuery
     * COALESCE-Property).
     *
     * Anders als die L2-Vorlage mit class_exists-Smoke pruefen wir hier den
     * realen LEFT-JOIN-Pfad gegen MySQL: ein Log-Eintrag ohne user_id und
     * ohne gedcom_id wird per Marker selektiv abgegriffen und im CSV-Body
     * muessen die beiden CSV-Felder den COALESCE-Defaultwert "<none>"
     * tragen.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_download_renders_none_marker_for_null_user_and_tree(): void
    {
        // Arrange: Marker-Log-Eintrag ohne user_id und ohne gedcom_id einfuegen.
        $marker = 'integration-test-marker SiteLogsDownload NullUserNullGedcom ' . uniqid('', true);
        \Fisharebest\Webtrees\DB::table('log')->insert([
            'log_type'    => 'config',
            'log_message' => $marker,
            'ip_address'  => '127.0.0.1',
            'user_id'     => null,
            'gedcom_id'   => null,
        ]);

        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'config',
                'text'     => $marker,
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK, Marker-Zeile enthaelt zwei "<none>"-Felder als CSV
        // (jeweils in Anfuehrungszeichen). Die CSV-Struktur ist:
        // "log_time","log_type","log_message","ip_address","user_name","gedcom_name"
        // Mit NULL-User und NULL-Gedcom muessen die letzten beiden Felder
        // "<none>" sein.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString($marker, $body);
        self::assertStringContainsString('"<none>","<none>"', $body);
    }

    /**
     * EP9: SiteLogsDownload::handle() liefert die zuvor geschriebenen
     * Log-Felder unverändert im CSV-Body.
     *
     * Anders als die L2-Vorlage mit Builder/Collection-Stubs nutzt der L3-Test
     * echte Service-Instanz und MySQL-Backing-Store: ein synthetischer
     * Log-Eintrag wird eingefügt und über den Marker im text-Filter selektiv
     * abgegriffen.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_download_body_contains_log_fields(): void
    {
        // Arrange: Marker-Log-Eintrag schreiben.
        $marker = 'integration-test-marker SiteLogsDownload BodyContent';
        \Fisharebest\Webtrees\DB::table('log')->insert([
            'log_type'    => 'config',
            'log_message' => $marker,
            'ip_address'  => '127.0.0.1',
            'user_id'     => null,
            'gedcom_id'   => null,
        ]);

        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'config',
                'text'     => $marker,
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK und Body enthält Log-Felder.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('config', $body);
        self::assertStringContainsString($marker, $body);
        self::assertStringContainsString('127.0.0.1', $body);
    }

    /**
     * EP10: SiteLogsDownload escaped Doppel-Anführungszeichen im Body als "".
     *
     * Anders als die L2-Vorlage mit Stubs nutzt der L3-Test einen echten
     * Log-Eintrag mit Anführungszeichen in log_message; der reale
     * Implementierungs-Pfad (str_replace) wird durchlaufen.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_download_escapes_double_quotes(): void
    {
        // Arrange: Marker-Log-Eintrag mit "quotes" schreiben.
        $marker = 'integration-test-marker SiteLogsDownload Message with "quotes"';
        \Fisharebest\Webtrees\DB::table('log')->insert([
            'log_type'    => 'config',
            'log_message' => $marker,
            'ip_address'  => '127.0.0.1',
            'user_id'     => null,
            'gedcom_id'   => null,
        ]);

        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'config',
                'text'     => 'SiteLogsDownload Message with',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Doppelte Anführungszeichen sind als "" verdoppelt.
        $body = (string) $response->getBody();
        self::assertStringContainsString('""quotes""', $body);
    }

    /**
     * EP11: SiteLogsDownload::handle() liefert für leere Treffermenge
     * 204 No Content (ResponseFactory mappt leeren Body automatisch von
     * 200 OK auf 204).
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_download_with_empty_result_returns_no_content(): void
    {
        // Arrange: Filter, der garantiert keine Logs trifft.
        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );
        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => 'config',
                'text'     => 'no-such-marker-exists-' . uniqid('', true),
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content (ResponseFactory verhalten bei leerem Body).
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP12: SiteLogsPage::handle() rendert das Admin-Filterformular für /admin/site-logs.
     *
     * Anders als die L2-Vorlage mit class_exists-Smoke prüft der L3-Test den
     * realen Render-Pfad: SiteLogsPage benutzt ViewResponseTrait und schaltet
     * das Layout auf 'layouts/administration'; die View 'admin/site-logs'
     * rendert ein POST-Formular mit name="logs" und sichtbaren Filterfeldern
     * für 'from', 'to' und 'type'. Diese Marker pinnen die Render-Property,
     * ohne EP13/EP14 (reine Statuscode-Assertions) zu duplizieren.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_page_renders_admin_filter_form(): void
    {
        // Arrange
        $handler = new SiteLogsPage(
            $this->treeService,
            $this->userService,
        );
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK, Body enthält Admin-Filterformular mit name="logs"
        // und die drei Eingabefelder from/to/type (Render-Property der View
        // 'admin/site-logs' unter dem 'layouts/administration'-Layout).
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('name="logs"', $body);
        self::assertStringContainsString('name="from"', $body);
        self::assertStringContainsString('name="to"', $body);
        self::assertStringContainsString('name="type"', $body);
    }

    /**
     * EP13: SiteLogsPage::handle() liefert 200 OK ohne Query-Filter.
     *
     * Anders als die L2-Vorlage mit createStub(TreeService) und createStub(UserService)
     * nutzt der L3-Test die echten Services aus der MysqlTestCase-Basis; der Render-Pfad
     * geht damit gegen MySQL und liefert die Admin-View für /admin/site-logs.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_page_returns_200(): void
    {
        // Arrange
        $handler = new SiteLogsPage(
            $this->treeService,
            $this->userService,
        );
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP14: SiteLogsPage::handle() liefert 200 OK auch mit gesetzten Query-Filtern.
     *
     * Anders als die L2-Vorlage mit Service-Stubs nutzt der L3-Test reale Services
     * und exerziert die Validator-Pfade von action/type/text/ip/username/tree gegen
     * MySQL durch.
     *
     * @group ported-l2-doubles
     */
    public function test_site_logs_page_with_query_filters_returns_200(): void
    {
        // Arrange
        $handler = new SiteLogsPage(
            $this->treeService,
            $this->userService,
        );
        $request = $this->createRequest(
            query: [
                'action'   => 'auth',
                'type'     => 'config',
                'text'     => 'search text',
                'ip'       => '127.0.0.1',
                'username' => 'test-admin',
                'tree'     => 'a10-logs',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
