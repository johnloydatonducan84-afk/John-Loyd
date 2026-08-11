<?php
return [
    // Database
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
