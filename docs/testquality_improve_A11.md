<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A11: System & Upgrade

**Referenz:** A11 | **SUT:** `app/Http/RequestHandlers/UpgradeWizardPage.php`, `UpgradeWizardConfirm.php`, `CheckForNewVersionNow.php`, `Masquerade.php`, `BroadcastPage.php`, `BroadcastAction.php`, `EmailPreferencesPage.php`, `EmailPreferencesAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. Masquerade: POST, setzt Session-Variable 'masquerade' = User-ID, Admin-only. UpgradeWizardPage: GET, prüft aktuelle Version vs. Latest (Netzwerkzugriff). PhpInformation: GET → phpinfo() Output.

---

## SUT-Kernbefunde

### Masquerade (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | user_id nicht gefunden → HttpNotFoundException | Nein |
| B2 | Selbst-Masquerade → kein Auth::login(), response() 200 | Nein |
| B3 (Happy Path) | Anderer User → Auth::login($user) + Session::put('masquerade', '1') + response() 200 | Nein |

### UpgradeWizardPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Netzwerkzugriff zu GitHub-API → latestVersion() | Nein (Netzwerkzugriff) |

### BroadcastAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | POST → Nachricht an alle User senden | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Masquerade POST: User nicht gefunden | HttpNotFoundException |
| EP2 | Masquerade POST: Selbst-Masquerade | 200, Auth::id() unverändert |
| EP3 | Masquerade POST: Happy Path | 200, Auth::id() == anderer User, Session['masquerade']=='1' |
| EP4 | BroadcastPage GET | Smoke: 200 |
| EP5 | EmailPreferencesPage GET | Smoke: 200 |
| EP6 | EmailPreferencesAction POST | Smoke: 302 |

---

## Empfohlene Strategie

**Masquerade vollständig (ISTQB B, sicherheitsrelevant). UpgradeWizardPage: nur wenn Netzwerk verfügbar (tbd bei P1). Alle anderen: Smoke.** Neue Klasse `SystemAdminIntegrationTest extends MysqlTestCase`. Admin-Auth, zweiter User für Masquerade-EP3.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
