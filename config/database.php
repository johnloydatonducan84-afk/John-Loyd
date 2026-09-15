<?php
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Ensure messaging tables exist.
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        patient_id INT(11) NOT NULL,
        admin_id INT(11) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_conversation (patient_id, admin_id),
        INDEX idx_conversation_patient (patient_id),
        INDEX idx_conversation_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        receiver_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        INDEX idx_message_conversation (conversation_id),
        INDEX idx_message_sender (sender_id),
        INDEX idx_message_receiver (receiver_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    error_log('CareSched DB Connection Error: ' . $e->getMessage());
    throw $e;
}

