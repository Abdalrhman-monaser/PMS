<?php


class TaskRepository extends BaseRepository
{
    private const STATUS_ORDER = "FIELD(t.status,'blocked','in_progress','review','todo','done')";

    public function search(string $status = '', string $projectId = '', string $query = ''): array
    {
        $sql = "SELECT t.*, p.name AS project_name, u.full_name AS assignee_name
                FROM tasks t
                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE t.deleted_at IS NULL AND p.deleted_at IS NULL";
        $params = [];

namespace App\Repositories;

use App\Contracts\TaskRepositoryInterface;
use PDO;
use PDOException;

class TaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function find(int $id): ?array
    {

        $sql = "SELECT t.*, p.name AS project_name
                FROM tasks t JOIN projects p ON p.id = t.project_id
                WHERE t.assigned_to = :uid AND t.deleted_at IS NULL AND p.deleted_at IS NULL";
        $params = ['uid' => $userId];
        if ($status !== '') { $sql .= " AND t.status = :status"; $params['status'] = $status; }
        $sql .= " ORDER BY " . self::STATUS_ORDER . ", t.due_date IS NULL, t.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();

        try {
            $stmt = $this->db->prepare("
                SELECT t.*, p.name AS project_name, u.name AS assigned_to_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::find: " . $e->getMessage());
            return null;
        }

    }

    public function getByProject(int $projectId): array
    {

        $sql = "SELECT t.*, u.full_name AS assignee_name FROM tasks t
                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE t.project_id = :id AND t.deleted_at IS NULL AND p.deleted_at IS NULL";
        if ($topLevelOnly) $sql .= " AND t.parent_task_id IS NULL";
        $sql .= " ORDER BY " . self::STATUS_ORDER . ", t.due_date IS NULL, t.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();

        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name AS assigned_to_name
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.project_id = :project_id
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::getByProject: " . $e->getMessage());
            return [];
        }

    }

    public function getByUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, p.name AS project_name
                FROM tasks t

                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE t.status != 'done' AND t.deleted_at IS NULL AND p.deleted_at IS NULL AND t.due_date IS NOT NULL

                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.assigned_to = :user_id

                ORDER BY t.due_date ASC
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::getByUser: " . $e->getMessage());
            return [];
        }
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO tasks (title, description, project_id, assigned_to, priority, status, due_date, created_at, updated_at)
                VALUES (:title, :description, :project_id, :assigned_to, :priority, :status, :due_date, NOW(), NOW())
            ");
            $stmt->execute([
                ':title'       => $data['title'],
                ':description' => $data['description'] ?? null,
                ':project_id'  => $data['project_id'],
                ':assigned_to' => !empty($data['assigned_to']) ? $data['assigned_to'] : null,
                ':priority'    => $data['priority'] ?? 'medium',
                ':status'      => $data['status'] ?? 'todo',
                ':due_date'    => !empty($data['due_date']) ? $data['due_date'] : null,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::create: " . $e->getMessage());
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE tasks 
                SET title = :title,
                    description = :description,
                    project_id = :project_id,
                    assigned_to = :assigned_to,
                    priority = :priority,
                    status = :status,
                    due_date = :due_date,
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                ':id'          => $id,
                ':title'       => $data['title'],
                ':description' => $data['description'] ?? null,
                ':project_id'  => $data['project_id'],
                ':assigned_to' => !empty($data['assigned_to']) ? $data['assigned_to'] : null,
                ':priority'    => $data['priority'] ?? 'medium',
                ':status'      => $data['status'] ?? 'todo',
                ':due_date'    => !empty($data['due_date']) ? $data['due_date'] : null,
            ]);
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::update: " . $e->getMessage());
            return false;
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE tasks 
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                ':id'     => $id,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Database error in TaskRepository::updateStatus: " . $e->getMessage());

require_once 'NotificationRepository.php';

class TaskRepository {
    private $db;
    private $notificationRepo;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->notificationRepo = new NotificationRepository($dbConnection);
    }

    // دالة تحديث المهمة مع الترانزاكشن (Transaction) لضمان الاتساق
    public function updateTaskWithNotifications($taskId, $taskTitle, $oldAssignee, $newAssignee, $oldStatus, $newStatus, $dueDate, $managerId) {
        try {
            // بدء الترانزاكشن
            $this->db->beginTransaction();

            // 1. تحديث بيانات المهمة الأساسية
            $stmt = $this->db->prepare("UPDATE tasks SET assigned_to = ?, status = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$newAssignee, $newStatus, $dueDate, $taskId]);

            // 2. إشعار إسناد مهمة لمستخدم جديد
            if ($newAssignee && $newAssignee != $oldAssignee) {
                $msg = "تم إسناد مهمة جديدة إليك: " . $taskTitle;
                $this->notificationRepo->createNotification($newAssignee, 'assignment', 'مهمة جديدة', $msg, "/modules/tasks/view.php?id=$taskId");
            }


    public function countByStatusNot(string $status): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.status != :status AND t.deleted_at IS NULL AND p.deleted_at IS NULL"
        );
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.status = :status AND t.deleted_at IS NULL AND p.deleted_at IS NULL"
        );
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    /** Count incomplete overdue tasks (due_date < CURDATE() and status != 'done') excluding deleted tasks/projects. */
    public function countOverdue(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.status != 'done'
               AND t.due_date IS NOT NULL
               AND t.due_date < CURDATE()
               AND t.deleted_at IS NULL
               AND p.deleted_at IS NULL"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function rawForBurndown(int $projectId): array
    {
        $stmt = $this->db->prepare("SELECT status, created_at, completed_at FROM tasks WHERE project_id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();
    }

            // 3. إشعار اكتمال المهمة لمدير المشروع
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $msg = "تم إكمال المهمة: " . $taskTitle;
                $this->notificationRepo->createNotification($managerId, 'completion', 'إنجاز مهمة', $msg, "/modules/tasks/view.php?id=$taskId");
            }

            // 4. فحص الموعد النهائي (أقل من 24 ساعة)
            if ($dueDate && $newStatus !== 'completed') {
                $dueTime = strtotime($dueDate);
                $now = time();
                $diffHours = ($dueTime - $now) / 3600;
                
                if ($diffHours > 0 && $diffHours <= 24) {
                    $msg = "المهمة تقترب من موعد التسليم: " . $taskTitle;
                    $this->notificationRepo->createNotification($newAssignee, 'deadline', 'تنبيه موعد', $msg, "/modules/tasks/view.php?id=$taskId");
                }
            }

            // تأكيد العملية وحفظها في قاعدة البيانات
            $this->db->commit();
            return true;


        } catch (Exception $e) {
            // التراجع عن كل شيء في حال حدوث خطأ
            $this->db->rollBack();
            return false;
        }
    }
}