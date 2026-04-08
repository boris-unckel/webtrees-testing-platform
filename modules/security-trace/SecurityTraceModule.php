<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace SecurityTrace;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * SecurityTraceModule — Whitebox-Laufzeit-Beobachter für den Security-Audit.
 *
 * Spec: docs/security-audit/05_security_trace_middleware.md
 *
 * Double-Guard:
 *   1. ENV `WEBTREES_SECURITY_TRACE=1` (global)
 *   2. Header `X-Audit-Probe: <probe-id>` (pro Request)
 *
 * Ohne beide Bedingungen macht process() genau einen getenv + if — Zero-Overhead.
 *
 * Aktiv schreibt das Modul ein JSON-Artefakt pro Probe nach
 * `/artifacts/security-trace/<task-id>/<iso-ts>.json` mit Request, Response,
 * Auth-Kontext, Exceptions und Middleware-eigener Laufzeit.
 */
class SecurityTraceModule extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface
{
    use ModuleCustomTrait;

    private const ARTIFACT_ROOT = '/artifacts/security-trace';
    private const EXCERPT_LIMIT = 512;

    /** Keys, deren Werte im Body/Form als redigiert gelten. */
    private const REDACT_KEYS = [
        'password',
        'password_confirm',
        'old_password',
        'dbpass',
        'api_key',
        'apikey',
        'secret',
        'token',
        'totp_code',
        'csrf_token',
    ];

    public function title(): string
    {
        return 'Security Trace';
    }

    public function description(): string
    {
        return 'Whitebox-Security-Trace-Middleware für den webtrees Audit-Lauf (nur aktiv mit WEBTREES_SECURITY_TRACE=1 und X-Audit-Probe-Header)';
    }

    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Guard 1: ENV-Check. Zero-Overhead-Pfad.
        if (getenv('WEBTREES_SECURITY_TRACE') !== '1') {
            return $handler->handle($request);
        }

        // Guard 2: Header-Check. Ohne Probe-Header passiert nichts.
        $probeId = $request->getHeaderLine('x-audit-probe');
        if ($probeId === '') {
            return $handler->handle($request);
        }

        $startedAt = microtime(true);
        $exceptions = [];
        $response   = null;

