<?php

namespace Tests\Unit;

use Tests\TestCase;

class IntegrationVerificationTest extends TestCase
{
    /**
     * اختبار دورة حياة المشروع والتحقق من الحقول الأساسية
     */
    public function test_project_lifecycle_and_validation(): void
    {
        // 1. Arrange
        $projectData = [
            'code'        => 'INT-PRJ-01',
            'name'        => 'Integration Core Project',
            'description' => 'End to end integration testing',
            'status'      => 'active',
            'priority'    => 'high',
            'progress'    => 25,
            'start_date'  => '2026-09-01',
            'end_date'    => '2026-12-31'
        ];

        // 2. Act
        $stmt = $this->pdo->prepare("
            INSERT INTO projects (code, name, description, status, priority, progress, start_date, end_date)
            VALUES (:code, :name, :description, :status, :priority, :progress, :start_date, :end_date)
        ");
        $stmt->execute($projectData);
        $projectId = (int)$this->pdo->lastInsertId();

        $fetchStmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL");
        $fetchStmt->execute(['id' => $projectId]);
        $project = $fetchStmt->fetch();

        // 3. Assert
        $this->assertNotEmpty($project);
        $this->assertEquals('INT-PRJ-01', $project['code']);
        $this->assertEquals('Integration Core Project', $project['name']);
        $this->assertEquals('active', $project['status']);
        $this->assertEquals(25, (int)$project['progress']);
    }

    /**
     * اختبار منع تكرار كود المشروع
     */
    public function test_project_code_must_be_unique(): void
    {
        // 1. Arrange
        $stmt = $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('UNIQUE-105', 'Project One')");
        $stmt->execute();

        // 2. Act & 3. Assert
        $this->expectException(\PDOException::class);
        $stmt2 = $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('UNIQUE-105', 'Project Two')");
        $stmt2->execute();
    }

    /**
     * اختبار تكامل أعضاء الفريق مع المشروع (إضافة، فحص، حذف)
     */
    public function test_project_members_integration(): void
    {
        // 1. Arrange
        $stmtUser = $this->pdo->prepare("INSERT INTO users (name, email, password, role) VALUES ('Alice Engineer', 'alice@pms.test', 'secret', 'developer')");
        $stmtUser->execute();
        $userId = (int)$this->pdo->lastInsertId();

        $stmtProj = $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('PRJ-MEM-01', 'Member Test Project')");
        $stmtProj->execute();
        $projectId = (int)$this->pdo->lastInsertId();

        // 2. Act: إضافة عضو
        $stmtAdd = $this->pdo->prepare("INSERT INTO project_members (project_id, user_id, role_in_project) VALUES (:p, :u, :r)");
        $stmtAdd->execute(['p' => $projectId, 'u' => $userId, 'r' => 'Lead']);

        // 3. Assert: فحص وجود العضو
        $stmtCheck = $this->pdo->prepare("SELECT * FROM project_members WHERE project_id = :p AND user_id = :u");
        $stmtCheck->execute(['p' => $projectId, 'u' => $userId]);
        $member = $stmtCheck->fetch();
        $this->assertNotEmpty($member);
        $this->assertEquals('Lead', $member['role_in_project']);

        // 4. Act: حذف العضو
        $stmtDel = $this->pdo->prepare("DELETE FROM project_members WHERE project_id = :p AND user_id = :u");
        $stmtDel->execute(['p' => $projectId, 'u' => $userId]);

        // 5. Assert: تأكيد إزالة العضوية مع بقاء المستخدم في جدول المستخدمين
        $stmtCheck->execute(['p' => $projectId, 'u' => $userId]);
        $this->assertFalse($stmtCheck->fetch(), 'Member should be removed from project');

        $userCheck = $this->pdo->prepare("SELECT id FROM users WHERE id = :id");
        $userCheck->execute(['id' => $userId]);
        $this->assertNotEmpty($userCheck->fetch(), 'User record must remain intact in users table');
    }

    /**
     * اختبار منع تكرار إضافة نفس العضو إلى نفس المشروع
     */
    public function test_cannot_add_duplicate_project_member(): void
    {
        // 1. Arrange
        $this->pdo->prepare("INSERT INTO users (name, email, password) VALUES ('Bob', 'bob@pms.test', 'pwd')")->execute();
        $userId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('DUP-MEM-01', 'Dup Member Project')")->execute();
        $projectId = (int)$this->pdo->lastInsertId();

        $addStmt = $this->pdo->prepare("INSERT INTO project_members (project_id, user_id, role_in_project) VALUES (:p, :u, :r)");
        $addStmt->execute(['p' => $projectId, 'u' => $userId, 'r' => 'Member']);

        // 2. Act & 3. Assert (توقع رمي استثناء عند محاولة تكرار العضوية)
        $this->expectException(\PDOException::class);
        $addStmt->execute(['p' => $projectId, 'u' => $userId, 'r' => 'Member']);
    }

    /**
     * اختبار ربط المهام بالمشاريع وتصفيتها بدقة
     */
    public function test_tasks_linked_to_correct_projects(): void
    {
        // 1. Arrange
        $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('PRJ-T-01', 'Task Project 1')")->execute();
        $p1 = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('PRJ-T-02', 'Task Project 2')")->execute();
        $p2 = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, title) VALUES ($p1, 'Task for P1')")->execute();
        $this->pdo->prepare("INSERT INTO tasks (project_id, title) VALUES ($p2, 'Task for P2')")->execute();

        // 2. Act
        $stmt1 = $this->pdo->prepare("SELECT * FROM tasks WHERE project_id = :p AND deleted_at IS NULL");
        $stmt1->execute(['p' => $p1]);
        $tasksP1 = $stmt1->fetchAll();

        // 3. Assert
        $this->assertCount(1, $tasksP1);
        $this->assertEquals('Task for P1', $tasksP1[0]['title']);
    }

