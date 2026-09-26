<?php

namespace Tests\Unit;

use Tests\TestCase;
use PDO;

class RepositoriesTest extends TestCase
{
    /**
     * اختبار إنشاء واسترجاع المشروع بنمط AAA
     */
    public function test_can_create_and_find_project_successfully(): void
    {
        // 1. Arrange (التهيئة)
        $projectData = [
            'code' => 'PMS-DEV',
            'name' => 'PMS Core Architecture',
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 45,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31'
        ];

        // 2. Act (التنفيذ)
        $stmt = $this->pdo->prepare("
            INSERT INTO projects (code, name, status, priority, progress, start_date, end_date)
            VALUES (:code, :name, :status, :priority, :progress, :start_date, :end_date)
        ");
        $stmt->execute($projectData);
        $insertedId = (int)$this->pdo->lastInsertId();

        $fetchStmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL");
        $fetchStmt->execute(['id' => $insertedId]);
        $project = $fetchStmt->fetch();

        // 3. Assert (التحقق والمطابقة)
        $this->assertNotEmpty($project, 'Project should be found in database');
        $this->assertEquals('PMS-DEV', $project['code']);
        $this->assertEquals('PMS Core Architecture', $project['name']);
        $this->assertEquals(45, (int)$project['progress']);
    }

    /**
     * اختبار رفض تكرار كود المشروع (Unique Code Constraint) بنمط AAA
     */
    public function test_cannot_insert_duplicate_project_code(): void
    {
        // 1. Arrange
        $code = 'DUPLICATE-01';
        $insertQuery = "INSERT INTO projects (code, name) VALUES (:code, :name)";

        $stmt1 = $this->pdo->prepare($insertQuery);
        $stmt1->execute(['code' => $code, 'name' => 'Project A']);

        // 2. Act & 3. Assert (توقع رمي PDOException عند التكرار)
        $this->expectException(\PDOException::class);

        $stmt2 = $this->pdo->prepare($insertQuery);
        $stmt2->execute(['code' => $code, 'name' => 'Project B']);
    }

    /**
     * اختبار الحذف اللطيف (Soft Delete) للمشروع بنمط AAA
     */
    public function test_project_soft_delete_preserves_record_with_timestamp(): void
    {
        // 1. Arrange
        $stmt = $this->pdo->prepare("INSERT INTO projects (code, name) VALUES ('PMS-DEL', 'Delete Me')");
        $stmt->execute();
        $projectId = (int)$this->pdo->lastInsertId();

        // 2. Act
        $deletedTime = date('Y-m-d H:i:s');
        $deleteStmt = $this->pdo->prepare("UPDATE projects SET deleted_at = :deleted_at WHERE id = :id");
        $deleteStmt->execute(['deleted_at' => $deletedTime, 'id' => $projectId]);

        // 3. Assert
        $activeStmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL");
        $activeStmt->execute(['id' => $projectId]);
        $this->assertFalse($activeStmt->fetch(), 'Soft-deleted project must not be returned in active queries');

        $allStmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $allStmt->execute(['id' => $projectId]);
        $record = $allStmt->fetch();
        $this->assertNotEmpty($record);
        $this->assertEquals($deletedTime, $record['deleted_at']);
    }
}