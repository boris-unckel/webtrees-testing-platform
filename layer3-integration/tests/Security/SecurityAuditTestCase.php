<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration\Security;

use DombrinksBlagen\WebtreesTests\Integration\MysqlTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Abstract Basis für Audit-Regressionstests.
 *
 * Spec: docs/security-audit/08_layer_integration.md §2
 *
 * Aktiviert die SecurityTraceMiddleware für die gesamte Dauer eines Testlaufs
 * (ENV `WEBTREES_SECURITY_TRACE=1`), liefert Helfer für Probe-Requests und
 * Trace-Artefakt-Assertions und räumt Artefakte in tearDown auf — damit
 * keine Cross-Test-Kontamination im `/artifacts/security-trace/`-Baum entsteht.
 *
 * Testklassen erben von dieser Klasse und folgen dem Naming:
 *   SecAudit<NNN>Test::test_h<n>_<name>()
 *
 * Eine Task = eine Testklasse. Eine Hypothese = eine Testmethode.
 */
abstract class SecurityAuditTestCase extends MysqlTestCase
{
    private const ARTIFACT_ROOT_CONTAINER = '/artifacts/security-trace';

    protected MiddlewareInterface $securityTrace;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('WEBTREES_SECURITY_TRACE=1');
        $_ENV['WEBTREES_SECURITY_TRACE']    = '1';
        $_SERVER['WEBTREES_SECURITY_TRACE'] = '1';

