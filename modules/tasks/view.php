<?php
require_once '../../includes/repositories/AttachmentRepository.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    $attachmentRepo = new AttachmentRepository($dbConnection);
    $taskId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
    $userId = $_SESSION['user_id'] ?? 1; 
    
    $success = $attachmentRepo->upload($_FILES['attachment'], 'task', $taskId, $userId);
    if ($success) {
        echo "<script>alert('تم رفع الملف المشفر بنجاح!');</script>";
    }
}
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$user = currentUser();

$taskRepo = new TaskRepository();
$attachmentRepo = new AttachmentRepository();

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    verifyCsrf();
    $comment = trim($_POST['comment']);
    if ($comment !== '') {
        $taskRepo->addComment($id, $user['id'], $comment);
        logActivity($user['id'], 'task', $id, 'commented', 'Commented on a task');

        $taskForNotif = $taskRepo->findRaw($id);
        if ($taskForNotif && $taskForNotif['assigned_to']) {
            (new NotificationRepository())->create(
                (int) $taskForNotif['assigned_to'],
                (int) $user['id'],
                'task_commented',
                $user['full_name'] . ' commented on "' . $taskForNotif['title'] . '"',
                'modules/tasks/view.php?id=' . $id
            );
        }
    }
    redirect('modules/tasks/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment'])) {
    verifyCsrf();
    $stored = saveUploadedFile($_FILES['attachment']);
    if ($stored) {
        $attachmentRepo->createForTask($id, $user['id'], $_FILES['attachment']['name'], $stored, (int)$_FILES['attachment']['size'], $_FILES['attachment']['type']);
        logActivity($user['id'], 'task', $id, 'attached', 'Attached a file to a task');
        flash('success', 'File attached.');
    } else {
        flash('error', 'Could not upload file. Allowed: pdf, doc(x), xls(x), ppt(x), images, txt, csv, zip — max 15 MB.');
    }
    redirect('modules/tasks/view.php?id=' . $id);
}

$task = $taskRepo->find($id);
if (!$task) { flash('error', 'Task not found.'); redirect('modules/tasks/index.php'); }

$subtasks = $taskRepo->subtasks($id);
$comments = $taskRepo->comments($id);
$dependencies = $taskRepo->dependencies($id);
$blockedByIncomplete = array_filter($dependencies, fn($d) => $d['status'] !== 'done');
$attachments = $attachmentRepo->forTask($id);

$pageTitle = $task['title'];
$activeNav = 'tasks';
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex-between mb-16">
    <div>
        <div class="muted" style="font-size:12px;">
            <a href="<?= url('modules/projects/view.php?id=' . $task['project_id']) ?>" class="muted"><?= e($task['project_name']) ?></a>
        </div>
        <h2 style="font-size:20px;"><?= e($task['title']) ?></h2>
    </div>
    <div class="flex gap-8">
        <span class="<?= statusBadgeClass($task['status']) ?>"><?= e(str_replace('_',' ',$task['status'])) ?></span>
        <span class="<?= priorityBadgeClass($task['priority']) ?>"><?= e($task['priority']) ?></span>
        <a href="<?= url('modules/tasks/edit.php?id=' . $task['id']) ?>" class="btn btn-sm">Edit</a>
    </div>
</div>

