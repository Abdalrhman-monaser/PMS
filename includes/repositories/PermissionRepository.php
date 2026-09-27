<?php

class PermissionRepository {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    // جلب قائمة الصلاحيات للدور وتخزينها في الجلسة
    public function loadUserPermissions(int $roleId) {
        $stmt = $this->db->prepare("
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // تخزين الصلاحيات في الجلسة (Session) لاستخدامها لاحقاً
        $_SESSION['user_permissions'] = $permissions;
        
        return $permissions;
    }
}
