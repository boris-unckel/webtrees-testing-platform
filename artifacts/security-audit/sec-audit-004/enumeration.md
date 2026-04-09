<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-004 — SVG Serve-Path Enumeration

**Task-Typ:** Search-&-Enumerate (kein Exploit-Deep-Dive)
**Codebase-Stand:** `upstream/webtrees` @ upstream main
**Ausgeführt:** 2026-04-09

## Frage

Gibt es in webtrees Serve-Pfade, die SVG-Daten aus benutzerkontrollierten
Quellen an den Browser liefern, ohne durch `ImageFactory::imageResponse()`
zu laufen (und damit den SEC-AUDIT-001 XSS-Blocker zu umgehen)?

## Methodik

Code-Suche über `upstream/webtrees/app/`, `upstream/webtrees/modules_v4/`
und `upstream/webtrees/resources/views/`. Primäre Queries:

| Query | Treffer-Dateien | Relevanz |
|---|---|---|
| `image/svg\+xml` (in `app/`) | `app/Mime.php`, `app/Factories/ImageFactory.php`, `app/Http/Middleware/CompressResponse.php` | Nur ImageFactory ist ein Serve-Pfad |
| `svg` (in `app/Module/`, case-insensitive) | 0 Treffer | Keine SVG-spezifische Module-Logik |
| `svg` (in `resources/views/`) | 0 Treffer | Keine SVG-emittierenden Templates |
| `mediaFilesystem` (in `app/Http/RequestHandlers/`) | nur `EditMediaFileAction.php` (Write-Pfad, kein Serve) | |
| `return response\(` (in `app/Http/RequestHandlers/`, mit Content-Type) | 12 explizite `content-type` Treffer, 0 davon `image/svg+xml` | |
| `fileResponse\|thumbnailResponse\|imageResponse\|replacementImageResponse` | ausschließlich ImageFactory + 4 RequestHandler | Deckt genau den bekannten Pfad |

## Ergebnistabelle aller Medien-/Datei-Serve-Pfade

| # | Pfad | Eintritt (Route) | Senke (Response-Konstruktion) | Bytes-Quelle | Content-Type | Guard |
|---|---|---|---|---|---|---|
| 1 | `MediaFileDownload::handle` | `GET /media-{filename}` (visitor) | `$image_factory->mediaFileResponse($media_file, $watermark, $download)` | `$media_file->tree()->mediaFilesystem()->read()` via `fileResponse` | dynamisch (mime von Flysystem) | **ImageFactory::imageResponse()** — ✅ guard |
| 2 | `MediaFileThumbnail::handle` | `GET /media-thumbnail-{...}` (visitor) | `$image_factory->mediaFileThumbnailResponse(...)` | Intervention-re-encoded thumbnail | nur raster (Intervention rasterisiert) | **ImageFactory::imageResponse()** — ✅ guard (SVG kann Intervention gar nicht durchlaufen) |
| 3 | `AdminMediaFileDownload::handle` | `GET /admin/media-file-download` (admin) | `Registry::imageFactory()->fileResponse($filesystem, $path, false)` | Admin-uploaded / tree media | dynamisch | **ImageFactory::imageResponse()** — ✅ guard |
| 4 | `AdminMediaFileThumbnail::handle` | `GET /admin/media-file-thumbnail` (admin) | `Registry::imageFactory()->thumbnailResponse(...)` | Intervention-re-encoded | nur raster | **ImageFactory::imageResponse()** — ✅ guard |
| 5 | `GedcomExportService::export` | `DownloadGedcomFile` / `ExportGedcomPage` | `ZipArchive::addFile()` / `Filesystem::writeStream()` | Tree media files | `application/zip` (oder wrapper) | **nicht relevant** — SVG in ZIP wird vom Browser nicht aktiv gerendert |
| 6 | `ManageMediaData::getFileInfo` | `GET /admin/media-data` | nur `getimagesizefromstring($data_filesystem->read($file))` | Data filesystem | nicht serviert, nur geparst | **nicht relevant** — Bytes verlassen PHP nicht |
| 7 | `EditMediaFileAction::handle` | `POST /admin/media-file-edit` | `$filesystem->move($old, $new)` | — | — | **nicht relevant** — Rename/Write, kein Serve |
| 8 | `HtmlRenderer::createImage` | XML-Report `<Image file="..."/>` | `ReportHtmlImage` → `<img src="$file">` | Gedcom-referenzierte Datei | (latenter Render-Bug — siehe Observation) | Regex-Gate `/(jpg\|jpeg\|png\|gif)$/i` in `ReportParserGenerate` — **✅ SVG abgelehnt** |
| 9 | `HtmlRenderer::createImageFromObject` | XML-Report highlighted-image | `$src = 'data:mime;base64,...'` aus `Registry::imageFactory()->mediaFileThumbnail(...)` | Intervention-re-encoded | nur raster | **ImageFactory (Intervention)** — ✅ SVG kann nicht durchlaufen |
| 10 | `ReportParserGenerate` highlighted-image | XML-Report `<Highlight />` | `imagecreatefromstring($media_file->fileContents())` → `createImageFromObject` | GD-Parse | nur raster | **GD (raster-only)** — ✅ SVG wirft Fehler vor createImageFromObject |
| 11 | `StatisticsChartModule::chartCustomChart` | `POST /modules/statistics-chart/custom` | `response($statistics->chartDistribution(...))` | **Kein** Byte-Serve — returniert HTML-View aus `statistics/other/charts/geo`, Client-side JS rendert Chart | `text/html` (default) | **nicht relevant** — keine SVG-Bytes geserviert, Chart ist client-side |
| 12 | Module-Handler (`app/Module/*.php`) | verschieden | Grep `svg` / `image/svg` in `app/Module/` | — | — | **0 Treffer** — keine Module beliefern SVG-Content |
| 13 | `modules_v4/*` custom modules | — | Inhalt: `_none`, `otel_spans`, `security_trace` (+ README) | — | — | **Nur Test-Module** aus der Testing-Platform, keine Produktions-Custom-Module |

