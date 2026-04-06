<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — U01: Validator (root-Paket)

**Referenz:** U01 | **SUT:** `app/Validator.php` (`Fisharebest\Webtrees\Validator`)
**Bestehender Test:** `ValidatorTest.php` (Layer 2 / upstream, substanziell)
**Übergreifende Konzepte:** → [wf_test-iteration_guide.md](wf_test-iteration_guide.md)

---

## Status quo

Der upstream `ValidatorTest.php` deckt alle öffentlichen Methoden auf Layer 2 (SQLite in-memory) ab — mit einer **kritischen Ausnahme**: die Methode `float()` hat in keinem Test eine Assertion (CRAP=12, count=0 in der Layer-3-Coverage).

Layer-3-Coverage-Bericht (2026-04-06):
- 21 Methoden, 9 abgedeckt (43%)
- 117 Statements, 91 abgedeckt (78%)
- `float()`: CRAP=12, 0 Hits → **Primäres Ziel**

Weitere Layer-3-Lücken (in Layer 2 bereits abgedeckt, aber nicht in Layer 3):
- `__construct`: UTF-8-Validierung (Key: line 64, Value: line 67) — count=0
- `integer()`: negativer String-Pfad (`'-42'` via `str_starts_with`) — lines 327–328, count=0
- `integer()`: throw-Zweig ohne Default — line 341, count=0
- `array()`: throw für Non-Array-Non-Null — line 279, count=0

---

## SUT-Kernbefunde

### `float()` — Vollständige Branch-Tabelle

| Branch | Bedingung | Layer-3-getestet? |
|---|---|---|
| numeric String | `is_numeric('3.14')` → `(float)` | Nein |
| numeric Integer-String | `is_numeric('42')` → `(float)` | Nein |
| non-numeric String | `is_numeric('abc')` = false → `$value = null` | Nein |
| fehlender Parameter | `$params[$key] ?? null` → null | Nein |
| Default null + kein Wert | `$value === null` → `HttpBadRequestException` | Nein |
| Default gesetzt + kein Wert | `$value ?? $default` → $default | Nein |

### `__construct` — UTF-8-Prüfung

| Branch | Bedingung | Layer-3-getestet? |
|---|---|---|
| encoding = UTF-8, Key ungültig | `preg_match('//u', $key) !== 1` → throw | Nein |
| encoding = UTF-8, Value ungültig | `preg_match('//u', $value) !== 1` → throw | Nein |
| encoding = ASCII (serverParams) | `if ($encoding === 'UTF-8')` wird übersprungen | Nein |

### `integer()` — Negative-String-Zweig

| Branch | Bedingung | Layer-3-getestet? |
|---|---|---|
| Positiver digit-String | `ctype_digit('42')` → `(int)` | Ja (count=19) |
| Negativer digit-String | `str_starts_with('-') && ctype_digit(substr(..., 1))` | **Nein** (count=0) |
| Ungültiger String | weder ctype_digit noch neg-String → null | Ja (count=15 via default-Pfad) |
| throw ohne Default | `$value === null` → throw | **Nein** (count=0) |

### `array()` — Non-Array-Guard

| Branch | Bedingung | Layer-3-getestet? |
|---|---|---|
| Array-Wert | `is_array($value)` → weiter | Ja |
| null | `$value === null` → weiter (gibt [] zurück) | Ja |
| Non-Array, non-null | `!is_array($value) && $value !== null` → throw | **Nein** (count=0) |

---

## Äquivalenzklassen (EP) — float()

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | `'3.14'` (float-String) | 3.14 |
| EP2 | `'42'` (integer-String) | 42.0 |
| EP3 | `42` (int-Typ, als Attribut) | 42.0 |
| EP4 | `'-1.5'` (negativer float-String) | -1.5 |
| EP5 | `'0'` (Null-Grenzwert) | 0.0 |
| EP-inv1 | `'abc'` ohne Default | HttpBadRequestException |
| EP-inv2 | `'abc'` mit Default 99.9 | 99.9 |
| EP-miss1 | fehlender Parameter ohne Default | HttpBadRequestException |
| EP-miss2 | fehlender Parameter mit Default 0.0 | 0.0 |

---

## Grenzwerte (BVA) — float()

| Grenzwert | Wert | Erwartung |
|---|---|---|
| BV1 | `'0'` / `'0.0'` | 0.0 (untere Grenze) |
| BV2 | `'-1.5'` | -1.5 (negative Partition) |

---

## Empfohlene Strategie

**ISTQB B (Spezifikationsbasiert)** für `float()` — vollständige EP-Matrix.  
**ISTQB C (Pragmatisch)** für die übrigen Layer-3-Lücken — gezielte Guard-Tests.

Neue Testklasse: `layer3-integration/tests/ValidatorIntegrationTest.php`  
Extends `MysqlTestCase` (Bootstrap für I18N + Gedcom-Tags notwendig, kein Tree/DB-Zugriff).  
Keine neuen Fixtures nötig — alle Tests arbeiten mit in-memory PSR-7-Requests.

---

## Testmethoden-Liste (P2 finalisiert)

| Nr | Methode | EP/BV | Abdeckt |
|---|---|---|---|
| 1 | `test_float_returns_float_from_valid_float_string` | EP1 | float() numeric String |
| 2 | `test_float_returns_float_from_integer_string` | EP2 | float() integer String |
| 3 | `test_float_returns_zero_from_zero_string` | EP5/BV1 | float() Null-Grenzwert |
| 4 | `test_float_returns_float_from_negative_string` | EP4/BV2 | float() negativ |
| 5 | `test_float_returns_float_from_int_typed_attribute` | EP3 | float() int-Typ via attrs |
| 6 | `test_float_throws_for_non_numeric_string_without_default` | EP-inv1 | float() throw |
| 7 | `test_float_returns_default_for_non_numeric_string` | EP-inv2 | float() default |
| 8 | `test_float_throws_for_missing_parameter_without_default` | EP-miss1 | float() throw missing |
| 9 | `test_float_returns_default_for_missing_parameter` | EP-miss2 | float() default missing |
| 10 | `test_query_params_throws_for_invalid_utf8_in_key` | — | __construct line 64 |
| 11 | `test_query_params_throws_for_invalid_utf8_in_value` | — | __construct line 67 |
| 12 | `test_server_params_allows_non_utf8_content` | — | __construct ASCII branch |
| 13 | `test_integer_returns_negative_int_from_negative_string` | — | integer() lines 327–328 |
| 14 | `test_integer_throws_for_non_numeric_string_without_default` | — | integer() line 341 |
| 15 | `test_array_throws_for_non_array_non_null_value` | — | array() line 279 |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT + upstream-Test + coverage.xml gelesen; Konzept mit Code konsistent |
| P2: Soll-Design | ✅ | EP/BVA-Matrix + 15 Testmethoden finalisiert |
| P3: Test-Coding | ✅ | `ValidatorIntegrationTest.php` geschrieben |
| P4: Ausführung + Fixing | ✅ | 15/15 grün (44 Assertions); Fix: withServerParams() nicht in Nyholm\Psr7 → createStub() |
| P5: Dokumentation | ✅ | U01 in tds_conditions_ref.md + tds_coverage_ref.md eingetragen |
