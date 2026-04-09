<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-002 — D2 Hypothesen

**Generated:** 2026-04-09
**Context-Datei:** `artifacts/security-audit/deepdive/002/context.md`

## Hypothesen-Ranking

| Rang | ID | Vektor | Ausnutzbarkeit | Mitigiert? | Status |
|---|---|---|---|---|---|
| 1 | **H1** | `.htm`-Upload → stored HTML injection / phishing via MediaFileDownload | high — trivial upload, trivial fetch | CSP `script-src none` blockt JS, aber HTML-Body wird gerendert | **probe** |
| 2 | **H2** | `.htaccess`-Upload → PHP-Handler-Aktivierung im Media-Verzeichnis | medium — braucht `AllowOverride` | Apache-Default `AllowOverride None` unter `data/` | **code-read, skip probe** |
| 3 | **H3** | Doppelextension `payload.php.jpg` → PHP-Execution via Apache MultiViews | low — braucht `Options +MultiViews` und PHP-via-Extension-Binding | UploadMediaAction hat dieselbe Lücke — kein SEC-AUDIT-002-Fix-Scope | **out-of-scope** |
| 4 | **H4** | Folder-Traversal `../` im `folder`-Parameter | low — Flysystem 3.x `WhitespacePathNormalizer` blockt `..` | Framework-Layer | **code-read, skip probe** |

## H1 — `.htm`-Upload → stored HTML injection / phishing

**Pfad:**
1. Editor POST `/create-media-object` mit `file_location=upload`, `folder=`, `new_file=evil.htm`, `file` = HTML-Body
2. `MediaFileService::uploadFile()` schreibt `evil.htm` in `data/media/<tree>/evil.htm`
3. CreateMediaObjectAction erstellt OBJE-Record mit `1 FILE evil.htm`
4. Visitor GET `/media-{slug}?xref=...&fact_id=...&disposition=inline`
5. `MediaFileDownload` → `ImageFactory::mediaFileResponse()` → `fileResponse()` → `imageResponse()`
6. Response: `Content-Type: text/html` + `Content-Security-Policy: script-src none;frame-src none`
7. Browser rendert HTML-Body, blockt aber JS-Execution

**Exploit-Ergebnis:**
- JS-Execution: **NEIN** (CSP blockt `<script>`, Inline-Event-Handler, `javascript:` URIs)
- HTML-Rendering: **JA** — `<form>`, `<a>`, `<img>`, `<meta http-equiv="refresh">`, CSS, Text werden gerendert
- Phishing: **JA** — Fake-Login-Page unter vertrautem Origin
- Cookie-Theft (JS-freie Variante via CSS-Exfiltration): **theoretisch möglich** aber extrem limitiert

**Einstufung:** stored HTML injection / stored phishing, nicht stored XSS (JS). Defense-in-depth: die Extension-Blocklist in UploadMediaAction beweist die Designabsicht, `.htm`-Uploads zu unterbinden.

**Probe-Entscheidung:** Code-Read genügt — der Datenfluss ist vollständig nachvollziehbar (keine dynamischen Guards, kein bedingter Pfad zwischen Upload und Serve). Formaler Layer 2 Test als D5 Regression, kein Layer 3 Probe nötig.

## H2 — `.htaccess`-Upload → PHP-Handler-Aktivierung

**Pfad:**
1. Editor POST `/create-media-object` mit `file_location=upload`, `new_file=.htaccess`, `file` = `AddHandler application/x-httpd-php .jpg`
2. Datei landet unter `data/media/<tree>/.htaccess`

**Blockaden:**
- webtrees' Root `.htaccess` setzt `AllowOverride None` für `data/` — Apache ignoriert `.htaccess`-Dateien im Media-Verzeichnis
- webtrees' `data/.htaccess` enthält `Require all denied` — direkter HTTP-Zugriff via Apache auf `data/` ist gesperrt
- Media-Serving läuft über PHP (MediaFileDownload), nicht über Apache-DirectAccess

**Einstufung:** kein exploitabler Pfad im Standardsetup. Blocklist ist trotzdem korrekt als Defense-in-depth (nicht-Standard-Apache-Konfiguration, Nginx, LiteSpeed, …).

**Probe-Entscheidung:** Kein Probe nötig, Code-Read + Apache-Config-Review genügt.

## H3 — Doppelextension `.php.jpg` → PHP-Execution

**Analyse:**
- UploadMediaAction's Regex `/(\.(php|…))$/i` ist trailing-anchored — `exploit.php.jpg` wird **nicht** geblockt (Endung `.jpg`, nicht `.php`)
- Diese Lücke existiert **identisch** in der bestehenden Referenzimplementation
- Apache MultiViews müsste aktiv sein (`Options +MultiViews`), was nicht webtrees-Default ist
- Scope-Entscheidung: Nicht SEC-AUDIT-002, da UploadMediaAction denselben blinden Fleck hat

**Probe-Entscheidung:** Out-of-scope für SEC-AUDIT-002. Notiz: ein separates Ticket für einen Content-Type-Check (nicht nur Extension-Check) wäre denkbar, liegt aber auf niedrigerem Risiko.

## H4 — Folder-Traversal `../` im `folder`-Parameter

**Analyse:**
- `$folder = Validator::parsedBody($request)->string('folder')` — kein Application-Level-Check
- `$tree->mediaFilesystem()` = `Registry::filesystem()->data($this->mediaFolder())` — Flysystem `LocalFilesystemAdapter` mit `WhitespacePathNormalizer`
- Flysystem 3.x `WhitespacePathNormalizer::normalizePath()` wirft `PathTraversalDetected` für Pfade mit `..`-Segmenten
- Damit ist Folder-Traversal **auf Framework-Layer geblockt**

**Probe-Entscheidung:** Kein Probe nötig. Flysystem schützt robust. Der `$folder`-Check in UploadMediaAction ist ein Application-Layer-Doppelcheck, dessen Fehlen in MediaFileService kein echtes Risk darstellt.

## Fix-Empfehlung (Vorschau für D6)

**Minimal-Diff:** Die beiden fehlenden Guards (Colon-Blocklist + Extension-Blocklist)
in den `case 'upload'`-Block von `MediaFileService::uploadFile()` einfügen —
**nach** Filename-Normalisierung, **vor** `writeStream`:

```php
// Block dangerous file extensions (mirroring UploadMediaAction safeguards).
if (preg_match('/(\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml))$/i', $file, $match)) {
    FlashMessages::addMessage(I18N::translate('Filenames are not allowed to have the extension "%s".', $match[1]));
    return '';
}
```

Optionale Erweiterung: auch den Colon-Check für Windows-Kompatibilität spiegeln.
Auf Folder-Allowlist verzichten (Flysystem-Guard genügt, und `uploadFile` unterstützt
legitimerweise neue Ordner, die noch nicht in `allMediaFolders` gelistet sind).

## Ergebnis D2

**Probe-Erfordernis:** Kein Container-Probe nötig. Code-Read bestätigt H1 vollständig
nachvollziehbar. D3 (formale Probe) überspringen, D5 (Regression) direkt als
Layer 2 Test entwerfen.
