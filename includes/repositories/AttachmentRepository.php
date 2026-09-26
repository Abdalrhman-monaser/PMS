<?php
class AttachmentRepository {
    private $db;
    private $uploadDir = '../../uploads/';

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function upload(array $file, string $entityType, int $entityId, int $userId): bool {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $blockedExtensions = ['php', 'phtml', 'exe', 'sh', 'bat', 'js'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];

        if ($fileError !== UPLOAD_ERR_OK) {
            return false;
        }

        // فحص الحجم
        if ($fileSize > $maxSize) {
            echo "<script>alert('الرفض: حجم الملف يتجاوز الحد الأقصى 10MB');</script>";
            return false;
        }

        // فحص الامتداد
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $blockedExtensions) || !in_array($fileExt, $allowedExtensions)) {
            echo "<script>alert('تحذير أمني: هذا النوع من الملفات غير مسموح به (محاولة رفع سكربت مرفوضة).');</script>";
            return false;
        }

        // فحص نوع المحتوى الفعلي (MIME Type)
        $mimeType = mime_content_type($fileTmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo "<script>alert('تحذير أمني: نوع المحتوى الفعلي غير متطابق أو مزيف.');</script>";
            return false;
        }

        // توليد اسم ملف عشوائي مشفر
        $randomFileName = bin2hex(random_bytes(16)) . '.' . $fileExt;
        $destination = $this->uploadDir . $randomFileName;

        if (move_uploaded_file($fileTmpName, $destination)) {
            $stmt = $this->db->prepare("INSERT INTO attachments (entity_type, entity_id, user_id, file_name, original_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([$entityType, $entityId, $userId, $randomFileName, $fileName]);
        }

        return false;
    }
}
