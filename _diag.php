<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

$localConfigFile = __DIR__ . '/config/local.php';
echo "local.php exists=" . (is_file($localConfigFile) ? 'YES' : 'NO') . "\n";

if (is_file($localConfigFile)) {
    try {
        $lc = require $localConfigFile;
        echo "local.php keys=" . (is_array($lc) ? implode(',', array_keys($lc)) : 'NOT AN ARRAY') . "\n";
    } catch (Throwable $e) {
        echo "local.php ERROR: " . $e->getMessage() . "\n";
    }
}

try {
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/config/database.php';
    echo "DB CONNECTED OK\n";
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "users table count=" . $count . "\n";
} catch (Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}
