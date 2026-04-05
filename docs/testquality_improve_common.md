<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — Übergreifende Konzepte

Dieses Dokument beschreibt die übergreifenden Methoden, Muster und Infrastrukturbausteine, die in den Einzeldokumenten `testquality_improve_<REFERENZ>.md` referenziert werden. Es bildet die gemeinsame Grundlage für die Aufwertung strukturbasierter CRAP-Analyse-Tests auf eine spezifikationsbasierte oder pragmatisch erweiterte Qualitätsstufe.

---

## 1. Was bedeutet „höherwertige Qualitätsstufe"?

Die aktuellen CRAP-Analyse-Tests sind **strukturbasiert** (ISTQB): Testziel-Auswahl via Code-Pfad-Coverage, Abbruchkriterium „kein Exception / kein HTTP 500". Das ist bewusst niedrig — es deckt nur den minimalen Happy Path ab.

Eine höhere Qualitätsstufe nach ISTQB bedeutet eines der folgenden:

| Stufe | Bezeichnung | Was wird geprüft |
|---|---|---|
| **Strukturbasiert erweitert** | Branch/MC-DC Coverage | Alle Entscheidungspunkte im SUT werden abgedeckt, nicht nur der Happy Path |
| **Spezifikationsbasiert (B)** | Äquivalenzklassen + Grenzwerte | Anforderungen werden aus dem Verhalten des SUT abgeleitet; EP- und BVA-Methodik |
| **Pragmatisch erweitert (C)** | Negative Pfade + Guards | Wichtige Fehlerfälle, Guards und Pre-/Postconditions werden explizit geprüft |

Die Einzeldokumente verwenden die Bezeichnungen **B** (Spezifikationsbasiert) und **C** (Pragmatisch), teilweise als Hybrid. Die Wahl ist fallabhängig.

**Faustregel:** Wenn ein SUT klare, ableitbare Invarianten hat (Validierungslogik, Zustandsmaschinen, definierte Enumerationen) → ISTQB B. Wenn das SUT primär HTTP-Handler-Koordination macht → Pragmatisch C (negative Guards).

---

## 2. Äquivalenzklassenanalyse (EP) — Methodik

**Ziel:** Eingaben in Klassen einteilen, bei denen der SUT identisches Verhalten zeigt. Pro Klasse reicht ein Testfall.

**Vorgehen:**

1. Alle Parameter/Attribute des SUT identifizieren.
2. Für jeden Parameter: gültige und ungültige Partitionen bestimmen.
3. Pro Partition: genau einen Testfall ableiten.

**Beispiel (TreeExport `--format`):**

| Partition | Wert | Erwartung |
|---|---|---|
| EP-valid-1 | `gedcom` | Export erfolgreich, .ged erzeugt |
| EP-valid-2 | `gedzip` | Export erfolgreich, .gdz erzeugt |
| EP-valid-3 | `zip` | Export erfolgreich, .zip erzeugt |
| EP-valid-4 | `zipmedia` | Export erfolgreich, .zip mit Media erzeugt |
| EP-invalid-1 | `xml` | FAILURE, Fehlermeldung |
| EP-invalid-2 | `GEDCOM` (Großschreibung) | FAILURE (case-sensitive) |
| EP-invalid-3 | `` (leer) | Fallback zu `gedcom` |

**In PHP PHPUnit:** `@dataProvider`-Annotation für EP-Matrizen (→ Abschnitt 7).

---

## 3. Grenzwertanalyse (BVA) — Methodik

**Ziel:** Grenzfälle an den Rändern von Partitionen testen (Fehler treten häufig an Grenzen auf).

**Kandidaten suchen bei:**
- Numerischen Bereichen: 0, 1, max-1, max, max+1
- String-Längen: leer, 1 Zeichen, max-Länge, max+1
- Aufzählungen: erster Wert, letzter Wert, ungültiger Wert
- Boolean-Flags: true/false, '0'/'1' als String
- Array-Längen: leer, 1 Element, viele Elemente

**Beispiel (StatisticsData `$limit`):**
- BV1: `$limit = 0` → leere Collection (Grenzfall unten)
- BV2: `$limit = 1` → genau 1 Ergebnis
- BV3: `$limit = PHP_INT_MAX` → alle Ergebnisse

---

