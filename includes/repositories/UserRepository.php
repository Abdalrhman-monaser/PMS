<?php
require_once __DIR__ . '/../contracts/UserRepositoryInterface.php';

class UserRepository implements UserRepositoryInterface {
    private $db;

    public function __construct($dbConnection) {
        // حقن التبعية (Dependency Injection) لمعمل قاعدة البيانات
        $this->db = $dbConnection;
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function allActive() {
        // افتراض أن حقل الحالة هو status أو is_active
        $stmt = $this->db->prepare("SELECT * FROM users WHERE status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data) {
        // تشفير كلمة المرور قبل تخزينها باستخدام الخوارزمية الحديثة
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$data['name'], $data['email'], $hashedPassword]);
    }

    public function update(int $id, array $data) {
        $stmt = $this->db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        return $stmt->execute([$data['name'], $data['email'], $id]);
    }

    public function changePassword(int $id, string $hash) {
        // استخدام password_hash لتأمين الكلمة الجديدة
        $hashedPassword = password_hash($hash, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }
}