        $this->securityTrace = $this->loadSecurityTraceModule();
    }

    protected function tearDown(): void
    {
        $this->resetSecurityTraceArtifacts();

        putenv('WEBTREES_SECURITY_TRACE=');
        unset($_ENV['WEBTREES_SECURITY_TRACE'], $_SERVER['WEBTREES_SECURITY_TRACE']);

        parent::tearDown();
    }

    /**
     * Lädt die SecurityTraceModule direkt aus dem Bind-Mount.
     *
     * Der Custom-Modul-Loader von webtrees läuft erst, wenn eine echte Request-Pipeline
     * aktiv ist. Im Integrationstest-Prozess wird die Klasse hier manuell geladen.
     */
    private function loadSecurityTraceModule(): MiddlewareInterface
    {
        $path = '/var/www/html/modules_v4/security_trace/SecurityTraceModule.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf(
                'SecurityTraceModule nicht gefunden unter %s — prüfe Bind-Mount in compose.yaml',
                $path,
            ));
        }
        /** @psalm-suppress UnresolvableInclude */
        require_once $path;

        $fqcn = '\\SecurityTrace\\SecurityTraceModule';
        if (!class_exists($fqcn)) {
            throw new RuntimeException('Klasse SecurityTrace\\SecurityTraceModule nach require_once nicht verfügbar.');
        }

        /** @var MiddlewareInterface $instance */
        $instance = new $fqcn();
        return $instance;
    }

    /**
     * Schickt einen Probe-Request durch die SecurityTraceMiddleware und gibt die Response zurück.
     *
     * Der Request bekommt zusätzlich den `X-Audit-Probe`-Header gesetzt; dadurch schreibt
     * die Middleware ein Trace-Artefakt ins `/artifacts/security-trace/<task>/`-Verzeichnis.
     */
    protected function sendProbeRequest(
        ServerRequestInterface $request,
        string $hypothesisId,
        RequestHandlerInterface $targetHandler,
    ): ResponseInterface {
        $request = $request->withHeader('X-Audit-Probe', $hypothesisId);

        return $this->securityTrace->process($request, $targetHandler);
    }

    /**
     * Lädt eine JSON-Fixture aus fixtures/security/payloads/<name>.json.
     *
     * @return array<string,mixed>
     */
    protected function loadFixture(string $name): array
    {
        $path = '/fixtures/security/payloads/' . $name . '.json';
        if (!is_file($path)) {
            throw new RuntimeException("Fixture nicht gefunden: {$path}");
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Fixture nicht lesbar: {$path}");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException("Fixture ungültig (kein Objekt): {$path}");
        }
        return $data;
    }

    /**
     * Erwartet, dass die Response einen aktiven Block-Status hat (403/401/422 typisch).
     *
     * Explizit **kein** 500 — ein Crash ist keine Schutzmaßnahme.
     */
    protected function assertResponseBlocked(ResponseInterface $response, int $expectedStatus = 403): void
    {
        $actual = $response->getStatusCode();
        self::assertSame(
            $expectedStatus,
            $actual,
            sprintf('Erwartet Block-Status %d, erhalten %d', $expectedStatus, $actual),
        );
        self::assertLessThan(500, $actual, 'Status 5xx ist kein Block, sondern Crash.');
    }

    /**
     * Prüft, dass das Trace-Artefakt für die Hypothese den erwarteten Branch enthält.
     *
     * Für Pre-Fix-Reproduktion: bestätigt, dass der Exploit-Pfad durchlaufen wurde.
     */
    protected function assertTraceHit(string $hypothesisId, string $expectedBranch): void
    {
        $artifact = $this->latestArtifact($hypothesisId);
        self::assertNotNull($artifact, "Kein Trace-Artefakt für {$hypothesisId} gefunden.");

        $haystack = json_encode($artifact) ?: '';
        self::assertStringContainsString(
            $expectedBranch,
            $haystack,
            "Trace enthält erwarteten Branch {$expectedBranch} nicht.",
        );
    }

    /**
     * Gegen-Assertion: das Trace-Artefakt darf ein bestimmtes Feld NICHT enthalten.
     *
     * Für Post-Fix-Regression: der Sink wurde nicht mehr erreicht.
     */
    protected function assertTraceAbsent(string $hypothesisId, string $forbiddenField): void
    {
        $artifact = $this->latestArtifact($hypothesisId);
        if ($artifact === null) {
            // Kein Artefakt → Feld trivial abwesend.
            self::assertTrue(true);
            return;
        }

        $haystack = json_encode($artifact) ?: '';
        self::assertStringNotContainsString(
            $forbiddenField,
            $haystack,
            "Trace enthält Feld {$forbiddenField}, das nach Fix nicht mehr auftauchen darf.",
        );
    }

    /**
     * Stärkste Form: Die Middleware hat für diese Hypothese kein Artefakt geschrieben.
     *
     * Das bedeutet: Der Request wurde **vor** dem Eintritt in den Ziel-Handler durch
     * einen Schutz-Mechanismus abgefangen, sodass die Probe den Sink nie erreichte.
     */
    protected function assertNoSecurityTraceArtifact(string $hypothesisId): void
    {
        $files = $this->artifactFiles($hypothesisId);
        self::assertCount(
            0,
            $files,
            sprintf('Erwartet: keine Trace-Artefakte für %s, gefunden: %d', $hypothesisId, count($files)),
        );
    }

    /**
     * Räumt alle Trace-Artefakte auf, die zur aktuellen Task-ID gehören.
     *
     * Wird in tearDown() aufgerufen. Kein Cleanup anderer Tasks — Namespacing strict.
     */
    protected function resetSecurityTraceArtifacts(): void
    {
        $taskId = $this->taskIdForClass();
        $dir = self::ARTIFACT_ROOT_CONTAINER . '/' . $taskId;
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($dir . '/*.json.tmp') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * Leitet die Task-ID aus dem Klassen-Namen ab.
     *
     * SecAudit042Test → SEC-AUDIT-042
     */
    protected function taskIdForClass(): string
    {
        $shortName = (new \ReflectionClass(static::class))->getShortName();
        if (preg_match('/^SecAudit(\d+)Test$/', $shortName, $m)) {
            return 'SEC-AUDIT-' . str_pad($m[1], 3, '0', STR_PAD_LEFT);
        }
        throw new RuntimeException(sprintf(
            'Klassenname %s folgt nicht dem Muster SecAudit<NNN>Test — Task-ID nicht ableitbar.',
            $shortName,
        ));
    }

    /**
     * @return list<string>
     */
    private function artifactFiles(string $hypothesisId): array
    {
        $taskId = $this->taskIdForClass();
        $dir = self::ARTIFACT_ROOT_CONTAINER . '/' . $taskId;
        if (!is_dir($dir)) {
            return [];
        }
        $all = glob($dir . '/*.json') ?: [];
        $matching = [];
        foreach ($all as $f) {
            $content = file_get_contents($f);
            if ($content !== false && str_contains($content, $hypothesisId)) {
                $matching[] = $f;
            }
        }
        return $matching;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function latestArtifact(string $hypothesisId): ?array
    {
        $files = $this->artifactFiles($hypothesisId);
        if ($files === []) {
            return null;
        }
        sort($files);
        $latest = end($files);
        $content = file_get_contents($latest);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
