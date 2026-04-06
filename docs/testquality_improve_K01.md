<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — K01: Kontaktformular

**Referenz:** K01 | **Status:** 🚫 EXCLUDED — Teststufe 2 nicht anwendbar
**Übergreifende Konzepte:** → [testquality_improve_common2.md](testquality_improve_common2.md)

## Ausschlussgrund

`ContactPage` (Seite) und `ContactAction` (Aktion) sind wegen SMTP-Abhängigkeit dauerhaft ausgeklammert. Die `ContactAction` ruft `$this->email_service->send()` auf — der EmailService verwendet SMTP, das im Test-Stack nicht konfiguriert ist. Ein Mock ist ohne Änderung am DI-Binding nicht möglich, da EmailService konkret injiziert wird. Die testbaren Guard-Pfade (leere Felder, ungültige E-Mail) sind nicht hinreichend komplex für einen eigenständigen Layer-3-Test.

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | 🚫 | SMTP-Abhängigkeit; kein Mailserver im Test-Stack |
