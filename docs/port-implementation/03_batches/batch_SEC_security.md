<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch SEC — Sicherheit

**Priorität:** 1 (höchste — sicherheitsrelevante Handler)
**Feature-IDs:** SEC-H01–H06, SEC-D01–D02, SEC-C01–C03, SEC-M01–M03,
SEC-PUB01–04, SEC-W01, SEC-WZ01–04, SEC-HDR01–04, SEC-BOT01, SEC-UTL01

---

## Portierbare Tests

| # | Test-Datei | SUT-Klasse | Template | Dependencies | Status | Bemerkung |
|---|-----------|------------|----------|-------------|--------|-----------|
| 1 | `MediaFileDownloadTest.php` | `MediaFileDownload` | T3 | `MediaFileService`, `Registry::mediaFactory()` | `pending` | SEC-M01 |
| 2 | `MediaFileThumbnailTest.php` | `MediaFileThumbnail` | T3 | `MediaFileService`, `Registry::mediaFactory()` | `pending` | SEC-M02 |
| 3 | `PublicFilesTest.php` (ggf. Middleware) | `PublicFiles` | T2 | — | `pending` | SEC-PUB01–04 |
| 4 | `SetupTest.php` / `SetupWizardTest.php` | `SetupWizard*` | T1 | `ServerCheckService`, `MigrationService` | `pending` | SEC-W01, SEC-WZ01–04 |
| 5 | `PingTest.php` | `Ping` | T2 | — | `pending` | SEC-UTL01 (bereits substanziell, Verbesserung prüfen) |
| 6 | `RobotsTxtTest.php` | `RobotsTxt` | T2 | — | `pending` | SEC-UTL01 |
| 7 | `SiteRegistrationActionTest.php` | `SiteRegistrationAction` | T1 | `UserService` | `pending` | SEC-UTL01 |
| 8 | `LoginActionTest.php` | `LoginAction` | T1 | `UserService`, `AuthenticationService` | `pending` | SEC-UTL01 |
| 9 | `PasswordResetActionTest.php` | `PasswordResetAction` | T1 | `UserService` | `pending` | SEC-UTL01 |
| 10 | `VerifyEmailTest.php` | `VerifyEmail` | T1 | `UserService` | `pending` | SEC-UTL01 |

### Sonderkandidaten (Middleware, ggf. eigener Pfad)

| # | Test-Datei (vermutlich) | SUT | Template | Status | Bemerkung |
|---|------------------------|-----|----------|--------|-----------|
| 11 | Middleware-Tests für SecurityHeaders | `SecurityHeaders` | T2 | `pending` | SEC-HDR01–04, Mock Request+Response |
| 12 | Middleware-Tests für BadBotBlocker | `BadBotBlocker` | T2 | `pending` | SEC-BOT01, Mock Request mit User-Agent |

## Ausgeschlossen (Layer 3 / Layer 4)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| SEC-H01–H06 | .htaccess / HTTP-Zugriff | Deployment-Level, nicht unit-testbar |
| SEC-D01–D02 | data/index.php | Filesystem-Level |
| SEC-C01–C03 | Config Guards | Filesystem-Level |

## Discovery

```bash
# Alle Security-relevanten Handler-Stubs finden
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
grep -rl 'class_exists' tests/app/Http/RequestHandlers/ | \
  xargs grep -l 'Login\|Password\|Setup\|Verify\|Register\|Auth\|Security\|Robot\|Ping'
```

## Statistik

- Portierbar: ~12
- Ausgeschlossen: ~6 Feature-IDs (Deployment/Filesystem)
- Bereits substanziell: PingTest (3 Methoden), PasswordResetPageTest (2)
