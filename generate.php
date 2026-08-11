<?php

$password = 'admin123';

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>CareSched Admin Password Hash</h2>";
echo "<p>Password: <strong>admin123</strong></p>";
echo "<p>Generated Hash:</p>";
echo "<textarea style='width:600px;height:100px;font-size:16px;'>"
    . htmlspecialchars($hash)
    . "</textarea>";