## 4. Wiederkehrende Verbesserungsmuster

### 4.1 Negative Pfade und Guards

Fast alle Request-Handler und CLI-Commands haben **Guard-Clauses** am Anfang, die bei ungültigen Eingaben sofort abbrechen (Redirect, Exception, FAILURE). Diese sind derzeit nicht getestet.

**Muster:**
```php
// SUT hat Guard:
if ($record === null) {
    return redirect(MergeRecordsPage::class);
}
```

**Test:**
```php
public function test_handle_redirects_when_record1_not_found(): void {
    // Arrange: xref1 verweist auf nicht existierenden Record
    // Act: handle() aufrufen
    // Assert: Response ist 3xx-Redirect, kein 200
}
```

**Gilt für:** Alle Request-Handler (P30–P37, G25, G28) und alle CLI-Commands (P35, P36, G26).

### 4.2 Vor-/Nachbedingungen (Pre/Postconditions)

Strukturbasierte Tests prüfen nur den Response-Code. Spezifikationsbasierte Tests prüfen zusätzlich den **Datenbankzustand** vor und nach der Aktion.

**Muster:**
```php
public function test_delete_record_removes_from_database(): void {
    // Precondition: Record existiert in DB
    self::assertNotNull(Registry::individualFactory()->make('I1', $tree));

    // Action
    $response = $this->handler->handle($request);

    // Postcondition: Record nicht mehr in DB
    self::assertNull(Registry::individualFactory()->make('I1', $tree));
    self::assertSame(204, $response->getStatusCode());
}
```

**Gilt für:** P30, P31, P32, P33, P34, P35, P36, G25, G27, G28, S48.

### 4.3 Zustandsabhängige Tests

Manche SUT-Pfade setzen einen bestimmten DB-Zustand voraus (z.B. `pending_edits`, `keep_media`, `imported`-Flag). Diese Zustände müssen im `setUp()` oder in der Test-Methode explizit hergestellt werden.

**Muster:**
```php
// Zustand setzen:
DB::table('gedcom_chunk')->insert(['imported' => 0, ...]);
$tree->setPreference('keep_media', '1');
```

### 4.4 Kaskadenlöschung und Cross-Table-Integrität

Bei Lösch- und Merge-Operationen (P30, P32, P34) müssen mehrere Tabellen nach der Aktion geprüft werden. Das ist aufwändig, aber für spezifikationsbasierte Tests notwendig.

```php
// Nach DeleteRecord:
self::assertSame(0, DB::table('individuals')->where('xref', 'I1')->count());
self::assertSame(0, DB::table('link')->where('l_to', 'I1')->count());
```

---

## 5. Mock-Infrastruktur für externe Abhängigkeiten

### 5.1 DNS-Mocking (für SEC-BOT01)

**Problem:** `BadBotBlocker` ruft direkt `gethostbyaddr()` und `gethostbyname()` auf — kein Interface, kein DI.

**Option A: PHP Function Mocking** (ohne SUT-Änderung)

Das Paket `php-mock/php-mock-phpunit` ermöglicht das Überschreiben von Built-in-Funktionen im Namespace des SUT:

```php
use phpmock\phpunit\PHPMock;

class BadBotBlockerTest extends TestCase {
    use PHPMock;

    public function test_dns_reverse_lookup_failure(): void {
        $gethostbyaddr = $this->getFunctionMock(
            'Fisharebest\Webtrees\Http\Middleware',
            'gethostbyaddr'
        );
        $gethostbyaddr->expects($this->once())->willReturn(false);

        // ... test DNS-Fehlerpfad
    }
}
```

**Voraussetzung:** `php-mock/php-mock-phpunit` als dev-Dependency. Funktioniert nur im Namespace des SUT.

**Option B: DNS-Service als Interface extrahieren** (SUT-Änderung nötig)

Bessere langfristige Lösung: `DnsResolverInterface` einführen, in `BadBotBlocker` per DI injizieren. Dann ist der DNS-Resolver vollständig mockbar. Dies ist jedoch eine webtrees-Kernänderung — außerhalb des Scope dieses Projekts, solange kein Fork betrieben wird.

**Option C: Lokaler Mock-DNS-Server** (Test-Infrastruktur)

DNSMASQ o.ä. im Podman-Compose-Stack konfigurieren, der bestimmte DNS-Antworten simuliert. Aufwand hoch, aber isolierbar.

