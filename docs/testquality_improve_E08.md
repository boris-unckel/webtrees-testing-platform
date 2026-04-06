<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E08: TomSelect & AutoComplete (Edit-Hilfs-APIs)

**Referenz:** E08 | **SUT:** `app/Http/RequestHandlers/TomSelectIndividual.php`, `TomSelectMediaObject.php`, `TomSelectSource.php`, `TomSelectRepository.php`, `TomSelectNote.php`, `TomSelectSharedNote.php`, `AutoCompleteCitation.php`, `AutoCompleteFolder.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

`AutoCompleteIntegrationTest` deckt `AutoCompletePlace`, `AutoCompleteSurname` ab. TomSelect-Handler und AutoCompleteCitation/AutoCompleteFolder sind nicht getestet.

---

## SUT-Kernbefunde

### TomSelectIndividual

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → JSON array von {value: xref, text: name} — XREF-Direktsuche | Nein |
| B2 | GET → Namenssuche via searchIndividualNames() | Nein |
| B3 | Keine Treffer → leeres JSON-Array | Nein |

### AutoCompleteFolder

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → JSON array von Ordnernamen aus data-filesystem | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | TomSelectIndividual GET: XREF-Direktsuche | 200, JSON, 1 Element, value = XREF |
| EP2 | TomSelectIndividual GET: Namenssuche | 200, JSON, ≥1 Elemente |
| EP3 | TomSelectIndividual GET: Kein Treffer | 200, JSON, leer |
| EP4 | TomSelectSource GET: Namenssuche | Smoke: 200, JSON-Array |
| EP5 | TomSelectRepository GET: Namenssuche | Smoke: 200, JSON-Array |
| EP6 | AutoCompleteFolder GET | 200, JSON-Array von Ordnernamen |
| EP7 | AutoCompleteCitation GET | Smoke: 200, JSON-Array |

---

## Empfohlene Strategie

**TomSelectIndividual vollständig (ISTQB B), alle anderen Smoke.** Neue Klasse `TomSelectIntegrationTest extends MysqlTestCase` oder `AutoCompleteIntegrationTest` erweitern. Fixtures: INDI + SOUR + REPO aus demo.ged-Import. DataProvider für Smoke der anderen Handler.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT gelesen: TomSelect JSON-Response, AutoCompleteFolder data-filesystem |
| P2: Soll-Design | ✅ | EP1–EP7: TomSelectIndividual vollständig, andere Smoke |
| P3: Test-Coding | ✅ | `TomSelectIntegrationTest` (5 Tests) |
| P4: Ausführung + Fixing | ✅ | 5/5 grün |
| P5: Big-Picture | ✅ | testing-bigpicture.md aktualisiert |
