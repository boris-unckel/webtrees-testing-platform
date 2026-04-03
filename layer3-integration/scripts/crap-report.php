<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * CRAP-Score-Report — parst PHPUnit Clover XML und listet Methoden mit
 * hohem CRAP-Score (> 100) und 0% Coverage auf, absteigend nach CRAP.
 *
 * CRAP-Formel bei 0% Coverage: cx² + cx
 * Scope: CRAP > 100 entspricht cx ≥ 10
 *
 * Verwendung (im Container):
 *   php /tests/layer3-integration/scripts/crap-report.php <pfad-zur-coverage.xml>
 *
 * Über make crap-report wird die Datei automatisch übergeben.
 */

declare(strict_types=1);

$xmlPath = $argv[1] ?? null;

if ($xmlPath === null || !file_exists($xmlPath)) {
    fwrite(STDERR, "Verwendung: php crap-report.php <coverage.xml>\n");
    if ($xmlPath !== null) {
        fwrite(STDERR, "Fehler: Datei nicht gefunden: $xmlPath\n");
    }
    exit(1);
}

$doc = new DOMDocument();
if (!@$doc->load($xmlPath)) {
    fwrite(STDERR, "Fehler: XML konnte nicht geladen werden: $xmlPath\n");
    exit(1);
}

$xpath = new DOMXPath($doc);
$rows  = [];

// Alle <line type="method" count="0"> mit CRAP > 100
foreach ($xpath->query('//line[@type="method"][@count="0"]') as $line) {
    $crap = (float) $line->getAttribute('crap');
    if ($crap <= 100.0) {
        continue;
    }

    $fileNode   = $line->parentNode;
    $classNodes = $xpath->query('class', $fileNode);
    $className  = '';
    $namespace  = '';

    if ($classNodes->length > 0) {
        $classNode = $classNodes->item(0);
        $className = $classNode->getAttribute('name');
        $namespace = $classNode->getAttribute('namespace');
    }

    // Paket aus Namespace (Fisharebest\Webtrees\Foo\Bar => Foo/Bar)
    $package = '';
    $prefix  = 'Fisharebest\\Webtrees\\';
    if (str_starts_with($namespace, $prefix)) {
        $package = str_replace('\\', '/', substr($namespace, strlen($prefix)));
    } elseif ($namespace !== '') {
        $package = str_replace('\\', '/', $namespace);
    }

    $rows[] = [
        'crap'   => $crap,
        'pkg'    => $package,
        'class'  => $className,
        'method' => $line->getAttribute('name'),
        'cx'     => (int) $line->getAttribute('complexity'),
    ];
}

usort($rows, static fn(array $a, array $b): int => $b['crap'] <=> $a['crap']);

if ($rows === []) {
    echo "Keine Methoden mit CRAP > 100 und 0% Coverage gefunden.\n";
    exit(0);
}

// Spaltenbreiten berechnen
$wPkg    = max(5,  ...array_map(static fn($r) => mb_strlen($r['pkg']),    $rows));
$wClass  = max(6,  ...array_map(static fn($r) => mb_strlen($r['class']),  $rows));
$wMethod = max(7,  ...array_map(static fn($r) => mb_strlen($r['method']), $rows));

printf(
    "%-4s | %-10s | %-{$wPkg}s | %-{$wClass}s | %-{$wMethod}s | %4s\n",
    'Rang', 'CRAP', 'Paket', 'Klasse', 'Methode', 'cx'
);
echo str_repeat('-', 4 + 3 + 10 + 3 + $wPkg + 3 + $wClass + 3 + $wMethod + 3 + 4) . "\n";

foreach ($rows as $i => $row) {
    printf(
        "%-4d | %-10s | %-{$wPkg}s | %-{$wClass}s | %-{$wMethod}s | %4d\n",
        $i + 1,
        number_format($row['crap'], 0, ',', '.'),
        $row['pkg'],
        $row['class'],
        $row['method'],
        $row['cx']
    );
}

printf("\nGesamt: %d Methoden mit CRAP > 100 bei 0%% Coverage\n", count($rows));