    /**
     * اختبار استبعاد مهام المشاريع المحذوفة ناعمًا من الاستعلامات المجمعة
     */
    public function test_soft_deleted_project_excludes_tasks_from_aggregation(): void
    {
        // 1. Arrange
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('DEL-PRJ-01', 'Delete Project', 'active')")->execute();
        $projectId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, title, status) VALUES ($projectId, 'Task Active Project', 'in_progress')")->execute();

        // 2. Act: فحص ظهور المهمة قبل الحذف
        $sql = "SELECT COUNT(*) FROM tasks t
                JOIN projects p ON p.id = t.project_id
                WHERE t.status != 'done' AND t.deleted_at IS NULL AND p.deleted_at IS NULL";
        $openCountBefore = (int)$this->pdo->query($sql)->fetchColumn();
        $this->assertGreaterThan(0, $openCountBefore);

        // حذف المشروع ناعمًا
        $this->pdo->prepare("UPDATE projects SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id")->execute(['id' => $projectId]);

        // 3. Assert: تأكيد استبعاد المهمة بعد حذف المشروع ناعمًا
        $openCountAfter = (int)$this->pdo->query($sql)->fetchColumn();
        $this->assertEquals($openCountBefore - 1, $openCountAfter);
    }

    /**
     * اختبار دقة حساب المهام المتأخرة (Overdue Tasks)
     */
    public function test_overdue_tasks_calculation(): void
    {
        // 1. Arrange
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('OVD-PRJ-01', 'Overdue Project', 'active')")->execute();
        $projectId = (int)$this->pdo->lastInsertId();

        $pastDate = date('Y-m-d', strtotime('-2 days'));
        $futureDate = date('Y-m-d', strtotime('+5 days'));

        // مهمة متأخرة وغير مكتملة
        $this->pdo->prepare("INSERT INTO tasks (project_id, title, status, due_date) VALUES ($projectId, 'Overdue Task', 'in_progress', '$pastDate')")->execute();
        // مهمة منتهية في موعد ماضٍ (لا تعتبر متأخرة)
        $this->pdo->prepare("INSERT INTO tasks (project_id, title, status, due_date) VALUES ($projectId, 'Done Task', 'done', '$pastDate')")->execute();
        // مهمة مستقبلية (لا تعتبر متأخرة)
        $this->pdo->prepare("INSERT INTO tasks (project_id, title, status, due_date) VALUES ($projectId, 'Future Task', 'todo', '$futureDate')")->execute();

        // 2. Act
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) FROM tasks t
                JOIN projects p ON p.id = t.project_id
                WHERE t.status != 'done'
                  AND t.due_date IS NOT NULL
                  AND t.due_date < '$today'
                  AND t.deleted_at IS NULL
                  AND p.deleted_at IS NULL";
        $overdueCount = (int)$this->pdo->query($sql)->fetchColumn();

        // 3. Assert
        $this->assertEquals(1, $overdueCount);
    }

    /**
     * اختبار دقة توزيع حالات المشاريع (Project Status Distribution)
     */
    public function test_project_status_distribution(): void
    {
        // 1. Arrange
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('ST-1', 'P1', 'planning')")->execute();
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('ST-2', 'P2', 'active')")->execute();
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('ST-3', 'P3', 'completed')")->execute();
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('ST-4', 'P4', 'on_hold')")->execute();
        $this->pdo->prepare("INSERT INTO projects (code, name, status) VALUES ('ST-5', 'P5', 'cancelled')")->execute();

        // 2. Act
        $rows = $this->pdo->query("SELECT status, COUNT(*) as cnt FROM projects WHERE deleted_at IS NULL GROUP BY status")->fetchAll();
        $distribution = [];
        foreach ($rows as $r) {
            $distribution[$r['status']] = (int)$r['cnt'];
        }

        // 3. Assert
        $this->assertArrayHasKey('planning', $distribution);
        $this->assertArrayHasKey('active', $distribution);
        $this->assertArrayHasKey('completed', $distribution);
        $this->assertArrayHasKey('on_hold', $distribution);
        $this->assertArrayHasKey('cancelled', $distribution);
        $this->assertGreaterThanOrEqual(1, $distribution['planning']);
        $this->assertGreaterThanOrEqual(1, $distribution['active']);
    }
}
