<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Bedrohungsmodell — OWASP × Webtrees-Domänen

**Teil von:** [tp_security-audit_spec.md](../tp_security-audit_spec.md)
**Vorangehend:** [01_scope_and_tracks.md](01_scope_and_tracks.md)

---

## 1 Webtrees-Angriffsdomänen

Webtrees hat sieben Angriffsdomänen, die jede für sich einen eigenen Satz an Input-Sinks, Output-Sinks und rolleabhängigen Angriffsflächen mitbringt. Die Triage-Pipeline (siehe [`04_triage_pipeline.md`](04_triage_pipeline.md)) ordnet jede triagierte Datei einer oder mehreren Domänen zu.

| Kürzel | Domäne | Kern-Artefakte |
|---|---|---|
| **D-AUTH** | Authentication & Session | `Services/UserService.php`, `Http/RequestHandlers/Login*.php`, `Http/RequestHandlers/Password*.php`, `Http/Middleware/UseSession.php`, `Http/Middleware/AuthLoggedIn.php`, `Auth::*` |
| **D-AUTHZ** | Authorization & Privacy-Model | `Individual::canShow/canShowName`, `Family::canShow*`, `GedcomRecord::canShow*`, `Services/RelationshipService.php`, RESN-Logik, `Http/Middleware/Auth{Member,Editor,Moderator,Manager,Administrator}.php`, `default_resn` |
| **D-GEDIO** | GEDCOM-Import/Export | `Services/GedcomImportService.php`, `Services/GedcomExportService.php`, `Http/RequestHandlers/GedcomLoad.php`, `Http/RequestHandlers/UploadGedcom*.php`, `Gedcom::registerTags()`, GEDCOM-Parser |
| **D-MEDIA** | Media-Upload & Media-Routes | `Http/RequestHandlers/MediaFile*.php`, `Services/MediaFileService.php`, `Factories/MediaFileFactory.php`, `.htaccess` in `data/media/`, Bildbibliotheken (`gd`, `exif`) |
| **D-SEARCH** | Search & Autocomplete | `Http/RequestHandlers/Search*.php`, `Services/SearchService.php`, `Http/RequestHandlers/AutoComplete*.php`, LIKE-Queries, Regex-Anchors |
| **D-WIZARD** | Installer-Wizard & Setup | `Http/RequestHandlers/SetupWizard*.php`, `Http/Middleware/UpdateDatabaseSchema.php`, `Http/Middleware/ReadConfigIni.php`, `config.ini.php`, `MigrationService::updateSchema()` |
| **D-MOD** | Module / Theme / Custom-Code | `Services/ModuleService.php`, `Module/AbstractModule.php`, `modules_v4/*`, Theme-Loader, Hook-System, PSR-15-Middlewares via Custom-Module |

Eine achte Querschnittsdomäne fasst Infrastruktur-Middlewares zusammen:

| Kürzel | Domäne | Kern-Artefakte |
|---|---|---|
| **D-INFRA** | HTTP-Infrastruktur | `Http/Middleware/BaseUrl.php`, `Http/Middleware/ClientIp.php`, `Http/Middleware/CheckCsrf.php`, `Http/Middleware/BadBotBlocker.php`, `Http/Middleware/ErrorHandler.php`, `Http/Middleware/HandleExceptions.php`, `Http/Middleware/UseSession.php` |

---

## 2 OWASP Top 10 × Domänen-Matrix

Für jede OWASP-Kategorie ist eine Kreuz-Zuordnung zu den webtrees-Domänen dokumentiert — mit konkreten Hypothesen-Seeds, die der Deep-Dive-Prompt direkt konsumieren kann (siehe [`07_prompts/prompt_02_whitebox_deep_dive.md`](07_prompts/prompt_02_whitebox_deep_dive.md)).