        try {
            $response = $handler->handle($request);
            return $response;
        } catch (Throwable $t) {
            $exceptions[] = [
                'class'          => get_class($t),
                'message'        => $this->truncate($t->getMessage()),
                'file'           => $this->shortFile($t->getFile()),
                'line'           => $t->getLine(),
                'trace_excerpt'  => $this->truncate($t->getTraceAsString()),
            ];
            throw $t;
        } finally {
            $durationUs = (int) ((microtime(true) - $startedAt) * 1_000_000);
            try {
                $this->writeArtifact(
                    probeId:    $probeId,
                    request:    $request,
                    response:   $response,
                    exceptions: $exceptions,
                    durationUs: $durationUs,
                );
            } catch (Throwable $writerErr) {
                // Niemals 5xx durch Tracing-Fehler. Nur error_log.
                @error_log(sprintf(
                    'SecurityTraceModule: artifact write failed for probe %s: %s',
                    $probeId,
                    $writerErr->getMessage(),
                ));
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $exceptions
     */
    private function writeArtifact(
        string $probeId,
        ServerRequestInterface $request,
        ?ResponseInterface $response,
        array $exceptions,
        int $durationUs,
    ): void {
        $taskId = $this->taskIdFromProbe($probeId);
        $dir    = self::ARTIFACT_ROOT . '/' . $taskId;

        if (!is_dir($dir)) {
            @mkdir($dir, 0o777, true);
        }

        $tsIso  = gmdate('Y-m-d\TH-i-s', (int) (microtime(true)));
        $tsUs   = (int) (microtime(true) * 1_000_000) % 1_000_000;
        $fname  = sprintf('%s-%06d.json', $tsIso, $tsUs);
        $path   = $dir . '/' . $fname;
        $tmp    = $path . '.tmp';

        $artifact = [
            '$schema'       => 'https://example.invalid/webtrees-security-trace.v1.json',
            'probe_id'      => $probeId,
            'task_id'       => $taskId,
            'iteration'     => $this->iterationFromProbe($probeId),
            'timestamp'     => gmdate('Y-m-d\TH:i:s\Z'),
            'duration_us'   => $durationUs,
            'request'       => $this->describeRequest($request),
            'matched_route' => $this->describeMatchedRoute($request),
            'auth_context'  => $this->describeAuth($request),
            'response'      => $this->describeResponse($response),
            'exceptions'    => $exceptions,
            'memory_peak'   => memory_get_peak_usage(true),
        ];

        $json = json_encode(
            $artifact,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        if ($json === false) {
            throw new \RuntimeException('json_encode failed');
        }

        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('fopen failed: ' . $tmp);
        }
        fwrite($fh, $json);
        fflush($fh);
        fclose($fh);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('rename failed: ' . $tmp . ' -> ' . $path);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function describeRequest(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (!is_array($parsed)) {
            $parsed = [];
        }
        $parsedRedacted = $this->redactArray($parsed);

        return [
            'method'              => $request->getMethod(),
            'uri'                 => (string) $request->getUri(),
            'path'                => $request->getUri()->getPath(),
            'query_params'        => $request->getQueryParams(),
            'parsed_body_keys'    => array_keys($parsed),
            'parsed_body_excerpt' => $this->truncate((string) json_encode($parsedRedacted, JSON_UNESCAPED_UNICODE)),
            'parsed_body_sha256'  => hash('sha256', (string) json_encode($parsedRedacted)),
            'headers'             => $this->redactHeaders($request->getHeaders()),
            'cookies_keys'        => array_keys($request->getCookieParams()),
            'remote_addr'         => $this->serverParam($request, 'REMOTE_ADDR'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function describeMatchedRoute(ServerRequestInterface $request): array
    {
        try {
            $route = Validator::attributes($request)->route();
            return [
                'handler_class' => $route->name ?? null,
                'route_name'    => $route->name ?? null,
            ];
        } catch (Throwable) {
            return ['handler_class' => null, 'route_name' => null];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function describeAuth(ServerRequestInterface $request): array
    {
        $userId   = null;
        $userName = null;
        $isAdmin  = null;

        try {
            $user     = Auth::user();
            $userId   = $user->id();
            $userName = $user->userName();
            $isAdmin  = Auth::isAdmin();
        } catch (Throwable) {
            // Guest oder Bootstrap noch nicht fertig — null lassen.
        }

        return [
            'user_id'    => $userId,
            'user_name'  => $userName,
            'is_admin'   => $isAdmin,
            'access_level_label' => $userId === null ? 'visitor' : ($isAdmin ? 'admin' : 'member'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function describeResponse(?ResponseInterface $response): array
    {
        if ($response === null) {
            return ['status' => null, 'reason' => null, 'headers' => [], 'body_length' => 0, 'body_excerpt' => null, 'body_sha256' => null];
        }
        $body = (string) $response->getBody();
        $response->getBody()->rewind();

        return [
            'status'       => $response->getStatusCode(),
            'reason'       => $response->getReasonPhrase(),
            'headers'      => $this->redactHeaders($response->getHeaders()),
            'body_length'  => strlen($body),
            'body_excerpt' => $this->truncate($body),
            'body_sha256'  => hash('sha256', $body),
        ];
    }

    /**
     * @param array<string,list<string>> $headers
     * @return array<string,list<string>>
     */
    private function redactHeaders(array $headers): array
    {
        $lowerSensitive = ['cookie', 'set-cookie', 'authorization', 'x-csrf-token', 'x-xsrf-token'];
        $out = [];
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $lowerSensitive, true)) {
                $joined = implode('', $values);
                $out[$name] = ['<redacted sha256:' . substr(hash('sha256', $joined), 0, 16) . '>'];
            } else {
                $out[$name] = $values;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactArray(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), self::REDACT_KEYS, true)) {
                $out[$k] = '<redacted>';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->redactArray($v);
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    private function truncate(string $s): string
    {
        if (strlen($s) <= self::EXCERPT_LIMIT) {
            return $s;
        }
        return substr($s, 0, self::EXCERPT_LIMIT) . '…';
    }

    private function shortFile(string $fullPath): string
    {
        $marker = '/var/www/html/';
        $idx = strpos($fullPath, $marker);
        if ($idx === false) {
            return $fullPath;
        }
        return substr($fullPath, $idx + strlen($marker));
    }

    private function serverParam(ServerRequestInterface $request, string $key): ?string
    {
        $server = $request->getServerParams();
        $v = $server[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    /**
     * Probe-IDs haben die Form `SEC-AUDIT-<NNN>-<suffix>` oder `SEC-AUDIT-<NNN>`.
     */
    private function taskIdFromProbe(string $probeId): string
    {
        if (preg_match('/^(SEC-AUDIT-\d+)/', $probeId, $m)) {
            return $m[1];
        }
        return 'unknown';
    }

    private function iterationFromProbe(string $probeId): ?int
    {
        if (preg_match('/-r(\d+)$/', $probeId, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
