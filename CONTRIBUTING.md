<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Mitwirken

Danke für dein Interesse an der webtrees-Testing-Platform. Diese Hinweise fassen die verbindlichen Konventionen zusammen (Details: `CLAUDE.md` und `docs/tp_conventions_spec.md`).

## Sprache

| Bereich | Locale |
|---|---|
| Dieses Repo (Doku, Kommentare, Commit-Messages) | de_DE |
| webtrees-Fork (Code, Tests, PHPDoc) | en_GB |

## Lizenz & Header

- Beiträge stehen unter **AGPL-3.0-or-later** (siehe `LICENSE.md`).
- Jede neue Quelldatei erhält in der ersten Zeile einen SPDX-Header `SPDX-License-Identifier: AGPL-3.0-or-later` (Kommentarsyntax je Dateityp — `//`, `#` oder `<!-- -->`).

## Commits

- Commits müssen **GPG-signiert** sein (`commit.gpgsign=true`).
- Commit-Messages auf Deutsch, im Stil der bestehenden Historie (`type(scope): Beschreibung`).

## Skripte

- Bash nach [Google Shell Style Guide](https://google.github.io/styleguide/shellguide.html): `#!/usr/bin/env bash`, `set -euo pipefail`, `lower_snake_case`-Funktionen, `[[ ]]`, Zwei-Space-Indent, `main "$@"`.
- Lokal verifizieren: `shellcheck <skript>`.
- **Kein Perl** — Textverarbeitung mit Bash-Bordmitteln bzw. `sed`.

## Tests ausführen

```bash
make up        # Stack starten (generiert Passwörter, klont Upstream)
make setup     # webtrees im Container einrichten
make test-all  # alle Stufen — oder gezielt: test-static/-unit/-integration/-e2e/-performance
make down
```

**Exklusive Ausführung:** Immer nur genau eine Teststufe und nur ein Lauf gleichzeitig — die Container teilen Zustand (MySQL, webtrees-Daten). Parallele Läufe erzeugen Race-Conditions.

## Vor dem Pull Request

- `make test-static` (PHPStan / PHPCS / Trivy) grün.
- Betroffene Teststufe(n) lokal grün.
- SPDX-Header und Sprache geprüft.
