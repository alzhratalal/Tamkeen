<?php

require_once __DIR__ . '/../db.php';

$username = 'admin';
$password = '123';

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // التحقق هل حساب الأدمن موجود
    $check = $pdo->prepare(
        "SELECT admin_id FROM admins WHERE username = ?"
    );

    $check->execute([$username]);

    $admin = $check->fetch();

    if ($admin) {

        // تحديث كلمة المرور إذا كان الحساب موجودًا
        $stmt = $pdo->prepare(
            "UPDATE admins SET password = ? WHERE username = ?"
        );

        $stmt->execute([$hashedPassword, $username]);

        echo "تم تحديث حساب الأدمن بنجاح.";

    } else {

        // إنشاء حساب جديد إذا لم يكن موجودًا
        $stmt = $pdo->prepare(
            "INSERT INTO admins (username, password) VALUES (?, ?)"
        );

        $stmt->execute([$username, $hashedPassword]);

        echo "تم إنشاء حساب الأدمن بنجاح.";
    }

    echo "<br>اسم المستخدم: admin";
    echo "<br>كلمة المرور: 123";

} catch (PDOException $e) {
    echo "حدث خطأ في قاعدة البيانات. تأكدي من أسماء الأعمدة في جدول admins.";
}
?>