### A01:2021 — Broken Access Control

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-AUTHZ | `Individual::canShow()` prüft Privacy nur gegen die *aktuelle* Tree-Preference, nicht gegen den effektiven Access-Path bei Relationship-Privacy | Visitor/Member |
| D-AUTHZ | Route X erwartet `AuthMember`-Middleware, tatsächlich ist nur `AuthLoggedIn` davor | Visitor |
| D-AUTHZ | `tree_id`-Parameter in der URL referenziert einen Tree, auf den der Nutzer keine Rechte hat, Handler prüft nicht | Member/Editor |
| D-AUTHZ | `xref`-Parameter zeigt auf Record in einem anderen Tree als in der URL → Cross-Tree-Leak | Member |
| D-SEARCH | Suchergebnisse enthalten private Records, weil Filter nur nach Tree-ID, nicht nach Record-Privacy filtert | Visitor/Member |
| D-MEDIA | Media-Datei ist über die App-Route unter `tree_id=A`, `xref=FOO`, `filename=bar.jpg` anfordernbar, obwohl der Record zu einem anderen Tree gehört | Visitor/Member |
| D-GEDIO | Export enthält Records, die der User nicht lesen dürfte (Privacy-Model wird im Export-Pfad nicht angewandt) | Member/Editor |

### A02:2021 — Cryptographic Failures

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-AUTH | Passwort-Hashing nutzt veralteten Algorithmus, oder `password_verify` wird nach `==`-Vergleich umgangen | alle |
| D-AUTH | Session-IDs sind aus vorhersagbarem Random | Visitor |
| D-AUTH | Passwort-Reset-Token mit schwacher Entropie oder Timing-Side-Channel bei Vergleich | Visitor |
| D-INFRA | `config.ini.php`-Parsing verwendet `include` statt `parse_ini_file` → DB-Credentials via PHP-Konstanten exfiltrierbar | Admin |
| D-INFRA | `Session`-Cookie ohne `secure`/`httponly`/`samesite` | Visitor |

### A03:2021 — Injection

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-SEARCH | `SearchService` baut LIKE-Pattern mit String-Konkatenation → Second-Order-SQLi | Visitor/Member |
| D-SEARCH | Regex-Anchor im Soundex-Search wird nicht escaped → Regex-DoS oder Result-Manipulation | Visitor |
| D-GEDIO | GEDCOM-Import schreibt Roh-Record in DB via `DB::raw()` ohne Prepared Statement | Editor |
| D-AUTHZ | `tree_id` oder `xref` gelangen in Raw-SQL ohne PDO-Binding | Member |
| D-MEDIA | Dateiname aus GEDCOM-Media-Record wird in `file_put_contents($path)` ohne Normalisierung verwendet → Path-Traversal | Editor |
| D-WIZARD | Setup-Wizard leitet `dbhost`/`dbuser`/`dbpass` ungefiltert in `new PDO(...)` → DSN-Injection | Visitor (wenn Re-Run möglich) |
| D-MOD | Modul-Konfigurationsfelder landen in `eval`/`include`/`unserialize` | Admin |

### A04:2021 — Insecure Design

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-AUTHZ | Privacy-Model vertraut auf Caller-Context statt Defence-in-Depth bei Service-Ebene | Member |
| D-GEDIO | Importer zählt erfolgreiche Imports als "Trust-Promotion" für weitere Aktionen | Editor |
| D-WIZARD | Wizard-State wird in Session gehalten, nicht atomar committet → TOCTOU | Visitor |
| D-MOD | Module dürfen ungepruefte Middleware registrieren, die vor `AuthAdministrator` läuft | Admin-Install |

### A05:2021 — Security Misconfiguration

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-INFRA | `ErrorHandler`-Middleware leakt Stack-Traces bei unbekannten Exception-Typen | Visitor |
| D-INFRA | Debug-Modus-Flag in `config.ini.php` kann über einen User-kontrollierbaren Pfad gesetzt werden | Admin |
| D-WIZARD | Default-Konfiguration erlaubt mehrfachen Wizard-Aufruf bis `config.ini.php` atomar geschrieben ist | Visitor |
| D-INFRA | `X-Powered-By` bzw. `Server`-Header leakt PHP-/Apache-Version (zu SEC-HDR04 mitgeführt) | Visitor |
| D-MOD | Modul-Verzeichnis `modules_v4/` wird beim Boot durchsucht ohne Integritätsprüfung | Admin |

