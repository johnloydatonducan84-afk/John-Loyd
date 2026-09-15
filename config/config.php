<?php

// IMPORTANT: never paste real credentials into this file - it is public
// (committed to git). Real values belong ONLY in a separate, new file
// named config/local.php - never edit or paste anything into THIS file.
$config = [
    // Database (local XAMPP defaults)
    'db_host' => 'localhost',
    'db_name' => 'caresched_db',
    'db_user' => 'root',
    'db_pass' => '',

    // Application
    'base_url' => 'http://localhost/caresched',

    // Mail (use environment variables or edit carefully)
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-email-password',
        'from_email' => 'no-reply@caresched.local',
        'from_name' => 'CareSched'
    ]
];

/*
 * config/local.php is gitignored, so `git push` never overwrites or
 * deploys it - it only needs to be created once directly on each
 * server (e.g. via the host's File Manager) with that environment's
 * real values. This keeps live secrets (DB credentials, SMTP
 * password) out of the public repo while still letting the same
 * committed config.php work everywhere.
 */
$localConfigFile = __DIR__ . '/local.php';

if (is_file($localConfigFile)) {

    $localConfig = require $localConfigFile;

    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
