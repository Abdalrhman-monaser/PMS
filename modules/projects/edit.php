<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$user = currentUser();

$projectRepo = new ProjectRepository();
$userRepo = new UserRepository();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
requirePermission($id ? 'projects.edit' : 'projects.create');
$project = ['name'=>'','code'=>'','description'=>'','project_type'=>'','status'=>'planning','priority'=>'medium','progress'=>0,'start_date'=>'','end_date'=>'','owner_id'=>null];

if ($id) {
    $found = $projectRepo->findRaw($id);
    if (!$found) { flash('error', 'Project not found.'); redirect('modules/projects/index.php'); }
    $project = $found;
}

$users = $userRepo->activeList();
$errors = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $project_type = trim($_POST['project_type'] ?? '');
    $status = $_POST['status'] ?? 'planning';
    $priority = $_POST['priority'] ?? 'medium';
    $rawProgress = trim($_POST['progress'] ?? '0');
    $startDateStr = trim($_POST['start_date'] ?? '');
    $endDateStr = trim($_POST['end_date'] ?? '');
    $ownerIdVal = trim($_POST['owner_id'] ?? '');

    // 1. Name validation
    if ($name === '') {
        $errors[] = 'Project name is required.';
    } elseif (mb_strlen($name) < 2) {
        $errors[] = 'Project name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 200) {
        $errors[] = 'Project name cannot exceed 200 characters.';
    }

    // 2. Code validation (uppercase, format, length, uniqueness)
    if ($code === '') {
        $errors[] = 'Project code is required.';
    } elseif (!preg_match('/^[A-Z0-9_\-]{2,20}$/', $code)) {
        $errors[] = 'Project code must be between 2 and 20 alphanumeric characters, hyphens, or underscores.';
    } elseif ($projectRepo->codeExists($code, $id)) {
        $errors[] = 'Project code "' . e($code) . '" is already in use. Please specify a unique code.';
    }

    // 3. Status validation (ENUM)
    $allowedStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Selected status is invalid.';
    }

    // 4. Priority validation (ENUM)
    $allowedPriorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $errors[] = 'Selected priority is invalid.';
    }

    // 5. Progress validation (0-100)
    if (!is_numeric($rawProgress) || (int)$rawProgress < 0 || (int)$rawProgress > 100) {
        $errors[] = 'Progress must be a number between 0 and 100.';
    }
    $progress = is_numeric($rawProgress) ? (int)$rawProgress : 0;

    // 6. Dates validation & comparison
    $startDate = null;
    $endDate = null;

    if ($startDateStr !== '') {
        $dtStart = DateTime::createFromFormat('Y-m-d', $startDateStr);
        if (!$dtStart || $dtStart->format('Y-m-d') !== $startDateStr) {
            $errors[] = 'Start date must be a valid date in YYYY-MM-DD format.';
        } else {
            $startDate = $startDateStr;
        }
    }

    if ($endDateStr !== '') {
        $dtEnd = DateTime::createFromFormat('Y-m-d', $endDateStr);
        if (!$dtEnd || $dtEnd->format('Y-m-d') !== $endDateStr) {
            $errors[] = 'End date must be a valid date in YYYY-MM-DD format.';
        } else {
            $endDate = $endDateStr;
        }
    }

    if ($startDate && $endDate && $endDate < $startDate) {
        $errors[] = 'End date cannot be earlier than start date.';
    }

    // 7. Owner validation (must exist in users table and be active)
    $ownerId = null;
    if ($ownerIdVal !== '') {
        $checkOwner = $userRepo->find((int)$ownerIdVal);
        if (!$checkOwner || $checkOwner['status'] !== 'active') {
            $errors[] = 'Selected owner is not a valid active user.';
        } else {
            $ownerId = (int)$ownerIdVal;
        }
    }

    // 8. Project type validation
    if (mb_strlen($project_type) > 60) {
        $errors[] = 'Project type cannot exceed 60 characters.';
    }

    // Keep user input in the form on validation errors
    $project = array_merge($project, [
        'name' => $name,
        'code' => $code,
        'description' => $description,
        'project_type' => $project_type,
        'status' => $status,
        'priority' => $priority,
        'progress' => $progress,
        'start_date' => $startDateStr,
        'end_date' => $endDateStr,
        'owner_id' => $ownerId,
    ]);

    if (empty($errors)) {
        $data = [
            'name' => $name,
            'code' => $code,
            'description' => $description ?: null,
            'project_type' => $project_type ?: null,
            'status' => $status,
            'priority' => $priority,
            'progress' => $progress,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'owner_id' => $ownerId,
        ];

        try {
            if ($id) {
                $projectRepo->update($id, $data);
                if ($ownerId) {
                    $projectRepo->addMember($id, $ownerId, 'Owner');
                }
                logActivity($user['id'], 'project', $id, 'updated', 'Updated project "' . $data['name'] . '"');
                flash('success', 'Project updated.');
            } else {
                $data['created_by'] = $user['id'];
                $newId = $projectRepo->create($data);
                if ($ownerId) {
                    $projectRepo->addMember($newId, $ownerId, 'Owner');
                }
                logActivity($user['id'], 'project', $newId, 'created', 'Created project "' . $data['name'] . '"');
                flash('success', 'Project created.');
            }
            redirect('modules/projects/index.php');
        } catch (PDOException $e) {
            error_log('Project save error: ' . $e->getMessage());
            $error = 'A database error occurred while saving the project. Please check your data.';
        }
    }
}

$pageTitle = $id ? 'Edit Project' : 'New Project';
$activeNav = 'projects';
require __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:640px;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin:0; padding-left:18px; list-style-type:disc;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrfField() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Project name</label>
                <input type="text" name="name" value="<?= e($project['name']) ?>" required>
            </div>
            <div class="form-group" style="max-width:160px;">
                <label>Code</label>
                <input type="text" name="code" value="<?= e($project['code']) ?>" placeholder="PRJ-002" required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= e($project['description']) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Project type</label>
                <input type="text" name="project_type" value="<?= e($project['project_type']) ?>" placeholder="software, construction, research…">
            </div>
            <div class="form-group">
                <label>Owner</label>
                <select name="owner_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $project['owner_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['planning','active','on_hold','completed','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $project['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select name="priority">
                    <?php foreach (['low','medium','high','critical'] as $p): ?>
                        <option value="<?= $p ?>" <?= $project['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Progress (%)</label>
                <input type="number" name="progress" min="0" max="100" value="<?= (int)$project['progress'] ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Start date</label>
                <input type="date" name="start_date" value="<?= e($project['start_date']) ?>">
            </div>
            <div class="form-group">
                <label>End date</label>
                <input type="date" name="end_date" value="<?= e($project['end_date']) ?>">
            </div>
        </div>

        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Save changes' : 'Create project' ?></button>
            <a href="<?= url('modules/projects/index.php') ?>" class="btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
