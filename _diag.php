<?php
header('Content-Type: text/plain');
echo "PHP_VERSION=" . PHP_VERSION . "\n";
echo "PHP_VERSION_ID=" . PHP_VERSION_ID . "\n";
echo "DOCUMENT_ROOT=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "SCRIPT_FILENAME=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";

$errors = [];
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "functions.php loaded OK\n";
    echo "APP_BASE_PATH=" . APP_BASE_PATH . "\n";
} catch (Throwable $e) {
    echo "functions.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/config/database.php';
    echo "database.php loaded OK\n";
} catch (Throwable $e) {
    echo "database.php ERROR: " . $e->getMessage() . "\n";
}
