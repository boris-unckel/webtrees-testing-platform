<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — K02: Benutzer-Nachrichten

**Referenz:** K02 | **Status:** 🚫 EXCLUDED — Teststufe 2 nicht anwendbar
**Übergreifende Konzepte:** → [testquality_improve_common2.md](testquality_improve_common2.md)

## Ausschlussgrund

`MessagePage`, `MessageAction` und `MessageSelect` sind aus demselben Grund wie K01 ausgeklammert: E-Mail-Versand nicht prüfbar. `MessageAction` ruft intern `$this->email_service->send()` auf (SMTP). Ohne funktionierenden Mailserver im Test-Stack ist der Happy Path nicht testbar und ein Test würde nur Guard-Pfade abdecken, die keinen eigenständigen Layer-3-Test rechtfertigen.

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | 🚫 | E-Mail-Versand nicht prüfbar — gleicher Grund wie K01 |