**Empfehlung:** Option A für schnelle Verbesserung; Option B als langfristige Ideal-Lösung nur wenn Webtrees geforkt wird.

### 5.2 Filesystem-Mocking (für G27, G28, S49)

Für Tests, die Dateisystem-Operationen prüfen (Upload, Medienverwaltung), bietet `vfsStream` eine In-Memory-Filesystem-Emulation:

```php
use org\bovigo\vfs\vfsStream;

protected function setUp(): void {
    $this->root = vfsStream::setup('media');
    // MediaFileService mit vfs-Pfad konfigurieren
}
```

**Voraussetzung:** `mikey179/vfsstream` als dev-Dependency.

### 5.3 HTTP-Mocking (für G27 URL-Upload)

Für `MediaFileService::uploadFromUrl()` mit externer URL können HTTP-Requests via `guzzlehttp/guzzle` Mock-Handler abgefangen werden:

```php
$mock = new MockHandler([
    new Response(200, [], 'fake-image-data'),
    new RequestException('Network Error', new Request('GET', 'test')),
]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
```

**Voraussetzung:** Webtrees verwendet intern Guzzle oder einen PSR-18 Client — prüfen, ob der DI-Container den HTTP-Client austauschbar macht.

### 5.4 WHOIS-Mocking (für SEC-BOT01)

`NetworkService::findIpRangesForAsn()` verwendet direkte Sockets. Für Tests kann der `NetworkService` gemockt werden, wenn `BadBotBlocker` ihn per DI bekommt (was er tut). Mockery oder PHPUnit-Mocks reichen:

```php
$networkService = $this->createMock(NetworkService::class);
$networkService->method('findIpRangesForAsn')->willReturn([]);
$blocker = new BadBotBlocker($networkService);
```

Dies funktioniert bereits **ohne** SUT-Änderung, weil `NetworkService` per Konstruktor injiziert wird.

---

## 6. Batch-Tests aufsplitten (Strategie für P30–P37)

Die aktuellen `RequestHandlerBatchA/B`-Testklassen bündeln mehrere Referenz-IDs. Für ISTQB-B-Tests ist eine Aufteilung nach SUT-Klasse sinnvoll.

**Empfohlene Aufteilung:**

| Aktuelle Datei | Aufzuspalten in | Referenz |
|---|---|---|
| `RequestHandlerBatchAIntegrationTest` | `GedcomRecordPageIntegrationTest` | P32 |
| | `DeleteRecordIntegrationTest` | P32 |
| | `TreePrivacyActionIntegrationTest` | P33 |
| | `HelpTextIntegrationTest` (→ bereits S50, bleibt dort) | S50 |
| `RequestHandlerBatchBIntegrationTest` | `MergeRecordsPageIntegrationTest` | P30 |
| | `MergeFactsPageIntegrationTest` | P30 |
| | `ChangeFamilyMembersActionIntegrationTest` | P31 |
| | `RenumberTreeActionIntegrationTest` | P34 |
| | `UserEditActionIntegrationTest` | P37 |
| `CliSettingsBatchIntegrationTest` | `SiteSettingCommandIntegrationTest` | P36 |
| | `TreeSettingCommandIntegrationTest` | P36 |
| | `UserSettingCommandIntegrationTest` | P36 |
| | `UserTreeSettingCommandIntegrationTest` | P36 |

**Hinweis:** Die bestehenden Batch-Dateien können zunächst bestehen bleiben und schrittweise durch spezifische Klassen ergänzt werden. Die Batch-Tests werden als Smoke-Tests behalten (niedrigere Qualitätsstufe, schneller Sanity-Check).

---

## 7. DataProvider-Muster für EP-Matrizen

Für parametrisierte EP-Tests in PHPUnit:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('formatProvider')]
public function test_export_format(string $format, string $expectedExtension): void {
    // ...
}