### A06:2021 — Vulnerable and Outdated Components

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-INFRA | `composer audit` im Container meldet CVE für eine Dependency — ist der betroffene Code-Pfad im Audit-Scope erreichbar? | je nach CVE |
| D-MEDIA | `gd`/`exif`/`libjpeg`/`libpng`/`libwebp` CVEs bei Thumbnail-Generierung aus User-Upload | Editor |
| D-GEDIO | XML-Parser-CVEs in einem als Dependency eingebundenen GEDCOM-X-Parser | Editor |

### A07:2021 — Identification and Authentication Failures

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-AUTH | Login-Rate-Limiting fehlt oder ist pro Session, nicht pro Account/IP | Visitor |
| D-AUTH | Passwort-Reset per Email-Link ohne Token-Binding an Session / IP / User-Agent | Visitor |
| D-AUTH | Session-Fixation: `session_regenerate_id()` fehlt nach Login | Visitor |
| D-AUTH | "Remember-me"-Cookie enthält rekonstruierbare Credentials | Visitor |
| D-AUTH | `AuthNotRobot`-Middleware hat Bypass über User-Agent-Spoofing | Visitor |

### A08:2021 — Software and Data Integrity Failures

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-GEDIO | GEDCOM-Import nimmt interne Referenzen (`@I123@`) ungeprüft entgegen und verknüpft sie → Injection von Referenzen in fremde Records | Editor |
| D-MOD | Modul-Update-Check lädt Remote-Metadaten ohne Signaturprüfung | Admin |
| D-MEDIA | Datei-Upload prüft nur MIME aus dem Client-Header, nicht Content-Magic-Bytes | Editor |
| D-INFRA | `unserialize()` irgendwo auf User-kontrollierten Daten (Cookie, Cache, Session) | Visitor (via Cookie) |

### A09:2021 — Security Logging and Monitoring Failures

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-AUTH | Login-Fehler werden nicht geloggt → Bruteforce nicht detektierbar | Visitor |
| D-AUTHZ | Privilege-Escalation-Versuche erzeugen keinen Audit-Log-Eintrag | alle |
| D-INFRA | `data/log.txt` ist HTTP-zugänglich (bestehend in SEC-H, aber hier ergänzend über App-Route-Leak prüfen) | Visitor |

Hinweis: A09 generiert selbst keine Exploit-Kette, ist aber Voraussetzung, um Findings post-mortem zu verifizieren. Der Audit-Bericht selbst ist das Logging-Asset für einen bestätigten Exploit.

### A10:2021 — Server-Side Request Forgery

| Domäne | Seed-Hypothese | Ziel-Rolle(n) |
|---|---|---|
| D-WIZARD | `baseurl`-Feld im Wizard wird im Re-Run-Szenario für weitere Requests genutzt → Probe an `http://169.254.169.254/` | Visitor (wenn Re-Run) |
| D-MOD | Modul-Update-URL per Admin-Konfiguration → SSRF ins interne Container-Netz (`http://otel-collector:4318`, `http://mysql:3306`) | Admin |
| D-MEDIA | Externe Media-URL im GEDCOM-Import wird beim Thumbnail-Bau gefetcht | Editor |
| D-AUTH | OAuth/Social-Login-Callback mit user-kontrollierter Discovery-URL | Visitor |

---

## 3 Datenflüsse — User-Input → SUT-Sinks

Die folgenden Flüsse sind während des Deep-Dive-Prompts explizit als Ankerpunkte zu verfolgen. Jeder Fluss liefert einen konkreten Hypothesen-Hebel.

### Fluss 1 — HTTP-Query/Body → Handler → Service → DB

