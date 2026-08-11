<?php
// Run this once to create a sample admin user for development/testing.
require_once __DIR__ . '/config/database.php';

$adminUsername = 'admin';
$adminEmail = 'admin@caresched.test';
$adminPassword = 'admin123';

try {
    $pdo->beginTransaction();
    // create user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $adminEmail]);
    if ($stmt->fetch()) {
        echo "Admin user already exists.\n";
        exit;
    }

    $password_hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (role, username, email, password, status, created_at) VALUES (:role, :username, :email, :password, :status, NOW())');
    $stmt->execute([
        'role' => 'admin',
        'username' => $adminUsername,
        'email' => $adminEmail,
        'password' => $password_hash,
        'status' => 'active'
    ]);
    $user_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO admins (user_id, full_name, created_at) VALUES (:uid, :name, NOW())');
    $stmt->execute(['uid' => $user_id, 'name' => 'CareSched Administrator']);

    $pdo->commit();
    echo "Sample admin created: Username: admin | Email: admin@caresched.test | Password: admin123\n";
    echo "Please change this account password before production.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error creating admin: " . $e->getMessage();
}
