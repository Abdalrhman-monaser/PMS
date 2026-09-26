<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$user = currentUser();

$projectRepo = new ProjectRepository();
$taskRepo = new TaskRepository();
$attachmentRepo = new AttachmentRepository();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid project ID.');
    redirect('modules/projects/index.php');
}

$project = $projectRepo->find($id);
if (!$project) {
    flash('error', 'Project not found.');
    redirect('modules/projects/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_member') {
        if (!userCan('projects.edit') && !userCan('team.manage')) {
            flash('error', 'You do not have permission to manage team members.');
            redirect('modules/projects/view.php?id=' . $id);
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $roleInProject = trim($_POST['role_in_project'] ?? 'Member');

        if ($userId <= 0) {
            flash('error', 'Please select a valid user.');
        } elseif ($projectRepo->isMember($id, $userId)) {
            flash('error', 'User is already a member of this project.');
        } else {
            $added = $projectRepo->addMember($id, $userId, $roleInProject ?: 'Member');
            if ($added) {
                logActivity($user['id'], 'project', $id, 'member_added', "Added user #$userId to project team");
                flash('success', 'Member added to project team.');
            } else {
                flash('error', 'Could not add member to project.');
            }
        }
        redirect('modules/projects/view.php?id=' . $id);
    }

    if ($action === 'remove_member') {
        if (!userCan('projects.edit') && !userCan('team.manage')) {
            flash('error', 'You do not have permission to manage team members.');
            redirect('modules/projects/view.php?id=' . $id);
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            flash('error', 'Invalid user specified.');
        } else {
            $removed = $projectRepo->removeMember($id, $userId);
            if ($removed) {
                logActivity($user['id'], 'project', $id, 'member_removed', "Removed user #$userId from project team");
                flash('success', 'Member removed from project team.');
            } else {
                flash('error', 'Could not remove member from project.');
            }
        }
        redirect('modules/projects/view.php?id=' . $id);
    }

    if (isset($_FILES['attachment'])) {
        $stored = saveUploadedFile($_FILES['attachment']);
        if ($stored) {
            $attachmentRepo->createForProject($id, $user['id'], $_FILES['attachment']['name'], $stored, (int)$_FILES['attachment']['size'], $_FILES['attachment']['type']);
            logActivity($user['id'], 'project', $id, 'attached', 'Attached a file to a project');
            flash('success', 'File attached.');
        } else {
            flash('error', 'Could not upload file. Allowed: pdf, doc(x), xls(x), ppt(x), images, txt, csv, zip — max 15 MB.');
        }
        redirect('modules/projects/view.php?id=' . $id);
    }
}

$phases = $projectRepo->phases($id);
$tasks = $taskRepo->forProject($id);
$members = $projectRepo->members($id);
$candidateMembers = (userCan('projects.edit') || userCan('team.manage')) ? $projectRepo->candidateMembers($id) : [];
$attachments = $attachmentRepo->forProject($id);

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

        <div class="card-title" style="margin-top:20px;">Team (<?= count($members) ?>)</div>
        <?php if (!$members): ?>
            <div class="muted" style="font-size:13px;">No members assigned yet.</div>
        <?php else: ?>
        <ul style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($members as $m): ?>
                <li class="flex-between" style="font-size:13px; align-items:center;">
                    <div class="flex gap-8" style="align-items:center;">
                        <?php if (!empty($m['avatar_color'])): ?>
                        <div class="avatar" style="width:24px;height:24px;font-size:10px;background:<?= e($m['avatar_color']) ?>;"><?= e(initials($m['full_name'])) ?></div>
                        <?php endif; ?>
                        <span><?= e($m['full_name']) ?></span>
                        <span class="muted" style="font-size:11px;">(<?= e($m['role_in_project'] ?: $m['role']) ?>)</span>
                    </div>
                    <?php if (userCan('projects.edit') || userCan('team.manage')): ?>
                    <form method="post" action="<?= url('modules/projects/view.php?id=' . $project['id']) ?>" style="margin:0;" onsubmit="return confirm('Remove &quot;<?= e($m['full_name']) ?>&quot; from project team?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove_member">
                        <input type="hidden" name="user_id" value="<?= (int)($m['user_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 8px; font-size:11px;">Remove</button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (userCan('projects.edit') || userCan('team.manage')): ?>
            <?php if (!empty($candidateMembers)): ?>
            <form method="post" action="<?= url('modules/projects/view.php?id=' . $project['id']) ?>" class="flex gap-8" style="margin-top:14px; align-items:center;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_member">
                <select name="user_id" required style="flex:2; font-size:12px; padding:4px 8px;">
                    <option value="">Select team member...</option>
                    <?php foreach ($candidateMembers as $cm): ?>
                        <option value="<?= $cm['id'] ?>"><?= e($cm['full_name']) ?> (<?= e($cm['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="role_in_project" style="flex:1; font-size:12px; padding:4px 8px;">
                    <option value="Member">Member</option>
                    <option value="Lead">Lead</option>
                    <option value="Reviewer">Reviewer</option>
                    <option value="Owner">Owner</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary" style="padding:4px 10px; font-size:12px;">+ Add</button>
            </form>
            <?php else: ?>
                <div class="muted" style="font-size:11.5px; margin-top:12px;">All active users are members of this project.</div>
            <?php endif; ?>
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
    <form method="post" enctype="multipart/form-data" class="flex gap-8">
        <?= csrfField() ?>
        <input type="file" name="attachment" required style="flex:1;">
        <button type="submit" class="btn btn-sm">Upload</button>
    </form>
</div>

<div class="card">
    <div class="card-title">Tasks (<?= count($tasks) ?>)</div>
    <?php if (!$tasks): ?>
        <div class="empty-state"><div class="glyph">∅</div>No tasks yet for this project.</div>
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
