<?php

namespace Api\Controllers;

use TaskRepository;
use ProjectRepository;

class TaskApiController
{
    private TaskRepository $taskRepo;
    private ProjectRepository $projectRepo;

    public function __construct()
    {
        $this->taskRepo = new TaskRepository();
        $this->projectRepo = new ProjectRepository();
    }

    /**
     * GET /api/tasks
     */
    public function index(): void
    {
        $projectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (string)$_GET['project_id'] : '';
        $status = $_GET['status'] ?? '';
        $query = $_GET['q'] ?? '';

        // استخدام دالة search الفعلية في TaskRepository
        $tasks = $this->taskRepo->search($status, $projectId, $query);

        $this->jsonResponse(200, [
            'status' => 'success',
            'count' => count($tasks),
            'data' => $tasks
        ]);
    }

    /**
     * POST /api/tasks
     */
    public function store(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            $this->jsonResponse(400, [
                'status' => 'error',
                'message' => 'Invalid JSON payload'
            ]);
            return;
        }

        // التحقق من صحة البيانات (Validation)
        $errors = [];
        if (empty(trim($input['title'] ?? ''))) {
            $errors['title'] = 'Task title is required';
        }

        if (empty($input['project_id']) || !is_numeric($input['project_id'])) {
            $errors['project_id'] = 'Valid project_id is required';
        } else {
            $project = $this->projectRepo->find((int)$input['project_id']);
            if (!$project) {
                $errors['project_id'] = 'Project does not exist';
            }
        }

        if (!empty($errors)) {
            $this->jsonResponse(422, [
                'status' => 'validation_error',
                'errors' => $errors
            ]);
            return;
        }

        // تجهيز مصفوفة البيانات المتوافقة مع هيكل جدول tasks في النظام
        $data = [
            'project_id' => (int)$input['project_id'],
            'phase_id' => !empty($input['phase_id']) ? (int)$input['phase_id'] : null,
            'title' => trim($input['title']),
            'description' => trim($input['description'] ?? ''),
            'acceptance_criteria' => trim($input['acceptance_criteria'] ?? ''),
            'priority' => $input['priority'] ?? 'medium',
            'status' => $input['status'] ?? 'todo',
            'complexity' => $input['complexity'] ?? 'medium',
            'progress' => isset($input['progress']) ? (int)$input['progress'] : 0,
            'estimated_hours' => isset($input['estimated_hours']) ? (float)$input['estimated_hours'] : 0.0,
            'actual_hours' => isset($input['actual_hours']) ? (float)$input['actual_hours'] : 0.0,
            'assigned_to' => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
            'reviewer_id' => !empty($input['reviewer_id']) ? (int)$input['reviewer_id'] : null,
            'start_date' => !empty($input['start_date']) ? $input['start_date'] : null,
            'due_date' => !empty($input['due_date']) ? $input['due_date'] : null,
            'created_by' => !empty($input['created_by']) ? (int)$input['created_by'] : 1
        ];

        try {
            $taskId = $this->taskRepo->create($data);
            $createdTask = $this->taskRepo->find($taskId);

            $this->jsonResponse(201, [
                'status' => 'success',
                'message' => 'Task created successfully',
                'data' => $createdTask
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(500, [
                'status' => 'error',
                'message' => 'Failed to create task: ' . $e->getMessage()
            ]);
        }
    }

    private function jsonResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}