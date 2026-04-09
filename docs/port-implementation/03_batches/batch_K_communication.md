<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch K — Kommunikation

**Priorität:** 5 (einfach, geringe Komplexität)
**Feature-IDs:** K01–K02

---

## Portierbare Tests

### Handler-Tests (Template 1 — Mock EmailService / MessageService)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `ContactPageTest.php` | `ContactPage` | `CaptchaService`, `MessageService` | `pending` | K01 |
| 2 | `ContactActionTest.php` | `ContactAction` | `CaptchaService`, `MessageService` | `pending` | K01 |
| 3 | `MessagePageTest.php` | `MessagePage` | `UserService` | `pending` | K02 |
| 4 | `MessageActionTest.php` | `MessageAction` | `MessageService`, `UserService` | `pending` | K02 |
| 5 | `MessageSelectPageTest.php` | `MessageSelectPage` | `UserService` | `pending` | K02 |
| 6 | `BroadcastActionTest.php` | `BroadcastAction` | `MessageService` | `pending` | K02 |

### Bestehende substanzielle Tests (Verbesserung in P2)

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `BroadcastPageTest.php` | 2 | Fehlende Negativ-Tests (kein Admin → AccessDenied) |

## Ausgeschlossen

Keine — alle Handler sind via Test Doubles portierbar.

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
ls tests/app/Http/RequestHandlers/*Contact*Test.php tests/app/Http/RequestHandlers/*Message*Test.php
ls tests/app/Http/RequestHandlers/*Broadcast*Test.php
```

## Statistik

- Portierbar: ~6
- Ausgeschlossen: 0
- Bereits substanziell: BroadcastPageTest (2 Methoden, Verbesserung)
