<?php
// التأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * دالة مساعدة للتحقق إذا كان المستخدم يملك الصلاحية (ترجع true أو false)
 */
function can(string $permission): bool {
    // إذا لم تكن مصفوفة الصلاحيات موجودة في الجلسة، نمنع الوصول
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) {
        return false;
    }
    
    // البحث عن الصلاحية المطلوبة داخل المصفوفة المحفوظة
    return in_array($permission, $_SESSION['user_permissions']);
}

/**
 * دالة الحراسة (Guard): توقف السكربت تماماً وتظهر 403 إذا لم يملك الصلاحية
 */
function requirePermission(string $permission) {
    if (!can($permission)) {
        // إرجاع كود 403 ووقف التنفيذ منعاً للوصول غير المصرح به
        header("HTTP/1.1 403 Forbidden");
        echo "<div style='text-align:center; margin-top:50px; font-family:tahoma;'>";
        echo "<h1 style='color:red;'>403 Forbidden</h1>";
        echo "<h3>عذراً، ليس لديك الصلاحية لتنفيذ هذه العملية.</h3>";
        echo "<a href='/'>العودة للصفحة الرئيسية</a>";
        echo "</div>";
        exit();
    }
}
