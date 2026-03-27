<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

// webtrees Autoloader
require '/var/www/html/vendor/autoload.php';

// Integrationstests Autoloader (einfaches Classmap)
spl_autoload_register(static function (string $class): void {
    $prefix = 'DombrinksBlagen\\WebtreesTests\\Integration\\';
    $baseDir = __DIR__ . '/tests/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