## Pfad-Kriterium „bypasses imageResponse()"

Kein Pfad erfüllt gleichzeitig:

- Quelle = benutzerkontrollierter SVG-Upload (Media-Filesystem / DB-Blob)
- Senke = HTTP-Response mit `image/svg+xml` (oder einem anderen vom Browser SVG-gerenderten Mime)
- Pfad = NICHT durch `ImageFactory::imageResponse()`

## Sekundär-Beobachtungen (nicht SEC-AUDIT-004 Scope)

### Latenter Render-Bug in `HtmlRenderer::createImage`

`app/Report/HtmlRenderer.php:234-248` baut eine `data:`-URL aus dem File-Inhalt
(`$src = 'data:' . $mime_type . ';base64,' . base64_encode($data);`), gibt aber
**nicht** `$src` an `new ReportHtmlImage(...)` weiter, sondern den File-Pfad:

```php
return new ReportHtmlImage($file, $x, $y, $w, $h, $align, $ln);
```

`ReportHtmlImage::render` emittiert dann `<img src="$file">` — also ein lokaler
Dateisystem-Pfad im HTML. Browser blocken `file://`-URLs aus Web-Contexten,
daher funktionieren Bilder im HTML-Report-Format (XML-Template mit `<Image file="..."/>`)
faktisch nicht. Dies ist ein **Render-Bug**, kein Security-Befund — SVG-XSS
kann auf diesem Weg ohnehin nicht passieren (Regex-Gate + Browser-Blockade).

Kein Follow-Up-Ticket aus SEC-AUDIT-004. Die Beobachtung ist für ein allgemeines
Qualitätsticket relevant, fällt aber außerhalb der Audit-Pipeline.

### Guard-Stand in `imageResponse()`

Der aktuelle Guard auf `volatile/upstream/webtrees` ist `str_contains($data, '<script')`
(`app/Factories/ImageFactory.php:274`). Der SEC-AUDIT-001-Fix
(`svgContainsActiveContent` DOM-Walker) ist im **authoritativen Fork** als
Branch `security-audit-001-svg-filter-hardening` gepflegt, aber nicht in die
volatile Scratch-Clone-`main` zurückgespiegelt. Dies ist für SEC-AUDIT-004
**nicht kritisch**: die Frage dieser Task ist „gibt es Pfade, die
`imageResponse()` umgehen", nicht „wie stark ist der Guard". Der fehlende
DOM-Walker im Scratch-Clone ist bekannt und wird beim nächsten
Upstream-Release automatisch gefixt (wenn der Fork-PR gemergt wird).

## Ergebnis

**`no_finding`** — nach SEC-AUDIT-001 und SEC-AUDIT-003 existiert kein weiterer
SVG-Serve-Pfad, der den `ImageFactory`-Guard umgeht. Keine Follow-Up-Tickets.

Dokumentationswert: Diese Enumeration belegt, dass `ImageFactory` der einzige
SVG-Response-Choke-Point ist, und liefert die Tabelle aller Medien-/Datei-
Serve-Pfade als Referenz für künftige Audits.
