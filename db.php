<?php
// Database connection for Tamkeen
$host = 'localhost';
$dbname = 'tamkeen_db';
$dbuser = 'root';
$dbpass = ''; // Change this if your MySQL account has a password.

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // In production, log the technical error rather than displaying it.
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات. تأكدي من تشغيل MySQL وصحة بيانات الاتصال في db.php.');
}
