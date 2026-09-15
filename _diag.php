<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

echo "PHP_VERSION=" . PHP_VERSION . "\n";

$localConfigFile = __DIR__ . '/config/local.php';
echo "local.php exists=" . (is_file($localConfigFile) ? 'YES' : 'NO') . "\n";

if (is_file($localConfigFile)) {
    try {
        $lc = require $localConfigFile;
        echo "local.php is_array=" . (is_array($lc) ? 'YES' : 'NO') . "\n";
        if (is_array($lc)) {
            echo "local.php keys=" . implode(',', array_keys($lc)) . "\n";
            echo "local.php db_host=" . ($lc['db_host'] ?? '(unset)') . "\n";
            echo "local.php db_name=" . ($lc['db_name'] ?? '(unset)') . "\n";
            echo "local.php db_user=" . ($lc['db_user'] ?? '(unset)') . "\n";
            echo "local.php db_pass_length=" . strlen($lc['db_pass'] ?? '') . "\n";
        }
    } catch (Throwable $e) {
        echo "local.php PARSE/LOAD ERROR: " . $e->getMessage() . "\n";
    }
}

$rawConfig = file_get_contents(__DIR__ . '/config/config.php');
echo "config.php size=" . strlen($rawConfig) . " bytes, md5=" . md5($rawConfig) . "\n";
echo "--- RAW config.php content ---\n";
echo $rawConfig;
echo "\n--- END RAW ---\n\n";

try {
    $config = require __DIR__ . '/config/config.php';
    echo "config.php loaded OK\n";
    echo "effective db_host=" . $config['db_host'] . "\n";
    echo "effective db_name=" . $config['db_name'] . "\n";
    echo "effective db_user=" . $config['db_user'] . "\n";
} catch (Throwable $e) {
    echo "config.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/includes/functions.php';
    echo "functions.php loaded OK\n";
} catch (Throwable $e) {
    echo "functions.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/config/database.php';
    echo "database.php loaded OK (PDO connected)\n";

    try {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "users table count=" . $count . "\n";
    } catch (Throwable $e) {
        echo "users query ERROR: " . $e->getMessage() . "\n";
    }

} catch (Throwable $e) {
    echo "database.php ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Simulating patient/dashboard.php require chain ---\n";
try {
    ob_start();
    $_SESSION['user_id'] = 999999;
    $_SESSION['role'] = 'patient';
    require __DIR__ . '/includes/auth.php';
    echo "auth.php loaded OK\n";
} catch (Throwable $e) {
    echo "auth.php ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
