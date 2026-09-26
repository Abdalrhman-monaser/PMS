<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$ownerFilter = trim($_GET['owner_id'] ?? '');
$search = trim($_GET['q'] ?? '');

$projectRepo = new ProjectRepository();
$userRepo = new UserRepository();

$projects = $projectRepo->search([
    'status'   => $statusFilter,
    'priority' => $priorityFilter,
    'owner_id' => $ownerFilter,
    'q'        => $search,
]);

$activeUsers = $userRepo->activeList();
$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $priorityFilter !== '' || $ownerFilter !== '');

$pageTitle = 'Projects';
$activeNav = 'projects';
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex-between mb-16" style="flex-wrap:wrap; gap:12px;">
    <form method="get" class="flex gap-8" style="flex-wrap:wrap;">
        <input type="text" name="q" placeholder="Search by name, code, description…" value="<?= e($search) ?>" style="width:230px;">
        <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['planning','active','on_hold','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" onchange="this.form.submit()">
            <option value="">All priorities</option>
            <?php foreach (['low','medium','high','critical'] as $p): ?>
                <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="owner_id" onchange="this.form.submit()">
            <option value="">All owners</option>
            <?php foreach ($activeUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $ownerFilter === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('modules/projects/index.php') ?>" class="btn">Clear Filters</a>
        <?php endif; ?>
    </form>
    <?php if (userCan('projects.create')): ?>
    <a href="<?= url('modules/projects/edit.php') ?>" class="btn btn-primary">+ New Project</a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (!$projects): ?>
        <?php if ($hasActiveFilters): ?>
            <div class="empty-state">
                <div class="glyph">∅</div>
                <div>No projects match the selected filters.</div>
                <div style="margin-top:10px;">
                    <a href="<?= url('modules/projects/index.php') ?>" class="btn btn-sm">Clear Filters</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="glyph">∅</div>
                <div>No projects created yet.</div>
                <?php if (userCan('projects.create')): ?>
                    <div style="margin-top:10px;">
                        <a href="<?= url('modules/projects/edit.php') ?>" class="btn btn-sm btn-primary">+ New Project</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
    <table>
        <thead><tr><th>Code</th><th>Name</th><th>Owner</th><th>Tasks</th><th>Progress</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
            <tr>
                <td class="mono muted"><?= e($p['code']) ?></td>
                <td><a href="<?= url('modules/projects/view.php?id=' . $p['id']) ?>" style="font-weight:600;"><?= e($p['name']) ?></a></td>
                <td><?= e($p['owner_name'] ?? '—') ?></td>
                <td><?= (int)$p['task_count'] ?></td>
                <td style="width:110px;">
                    <div class="progress-track"><div class="progress-fill" style="width:<?= (int)$p['progress'] ?>%"></div></div>
                </td>
                <td><span class="<?= priorityBadgeClass($p['priority']) ?>"><?= e($p['priority']) ?></span></td>
                <td><span class="<?= statusBadgeClass($p['status']) ?>"><?= e(str_replace('_',' ',$p['status'])) ?></span></td>
                <td class="mono muted"><?= formatDate($p['end_date']) ?></td>
                <td class="flex gap-8">
                    <?php if (userCan('projects.edit')): ?>
                    <a href="<?= url('modules/projects/edit.php?id=' . $p['id']) ?>" class="btn btn-sm">Edit</a>
                    <?php endif; ?>
                    <?php if (userCan('projects.delete')): ?>
                    <form method="post" action="<?= url('modules/projects/delete.php') ?>"
                          onsubmit="return confirm('Delete project &quot;<?= e($p['name']) ?>&quot; and all its tasks? This cannot be undone from the UI.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