<div class="grid grid-2">
    <div>
        <?php if ($blockedByIncomplete): ?>
        <div class="alert alert-error">
            Blocked by <?= count($blockedByIncomplete) ?> incomplete dependenc<?= count($blockedByIncomplete) === 1 ? 'y' : 'ies' ?>:
            <?= implode(', ', array_map(fn($d) => e($d['title']), $blockedByIncomplete)) ?>
        </div>
        <?php endif; ?>

        <div class="card mb-24">
            <div class="card-title">Description</div>
            <p style="color:var(--text-dim);"><?= nl2br(e($task['description'] ?: 'No description.')) ?></p>
            <?php if ($task['acceptance_criteria']): ?>
                <div class="card-title" style="margin-top:16px;">Acceptance Criteria</div>
                <p style="color:var(--text-dim);"><?= nl2br(e($task['acceptance_criteria'])) ?></p>
            <?php endif; ?>

            <div style="margin-top:16px;">
                <div class="flex-between muted" style="font-size:11px; margin-bottom:6px;">
                    <span>Progress</span><span><?= (int)$task['progress'] ?>%</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:<?= (int)$task['progress'] ?>%"></div></div>
            </div>
        </div>

        <?php if ($subtasks): ?>
        <div class="card mb-24">
            <div class="card-title">Subtasks</div>
            <table>
                <thead><tr><th>Title</th><th>Status</th><th>Progress</th></tr></thead>
                <tbody>
                <?php foreach ($subtasks as $s): ?>
                    <tr onclick="location.href='<?= url('modules/tasks/view.php?id=' . $s['id']) ?>'" style="cursor:pointer;">
                        <td><?= e($s['title']) ?></td>
                        <td><span class="<?= statusBadgeClass($s['status']) ?>"><?= e(str_replace('_',' ',$s['status'])) ?></span></td>
                        <td style="width:100px;"><div class="progress-track"><div class="progress-fill" style="width:<?= (int)$s['progress'] ?>%"></div></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($dependencies): ?>
        <div class="card mb-24">
            <div class="card-title">Depends On</div>
            <ul style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($dependencies as $d): ?>
                    <li class="flex-between">
                        <a href="<?= url('modules/tasks/view.php?id=' . $d['id']) ?>"><?= e($d['title']) ?></a>
                        <span class="<?= statusBadgeClass($d['status']) ?>"><?= e(str_replace('_',' ',$d['status'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

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
            <form method="post" enctype="multipart/form-data" class="flex gap-8">
                <?= csrfField() ?>
                <input type="file" name="attachment" required style="flex:1;">
                <button type="submit" class="btn btn-sm">Upload</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title">Comments</div>
            <ul style="display:flex; flex-direction:column; gap:14px; margin-bottom:16px;">
                <?php foreach ($comments as $c): ?>
                    <li>
                        <div class="flex-between">
                            <span style="font-weight:600; font-size:13px;"><?= e($c['full_name']) ?></span>
                            <span class="muted mono" style="font-size:10.5px;"><?= date('M d, H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div style="font-size:13px; color:var(--text-dim); margin-top:2px;"><?= nl2br(e($c['comment'])) ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$comments): ?><div class="muted" style="font-size:13px;">No comments yet.</div><?php endif; ?>
            </ul>
            <form method="post">
                <?= csrfField() ?>
                <div class="form-group">
                    <textarea name="comment" placeholder="Add a comment…" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Post comment</button>
            </form>
        </div>
    </div>

    <div class="card" style="align-self:start;">
        <div class="card-title">Details</div>
        <ul style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
            <li class="flex-between"><span class="muted">Assignee</span><span><?= e($task['assignee_name'] ?? '—') ?></span></li>
            <li class="flex-between"><span class="muted">Reviewer</span><span><?= e($task['reviewer_name'] ?? '—') ?></span></li>
            <li class="flex-between"><span class="muted">Complexity</span><span><?= e(ucfirst($task['complexity'])) ?></span></li>
            <li class="flex-between"><span class="muted">Est. hours</span><span class="mono"><?= e($task['estimated_hours'] ?? '—') ?></span></li>
            <li class="flex-between"><span class="muted">Actual hours</span><span class="mono"><?= e($task['actual_hours'] ?? '—') ?></span></li>
            <li class="flex-between"><span class="muted">Start</span><span class="mono"><?= formatDate($task['start_date']) ?></span></li>
            <li class="flex-between"><span class="muted">Due</span><span class="mono"><?= formatDate($task['due_date']) ?></span></li>
            <li class="flex-between"><span class="muted">Created</span><span class="mono"><?= formatDate($task['created_at']) ?></span></li>
        </ul>
        <!-- نموذج رفع المرفقات الذي تمت إضافته -->
<div style="padding: 20px; font-family: tahoma; background-color: #f9f9f9; border-top: 1px solid #ddd; margin-top: 20px;">
    <h3>إرفاق ملف للمهمة (الحد الأقصى 10MB)</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="attachment" required>
        <button type="submit" style="background: #007bff; color: white; padding: 10px; border: none; cursor: pointer; border-radius: 4px;">رفع المرفق بأمان</button>
    </form>
</div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