public static function formatProvider(): array {
    return [
        'gedcom'   => ['gedcom',   '.ged'],
        'gedzip'   => ['gedzip',   '.gdz'],
        'zip'      => ['zip',      '.zip'],
        'zipmedia' => ['zipmedia', '.zip'],
    ];
}
```

Für Kreuzprodukt-Tests (mehrere Parameter kombiniert):

```php
// Kombinationsmatrix: sex × age_dir × type
public static function statisticsQueryProvider(): array {
    $sexValues    = ['F', 'M', 'ALL'];
    $ageDirValues = ['ASC', 'DESC'];
    $typeValues   = ['full', 'age'];
    
    $cases = [];
    foreach ($sexValues as $sex) {
        foreach ($ageDirValues as $dir) {
            foreach ($typeValues as $type) {
                $cases["{$sex}-{$dir}-{$type}"] = [$sex, $dir, $type];
            }
        }
    }
    return $cases;
}
```

---

## 8. Aufwandskategorien

| Kategorie | Kriterien | Typische Maßnahme |
|---|---|---|
| **Niedrig** | Neue Testmethoden im bestehenden Test; kein neuer Setup-Aufwand; keine neuen Dependencies | 1–3 Testmethoden ergänzen |
| **Mittel** | Neue Test-Klasse nötig; Setup-Code erweitern; ggf. neue Fixtures | Neue Klasse + erweitertes Fixture |
| **Hoch** | Neue Dev-Dependencies; Test-Infrastruktur (VFS, Mock-DNS); SUT-Änderung wünschenswert | Neue Bibliothek evaluieren + integrieren |

---

## 9. Prioritäten-Roadmap

**Sofort umsetzbar (Niedrig/Mittel, hoher Erkenntnisgewinn):**

1. **G26 (TreeExport):** `--format`-EP-Matrix per DataProvider, `--tree` nicht gefunden
2. **G29 (GedcomEditService):** Vollständige EP-Matrix für `editLinesToGedcom`; bereits gute Basis
3. **P35 (UserEdit CLI):** Konflikt-Detection (--create + --delete); bereits gute Basis
4. **P36 (CLI Settings):** Idempotenz-Test (B12), Konflikt-Flags
5. **S42 (SearchGeneralPage):** Single-Result-Redirect-Pfad (komplett fehlend)
6. **SEC-BOT01 (BadBotBlocker):** Cookie-Heuristik, WordPress-Pfade, erweiterte UA-Sampling

**Mittelfristig (Mittel/Hoch, strukturell wichtig):**

7. **G25 (GedcomLoad):** keep_media-Zweig, BOM-Stripping, Header-Validierung
8. **P30 (MergeFacts):** Guard-Clauses (B1–B6), DB-Integrität nach Merge
9. **P31 (ChangeFamilyMembers):** Datumsbasierte Einfügungslogik
10. **P33 (TreePrivacyAction):** Array-Parallelitäts-Validierung, Deletion-Pfade
11. **S41 (StatisticsData):** Sex-/Sort-/Year-EP-Matrizen

**Langfristig (Hoch, externe Infrastruktur):**

12. **SEC-BOT01 DNS-Branches:** PHP Function Mocking oder DNS-Service-Interface
13. **G27 (MediaFileService URL-Upload):** HTTP-Mock für Netzwerkfehler
14. **S45 (Report Primitives):** render()-Output-Validierung für PDF/HTML

---

## 10. Nicht verbesserbar (dauerhaft ausgeklammert)

Die folgenden Zweige können mit vertretbarem Aufwand nicht auf eine höhere Qualitätsstufe angehoben werden, ohne externe Infrastruktur oder SUT-Änderungen:

| SUT | Zweig | Grund |
|---|---|---|
| `BadBotBlocker` | DNS Reverse+Forward-Lookup (Branchen ROBOT_REV_FWD_DNS) | Direct `gethostbyaddr()`/`gethostbyname()` calls; kein DI |
| `BadBotBlocker` | DNS Reverse-Only-Lookup (ROBOT_REV_ONLY_DNS) | Gleicher Grund |
| `GedcomLoad` | Race Condition / `$n===0` (Branch C1b) | Echte Parallelität im MySQL-Container nicht reproduzierbar |
| `TreeExport` | `stream_get_contents()` Fehler (Branch D1) | PHP-Ressource-Fehler kaum injizierbar ohne SUT-Änderung |
| `MediaFileService` | Dateirechte-Fehler | Filesystem-Permissions in Podman-Container kaum steuerbar |
| `RenumberTreeAction` | Timeout mid-import (B4) | TimeoutService muss mockbar sein; aktuell nicht per DI austauschbar |
