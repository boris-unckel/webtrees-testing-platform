<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Sicherheitsrichtlinie

Dieses Repository ist **Test-Infrastruktur** für webtrees — kein Produktivsystem und kein Bestandteil einer webtrees-Installation.

## Schwachstellen in webtrees selbst

Sicherheitslücken im webtrees-Core oder in webtrees-Modulen gehören **nicht** hierher, sondern an den Upstream:

- webtrees: <https://github.com/fisharebest/webtrees> — siehe dortige Sicherheitsrichtlinie.

Befunde, die das hier enthaltene Audit-Framework (`docs/security-audit/`) im webtrees-Core aufgedeckt hat, wurden bereits an den Upstream gemeldet und in **[webtrees 2.2.6](https://github.com/fisharebest/webtrees/releases/tag/2.2.6)** (2026-04-29) adressiert. Sie unterliegen **keinem Embargo** mehr; die Zuordnung der Upstream-Commits steht in `docs/security-audit/10_fixing_and_disclosure.md`.

## Probleme an dieser Test-Plattform

Für Sicherheitsprobleme der Test-Infrastruktur selbst (Skripte, Container-Konfiguration, Compose-Stack) bitte ein GitHub-Issue eröffnen. Der Stack läuft ausschließlich lokal bzw. in CI mit generierten Wegwerf-Credentials; sensible Meldungen können vorab privat an den Maintainer gerichtet werden.

## Credentials

Alle Passwörter (MySQL, webtrees-Admin, Test-User) werden lokal generiert (`scripts/generate-passwords.sh`) und liegen ausschließlich in der nicht versionierten `.env` (siehe `.gitignore`). Das Repository enthält keine echten Zugangsdaten — nur `.env.example` mit Platzhaltern.
