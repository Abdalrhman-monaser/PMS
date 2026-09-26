<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$user = currentUser();

$projectRepo = new ProjectRepository();
$taskRepo = new TaskRepository();
$attachmentRepo = new AttachmentRepository();

// 1. Strict ID validation
$rawId = $_GET['id'] ?? null;
$id = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : 0;

if ($id <= 0) {
    flash('error', 'Invalid project ID.');
    redirect('modules/projects/index.php');
}

// 2, 3, 4. Fetch project & verify exists and not deleted
$project = $projectRepo->find($id);
if (!$project) {
    flash('error', 'Project not found.');
    redirect('modules/projects/index.php');
}

// 5. Only now handle POST (e.g. attachment upload) with permission check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    requirePermission('projects.edit');
    verifyCsrf();
    $stored = saveUploadedFile($_FILES['attachment']);
    if ($stored) {
        $attachmentRepo->createForProject($id, $user['id'], $_FILES['attachment']['name'], $stored, (int)$_FILES['attachment']['size'], $_FILES['attachment']['type']);
        logActivity($user['id'], 'project', $id, 'attached', 'Attached a file to project "' . $project['name'] . '"');
        flash('success', 'File attached.');
    } else {
        flash('error', 'Could not upload file. Allowed: pdf, doc(x), xls(x), ppt(x), images, txt, csv, zip — max 15 MB.');
    }
    redirect('modules/projects/view.php?id=' . $id);
}

$phases = $projectRepo->phases($id);
$tasks = $taskRepo->forProject($id);
$members = $projectRepo->members($id);
$attachments = $attachmentRepo->forProject($id);

// Calculate task summary metrics
$totalTasks = count($tasks);
$doneTasks = 0;
foreach ($tasks as $t) {
    if ($t['status'] === 'done') {
        $doneTasks++;
    }
}
$openTasks = $totalTasks - $doneTasks;

$pageTitle = $project['name'];
$activeNav = 'projects';
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex-between mb-16">
    <div>
        <div class="mono muted" style="font-size:12px;"><?= e($project['code']) ?></div>
        <h2 style="font-size:20px;"><?= e($project['name']) ?></h2>
    </div>
    <div class="flex gap-8">
        <span class="<?= statusBadgeClass($project['status']) ?>"><?= e(str_replace('_',' ',$project['status'])) ?></span>
        <span class="<?= priorityBadgeClass($project['priority']) ?>"><?= e($project['priority']) ?></span>
        <?php if (userCan('projects.edit')): ?>
        <a href="<?= url('modules/projects/edit.php?id=' . $project['id']) ?>" class="btn btn-sm">Edit</a>
        <?php endif; ?>
        <?php if (userCan('tasks.create')): ?>
        <a href="<?= url('modules/tasks/edit.php?project_id=' . $project['id']) ?>" class="btn btn-sm btn-primary">+ Task</a>
        <?php endif; ?>
        <?php if (userCan('projects.delete')): ?>
        <form method="post" action="<?= url('modules/projects/delete.php') ?>" style="display:inline;"
              onsubmit="return confirm('Delete project &quot;<?= e($project['name']) ?>&quot; and all its tasks? This cannot be undone from the UI.');">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $project['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2 mb-24">
    <div class="card">
        <div class="card-title">Overview</div>
        <p style="color:var(--text-dim); margin:0 0 16px;"><?= nl2br(e($project['description'] ?: 'No description provided.')) ?></p>
        <div class="grid grid-3" style="gap:12px;">
            <div><div class="muted" style="font-size:11px;">Owner</div><div><?= e($project['owner_name'] ?? '—') ?></div></div>
            <div><div class="muted" style="font-size:11px;">Start</div><div class="mono"><?= formatDate($project['start_date']) ?></div></div>
            <div><div class="muted" style="font-size:11px;">Due</div><div class="mono"><?= formatDate($project['end_date']) ?></div></div>
        </div>
        <div style="margin-top:16px;">
            <div class="flex-between muted" style="font-size:11px; margin-bottom:6px;">
                <span>Progress</span><span><?= (int)$project['progress'] ?>%</span>
            </div>
            <div class="progress-track"><div class="progress-fill" style="width:<?= (int)$project['progress'] ?>%"></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Phases</div>
        <?php if (!$phases): ?>
            <div class="muted" style="font-size:13px;">No phases defined.</div>
        <?php else: ?>
        <ul style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($phases as $ph): ?>
                <li class="flex-between">
                    <span><?= e($ph['name']) ?></span>
                    <span class="<?= statusBadgeClass($ph['status']) ?>"><?= e(str_replace('_',' ',$ph['status'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="card-title" style="margin-top:20px;">Team</div>
        <?php if (!$members): ?>
            <div class="muted" style="font-size:13px;">No members assigned yet.</div>
        <?php else: ?>
        <ul style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($members as $m): ?>
                <li class="flex-between" style="font-size:13px;">
                    <span><?= e($m['full_name']) ?></span>
                    <span class="muted"><?= e($m['role_in_project'] ?: $m['role']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-24">
    <div class="card-title">Attachments</div>
    <?php if (!$attachments): ?>
        <div class="muted" style="font-size:13px; margin-bottom:14px;">No files attached yet.</div>
    <?php else: ?>
    <ul style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
        <?php foreach ($attachments as $a): ?>
            <li class="flex-between">
                <a href="<?= url('modules/attachments/download.php?id=' . $a['id']) ?>" class="mono" style="font-size:12.5px;">
                    <?= e($a['original_name']) ?>
                </a>
                <span class="muted" style="font-size:11px;"><?= humanFileSize((int)$a['file_size']) ?> · <?= e($a['full_name'] ?? '—') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if (userCan('projects.edit')): ?>
    <form method="post" enctype="multipart/form-data" class="flex gap-8">
        <?= csrfField() ?>
        <input type="file" name="attachment" required style="flex:1;">
        <button type="submit" class="btn btn-sm">Upload</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin-bottom:0;">Tasks</div>
        <div class="flex gap-12" style="font-size:12px;">
            <span class="muted">Total: <strong style="color:var(--text);"><?= $totalTasks ?></strong></span>
            <span class="muted">Open: <strong style="color:var(--info);"><?= $openTasks ?></strong></span>
            <span class="muted">Done: <strong style="color:var(--success);"><?= $doneTasks ?></strong></span>
        </div>
    </div>
    <?php if (!$tasks): ?>
        <div class="empty-state">
            <div class="glyph">∅</div>
            <div>No tasks yet for this project.</div>
            <?php if (userCan('tasks.create')): ?>
            <div style="margin-top:10px;">
                <a href="<?= url('modules/tasks/edit.php?project_id=' . $project['id']) ?>" class="btn btn-sm btn-primary">+ Task</a>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <table>
        <thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Progress</th><th>Due</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
            <tr onclick="location.href='<?= url('modules/tasks/view.php?id=' . $t['id']) ?>'" style="cursor:pointer;">
                <td><?= e($t['title']) ?></td>
                <td><?= e($t['assignee_name'] ?? '—') ?></td>
                <td><span class="<?= priorityBadgeClass($t['priority']) ?>"><?= e($t['priority']) ?></span></td>
                <td><span class="<?= statusBadgeClass($t['status']) ?>"><?= e(str_replace('_',' ',$t['status'])) ?></span></td>
                <td style="width:100px;"><div class="progress-track"><div class="progress-fill" style="width:<?= (int)$t['progress'] ?>%"></div></div></td>
                <td class="mono muted"><?= formatDate($t['due_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
