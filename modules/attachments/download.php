<?php
session_start();
require_once '../../includes/repositories/ActivityRepository.php';

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Error 403: غير مصرح لك: يرجى تسجيل الدخول أولاً.");
}

$userId = (int)$_SESSION['user_id'];
$attachmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($attachmentId <= 0) {
    header("HTTP/1.1 404 Not Found");
    exit("رقم المرفق غير صحيح.");
}

// جلب بيانات المرفق (بافتراض إعداد $dbConnection في بيئة العمل)
$stmt = $dbConnection->prepare("SELECT * FROM attachments WHERE id = ?");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    header("HTTP/1.1 404 Not Found");
    exit("الملف غير موجود في قاعدة البيانات.");
}

$entityType = $attachment['entity_type'];
$entityId = (int)$attachment['entity_id'];

// 2. التحقق الأمني من الصلاحيات (Authorization)
$hasAccess = false;
if ($entityType === 'task') {
    // التأكد أن المستخدم له علاقة بالمهمة
    $check = $dbConnection->prepare("SELECT id FROM tasks WHERE id = ? AND (assigned_to = ? OR manager_id = ?)");
    $check->execute([$entityId, $userId, $userId]);
    $hasAccess = $check->rowCount() > 0;
} else {
    // سماحية افتراضية للأنواع الأخرى
    $hasAccess = true;
}

// تطبيق معيار (DoD): اعتراض التنزيل للمستخدم غير المصرح
if (!$hasAccess) {
    header("HTTP/1.1 403 Forbidden");
    exit("Error 403: تم اعتراض العملية. ليس لديك صلاحية لمشاهدة وتنزيل ملفات هذا المشروع/المهمة.");
}

// التحقق من وجود الملف فيزيائياً
$filePath = '../../uploads/' . $attachment['file_name'];
if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    exit("عذراً، الملف غير موجود على مساحة التخزين.");
}

// 3. تسجيل عملية التحميل آلياً باستخدام سمة التدقيق (HasAuditLog)
$activityRepo = new ActivityRepository($dbConnection);
$activityRepo->logActivity($userId, 'download_attachment', $entityType, $entityId, "قام بتنزيل الملف: " . $attachment['original_name']);

// 4. إرسال الملف عبر الترويسات الآمنة وإخفاء الرابط
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($attachment['original_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// تنظيف ذاكرة التخزين المؤقت لمنع تلف الملف
if (ob_get_level()) {
    ob_end_clean();
}

readfile($filePath);
exit();
