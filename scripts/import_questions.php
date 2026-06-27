<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/import_questions.php <source-json>\n");
    exit(1);
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/Services/MigrationService.php';
require_once BASE_PATH . '/app/Services/TestService.php';

TestService::installIfNeeded();
echo 'Imported ' . TestService::importCatalog($argv[1]) . ' questions.' . PHP_EOL;