```
HTTP Request
  → Router/Aura → $request
  → AuthX-Middleware (Track 1 Reachability-Gate)
  → RequestHandler::handle($request)
    → Validator::queryParams($request)->string('xref','')
    → $service->method($xref, $tree_id)
      → DB::table('individuals')->where('i_id', $xref)
        → PDO execute (OTel PDO-Instrumentation sichtbar)
```

Sink-Erkennung: `DB::raw`, String-Konkatenation vor `whereRaw`, manuelle `->select()->whereRaw(...)`-Mischungen, `$query->where('col', '=', $raw)` wo `$raw` unescaped User-Input ist.

### Fluss 2 — Datei-Upload → Media-Factory → Filesystem

```
multipart/form-data
  → UploadGedcom* / MediaFile*
    → $request->getUploadedFiles()
      → $uploaded->getClientFilename()         ← user-kontrolliert
      → $uploaded->getClientMediaType()        ← user-kontrolliert
      → $uploaded->moveTo($target_path)        ← $target_path aus GEDCOM oder Request
```

Sink-Erkennung: `moveTo` mit user-einflussbarem Pfad, fehlende `pathinfo($filename, PATHINFO_EXTENSION)`-Whitelist, fehlende Normalisierung von `..`/`/`/Null-Bytes.

### Fluss 3 — GEDCOM-Record → Parser → DB → Render

```
.ged Datei
  → GedcomImportService::importRecord($raw_gedcom)
    → Parse (zeilenbasiert, Tag-Hierarchie)
    → DB::insert / update
  → Später: GedcomRecord::facts() / ->value()
    → View-Template mit Raw-Ausgabe?  (XSS-Sink)
```

Sink-Erkennung: Template nutzt `{{ }}` (escaped) oder `{!! !!}` (raw)? Werden CONC/CONT-Zeilen kombiniert und dann raw ausgegeben?

### Fluss 4 — Session → Globale State → Cross-Tenant-Leak

```
Session-Variable: user_id, tree_id, accessLevel
  → `Auth::user()`, `Auth::accessLevel()`
    → Privacy-Checks in canShow()
      → Cache oder Registry-Eintrag, der Tree-spezifisch ist
```

Sink-Erkennung: Caches oder Registry-Einträge, die nicht Tree-Id-geschlüsselt sind → Bleed-Through zwischen Trees. `Registry::container()->set(...)` mit globalem Key statt Tree-Scope.

### Fluss 5 — Wizard-State → config.ini.php → Boot-Reihenfolge

```
POST /setup step=6
  → Config-Werte in /var/www/html/data/config.ini.php
  → Nächster Request → ReadConfigIni Middleware
    → include / parse → DB::connect
```

Sink-Erkennung: Wird der Wizard atomar abgeschlossen, oder ist ein halbfertiger `config.ini.php` möglich, den ein nachfolgender Request ausnutzt? Prüft der Wizard, ob die Datei bereits existiert, bevor er sie überschreibt?

### Fluss 6 — Modul-Boot → PSR-15-Middleware-Stack

```
ModuleService::bootModules()
  → foreach ($modules as $module)
    → if ($module instanceof MiddlewareInterface)
      → Registry::container()->set("middleware.$module", $module)
```

Sink-Erkennung: Reihenfolge der Middleware-Registrierung — kann ein Modul seine Middleware *vor* `AuthAdministrator` platzieren und so Requests vor der Auth-Prüfung umleiten?

---

## 4 Rolle-zu-Handler-Matrix (zu befüllen in T0)

Die Triage-Pipeline generiert eine automatisch aktualisierte Matrix, die jede Route (Handler-Klasse) gegen die sechs Rollen stellt und markiert, welche Rollen die Route erreichen können. Sie wird in `artifacts/security-audit/<run-id>/reachability-matrix.md` persistiert.

