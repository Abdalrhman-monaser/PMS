<?php
require_once __DIR__ . '/BaseRepository.php';

class ProjectRepository extends BaseRepository
{
    /** List projects with owner name + task count, optionally filtered by status, priority, owner, and search query. */
    public function search(array|string $filters = [], string $legacyQuery = ''): array
    {
        if (is_string($filters)) {
            $filters = [
                'status' => $filters,
                'q'      => $legacyQuery,
            ];
        }

        $sql = "SELECT p.*, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count
                FROM projects p
                LEFT JOIN users u ON u.id = p.owner_id
                WHERE p.deleted_at IS NULL";
        $params = [];

        $status = trim($filters['status'] ?? '');
        if ($status !== '') {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }

        $priority = trim($filters['priority'] ?? '');
        if ($priority !== '') {
            $sql .= " AND p.priority = :priority";
            $params['priority'] = $priority;
        }

        $ownerId = trim((string)($filters['owner_id'] ?? ''));
        if ($ownerId !== '') {
            $sql .= " AND p.owner_id = :owner_id";
            $params['owner_id'] = (int)$ownerId;
        }

        $query = trim($filters['q'] ?? '');
        if ($query !== '') {
            $sql .= " AND (p.name LIKE :q1 OR p.code LIKE :q2 OR p.description LIKE :q3)";
            $params['q1'] = "%$query%";
            $params['q2'] = "%$query%";
            $params['q3'] = "%$query%";
        }

        $sql .= " ORDER BY p.updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Total count of all non-deleted projects in the system. */
    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL")->fetchColumn();
    }

    /** Most recently updated projects for the dashboard. */
    public function recentlyUpdated(int $limit = 5): array
    {
        $sql = "SELECT p.*, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count
                FROM projects p
                LEFT JOIN users u ON u.id = p.owner_id
                WHERE p.deleted_at IS NULL
                ORDER BY p.updated_at DESC
                LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.full_name AS owner_name FROM projects p
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE p.id = :id AND p.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findRaw(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        return $this->db->query("SELECT id, name FROM projects WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO projects (name, code, description, project_type, status, priority, progress, start_date, end_date, owner_id, created_by)
                VALUES (:name, :code, :description, :project_type, :status, :priority, :progress, :start_date, :end_date, :owner_id, :created_by)";
        $this->db->prepare($sql)->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = "UPDATE projects SET name=:name, code=:code, description=:description, project_type=:project_type,
                status=:status, priority=:priority, progress=:progress, start_date=:start_date, end_date=:end_date,
                owner_id=:owner_id WHERE id=:id";
        $data['id'] = $id;
        $this->db->prepare($sql)->execute($data);
    }

    public function softDelete(int $id): void
    {
        $this->db->prepare('UPDATE projects SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        $this->db->prepare('UPDATE tasks SET deleted_at = NOW() WHERE project_id = :id')->execute(['id' => $id]);
    }

    public function addMember(int $projectId, int $userId, string $role = 'Owner'): void
    {
        $this->db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role_in_project) VALUES (:p,:u,:r)")
            ->execute(['p' => $projectId, 'u' => $userId, 'r' => $role]);
    }

    public function members(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.full_name, u.role, pm.role_in_project FROM project_members pm
             JOIN users u ON u.id = pm.user_id WHERE pm.project_id = :id"
        );
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function phases(int $projectId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM phases WHERE project_id = :id ORDER BY sequence_order");
        $stmt->execute(['id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM projects WHERE status = :status AND deleted_at IS NULL");
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }
}
