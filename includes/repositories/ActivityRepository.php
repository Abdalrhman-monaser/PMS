<?php

// المفهوم المستفاد: بناء سمة التدقيق (Trait)
trait HasAuditLog {
    public function logActivity(int $userId, string $action, string $entityType, int $entityId, string $details = "") {
        $stmt = $this->db->prepare("INSERT INTO log_activity (user_id, action, entity_type, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$userId, $action, $entityType, $entityId, $details]);
    }
}

class ActivityRepository {
    // استدعاء السمة للعمل داخل الكلاس
    use HasAuditLog;

    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
}