```
Rolle \ Handler                            | SearchGeneral | IndividualPage | GedcomLoad | … 
-------------------------------------------|---------------|----------------|------------|---
Visitor                                    | y (tree=pub)  | y              | n          | …
Member                                     | y             | y              | n          | …
Editor                                     | y             | y              | y          | …
Moderator                                  | y             | y              | n          | …
Manager                                    | y             | y              | y          | …
Admin                                      | y             | y              | y          | …
```

Diese Matrix ist **primärer Input** für den Deep-Dive — ein Handler mit Visitor-Zugang und Dangerous-Function-Count > 0 rutscht automatisch auf MAX-Impact.

---

## 5 Webtrees-spezifische Vertikal-Hypothesen

Ergänzend zur OWASP-Matrix einige webtrees-typische Vektoren, die nicht sauber in eine einzige OWASP-Kategorie passen:

| Nr. | Hypothese | Domäne | Rolle |
|---|---|---|---|
| V1 | Privacy-Model hat Default-Fallback auf `HIDE` nur bei `canShow`, aber nicht bei `canShowName` → Name leakt, Record nicht | D-AUTHZ | Visitor |
| V2 | `default_resn` greift nicht, wenn eine Root-Individual-RESN fehlt, aber ein Child-Fact gesetzt ist | D-AUTHZ | Visitor |
| V3 | Relationship-Privacy: Entferntverwandte werden anhand eines Graph-Scans berechnet, der bei großen Trees abbricht und DEFAULT=HIDE zu DEFAULT=SHOW ändert | D-AUTHZ | Member |
| V4 | GEDCOM-Export enthält `@I999@`-Records, die im Export-Pfad nicht gefiltert werden, obwohl `canShow` false ist | D-GEDIO | Editor |
| V5 | Autocomplete liefert Record-Titel, die `canShow` nicht respektieren | D-SEARCH | Visitor |
| V6 | `tree_id` in URL widerspricht dem Tree des `xref` → Handler lädt Record via `xref` aus anderem Tree, ohne Tree-Mismatch zu prüfen | D-AUTHZ | Member |
| V7 | Admin-Backup-Download bindet Dateipfad aus Query → Path-Traversal auf `/var/www/html/../../etc/passwd` | D-INFRA | Admin (nur wenn via CSRF triggerbar) |
| V8 | Modul-Install-Endpoint akzeptiert Zip-Slip in hochgeladener `.zip` → Schreibt in beliebige Container-Pfade | D-MOD | Admin (nur wenn Nicht-Admin-Trigger existiert) |
| V9 | Wizard-Schritt 2 schreibt `config.ini.php` unter Race mit Schritt 3 → Zwei Wizards parallel, einer gewinnt, der andere erbt fremde DB-Credentials | D-WIZARD | Visitor |
| V10 | `CheckCsrf`-Middleware akzeptiert fehlenden Token bei `GET`-Requests, die Seiteneffekte haben (State-Changing-GET) | D-INFRA | Visitor/Member |
| V11 | `BadBotBlocker`-Middleware darf vor `UseSession` laufen → prüft Robots-Status ohne User-Kontext, Bypass über Cookie-Tricks | D-INFRA | Visitor |
| V12 | `ClientIp`-Middleware vertraut auf `X-Forwarded-For` ohne Proxy-Whitelist → Logging-Spoofing, Rate-Limit-Bypass | D-INFRA | Visitor |

Diese Vertikalen sind der **erste Task-Seed-Satz** — die Triage-Pipeline legt für jede als Ausgangspunkt einen Task an, sofern der entsprechende Code nicht bereits durch eine andere Quelle getriggert wurde.

---

## 6 Querverweise

- [01_scope_and_tracks.md](01_scope_and_tracks.md) — Track-Definitionen und Rollen-Taxonomie
- [04_triage_pipeline.md](04_triage_pipeline.md) — Reachability-Matrix-Generierung in T0
- [07_prompts/prompt_02_whitebox_deep_dive.md](07_prompts/prompt_02_whitebox_deep_dive.md) — Konsumiert diese Matrix und die Vertikal-Hypothesen als Input
