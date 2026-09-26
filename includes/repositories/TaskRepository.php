<?php
require_once __DIR__ . '/BaseRepository.php';

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

        if ($status !== '') { $sql .= " AND t.status = :status"; $params['status'] = $status; }
        if ($projectId !== '') { $sql .= " AND t.project_id = :project_id"; $params['project_id'] = $projectId; }
        if ($query !== '') { $sql .= " AND t.title LIKE :q"; $params['q'] = "%$query%"; }

        $sql .= " ORDER BY " . self::STATUS_ORDER . ", t.due_date IS NULL, t.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function forUser(int $userId, string $status = ''): array
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
    }

    public function forProject(int $projectId, bool $topLevelOnly = true): array
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
    }

    public function upcoming(int $limit = 6): array
    {
        $sql = "SELECT t.*, p.name AS project_name, u.full_name AS assignee_name
                FROM tasks t
                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE t.status != 'done' AND t.deleted_at IS NULL AND p.deleted_at IS NULL AND t.due_date IS NOT NULL
                ORDER BY t.due_date ASC
                LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, p.name AS project_name, p.id AS project_id, u.full_name AS assignee_name, r.full_name AS reviewer_name
             FROM tasks t JOIN projects p ON p.id = t.project_id
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN users r ON r.id = t.reviewer_id
             WHERE t.id = :id AND t.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findRaw(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function subtasks(int $parentId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE parent_task_id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $parentId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmtProj = $this->db->prepare("SELECT id FROM projects WHERE id = :pid AND deleted_at IS NULL");
        $stmtProj->execute(['pid' => (int)($data['project_id'] ?? 0)]);
        if (!$stmtProj->fetch()) {
            throw new InvalidArgumentException("Cannot create task: Project does not exist or has been deleted.");
        }

        $payload = [
            'project_id'          => (int)($data['project_id'] ?? 0),
            'phase_id'            => $data['phase_id'] ?? null,
            'title'               => $data['title'] ?? '',
            'description'         => $data['description'] ?? null,
            'acceptance_criteria' => $data['acceptance_criteria'] ?? null,
            'priority'            => $data['priority'] ?? 'medium',
            'status'              => $data['status'] ?? 'todo',
            'complexity'          => $data['complexity'] ?? 'moderate',
            'progress'            => (int)($data['progress'] ?? 0),
            'estimated_hours'     => $data['estimated_hours'] ?? null,
            'actual_hours'        => $data['actual_hours'] ?? null,
            'assigned_to'         => $data['assigned_to'] ?? null,
            'reviewer_id'         => $data['reviewer_id'] ?? null,
            'start_date'          => $data['start_date'] ?? null,
            'due_date'            => $data['due_date'] ?? null,
            'created_by'          => $data['created_by'] ?? null,
        ];

        $sql = "INSERT INTO tasks (project_id, phase_id, title, description, acceptance_criteria, priority, status,
                complexity, progress, estimated_hours, actual_hours, assigned_to, reviewer_id, start_date, due_date, created_by)
                VALUES (:project_id, :phase_id, :title, :description, :acceptance_criteria, :priority, :status,
                :complexity, :progress, :estimated_hours, :actual_hours, :assigned_to, :reviewer_id, :start_date, :due_date, :created_by)";
        $this->db->prepare($sql)->execute($payload);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = "UPDATE tasks SET project_id=:project_id, phase_id=:phase_id, title=:title, description=:description,
                acceptance_criteria=:acceptance_criteria, priority=:priority, status=:status, complexity=:complexity,
                progress=:progress, estimated_hours=:estimated_hours, actual_hours=:actual_hours,
                assigned_to=:assigned_to, reviewer_id=:reviewer_id,
                start_date=:start_date, due_date=:due_date,
                completed_at = CASE WHEN :status2 = 'done' THEN NOW() ELSE completed_at END
                WHERE id=:id";
        $data['id'] = $id;
        $data['status2'] = $data['status'];
        $this->db->prepare($sql)->execute($data);
    }

    public function softDelete(int $id): void
    {
        $this->db->prepare('UPDATE tasks SET deleted_at = NOW() WHERE id = :id OR parent_task_id = :id')->execute(['id' => $id]);
    }

    public function unassignFromUser(int $userId): void
    {
        $this->db->prepare('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = :id')->execute(['id' => $userId]);
    }

    /* ---- Dependencies ---- */

    public function candidateDependencies(int $projectId, int $excludeTaskId): array
    {
        $stmt = $this->db->prepare("SELECT id, title FROM tasks WHERE project_id = :pid AND deleted_at IS NULL AND id != :self ORDER BY title");
        $stmt->execute(['pid' => $projectId, 'self' => $excludeTaskId]);
        return $stmt->fetchAll();
    }

    public function dependencyIds(int $taskId): array
    {
        $stmt = $this->db->prepare("SELECT depends_on_task_id FROM task_dependencies WHERE task_id = :id");
        $stmt->execute(['id' => $taskId]);
        return array_column($stmt->fetchAll(), 'depends_on_task_id');
    }

    public function dependencies(int $taskId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.title, t.status FROM task_dependencies td
             JOIN tasks t ON t.id = td.depends_on_task_id
             WHERE td.task_id = :id AND t.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $taskId]);
        return $stmt->fetchAll();
    }

    public function setDependencies(int $taskId, array $dependsOnIds): void
    {
        $dependsOnIds = array_filter(array_map('intval', $dependsOnIds), fn($d) => $d !== $taskId);
        $this->db->prepare("DELETE FROM task_dependencies WHERE task_id = :id")->execute(['id' => $taskId]);
        if ($dependsOnIds) {
            $insert = $this->db->prepare("INSERT IGNORE INTO task_dependencies (task_id, depends_on_task_id) VALUES (:t, :d)");
            foreach ($dependsOnIds as $depId) {
                $insert->execute(['t' => $taskId, 'd' => $depId]);
            }
        }
    }

    /* ---- Comments ---- */

    public function comments(int $taskId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, u.full_name FROM task_comments c JOIN users u ON u.id = c.user_id
             WHERE c.task_id = :id ORDER BY c.created_at ASC"
        );
        $stmt->execute(['id' => $taskId]);
        return $stmt->fetchAll();
    }

    public function addComment(int $taskId, int $userId, string $comment): void
    {
        $this->db->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (:t, :u, :c)")
            ->execute(['t' => $taskId, 'u' => $userId, 'c' => $comment]);
    }

    /* ---- Aggregates for dashboard / reports ---- */

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

    public function rawForBurndown(int $projectId): array
    {
        $stmt = $this->db->prepare("SELECT status, created_at, completed_at FROM tasks WHERE project_id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function rawForGantt(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, status, start_date, due_date FROM tasks
             WHERE project_id = :id AND deleted_at IS NULL
             ORDER BY start_date IS NULL, start_date, due_date"
        );
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();
    }
}
