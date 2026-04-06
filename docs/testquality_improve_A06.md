<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A06: Site-Präferenzen

**Referenz:** A06 | **SUT:** `app/Http/RequestHandlers/SitePreferencesPage.php`, `SitePreferencesAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. SitePreferencesPage: GET → site_setting-Tabelle, Admin-only. SitePreferencesAction: POST → site_setting schreiben, redirect.

---

## SUT-Kernbefunde

### SitePreferencesPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → Admin-View mit aktuellen Site-Einstellungen | Nein |

### SitePreferencesAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | INDEX_DIRECTORY ist beschreibbares Verzeichnis → Site::setPreference() | Nein |
| B2 | INDEX_DIRECTORY existiert nicht → FlashMessage 'danger' | Nein |
| B3 | INDEX_DIRECTORY nicht beschreibbar → FlashMessage 'danger' | Nein |
| B4 (Happy Path) | Alle Settings valide → setPreference() + redirect(ControlPanel) | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | SitePreferencesPage GET | 200, Admin-View |
| EP2 | SitePreferencesAction POST: valides beschreibbares Verzeichnis | 302 zu ControlPanel, Flash 'success' |
| EP3 | SitePreferencesAction POST: nicht-existierendes Verzeichnis | 302, Flash 'danger' |
| EP4 | SitePreferencesAction POST: nicht-beschreibbares Verzeichnis | 302, Flash 'danger' |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert).** Neue Klasse `SitePreferencesIntegrationTest extends MysqlTestCase`. Admin-Auth. Für EP2: Container-Pfad `/tmp` ist beschreibbar. Für EP3: `/nonexistent/path`. SitePreferences-Postcondition: `Site::getPreference('LANGUAGE')` == gesetzter Wert.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT gelesen: SitePreferencesAction — viele Pflichtfelder, DATA_DIRECTORY validiert |
| P2: Soll-Design | ✅ | EP1–EP4: GET + POST valid + POST saves LANGUAGE + POST invalid dir |
| P3: Test-Coding | ✅ | `SitePreferencesIntegrationTest` (4 Tests) |
| P4: Ausführung + Fixing | ✅ | 4/4 grün |
| P5: Big-Picture | ✅ | testing-bigpicture.md aktualisiert |
