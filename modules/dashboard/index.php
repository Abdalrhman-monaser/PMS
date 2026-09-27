<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$projectRepo = new ProjectRepository();
$taskRepo = new TaskRepository();
$userRepo = new UserRepository();
$activityRepo = new ActivityRepository();

$totalProjects = $projectRepo->totalCount();
$activeProjectsCount = $projectRepo->countByStatus('active');
$openTasks = $taskRepo->countByStatusNot('done');
$doneTasks = $taskRepo->countByStatus('done');
$overdueTasks = $taskRepo->countOverdue();
$teamMembers = $userRepo->countActive();
$statusCounts = $projectRepo->statusCounts();

$activeProjects = $projectRepo->activeProjects(5);
$upcomingTasks = $taskRepo->upcoming(6);
$activity = $activityRepo->recent(8);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-4 mb-24">
    <div class="card">
        <div class="stat-value"><?= (int)$activeProjectsCount ?></div>
        <div class="stat-label">Active Projects</div>
        <div class="muted mono" style="font-size:11px; margin-top:4px;"><?= (int)$activeProjectsCount ?> active / <?= (int)$totalProjects ?> total</div>
    </div>
    <div class="card">
        <div class="stat-value"><?= (int)$openTasks ?></div>
        <div class="stat-label">Open Tasks</div>
    </div>
    <div class="card">
        <div class="stat-value"><?= (int)$doneTasks ?></div>
        <div class="stat-label">Completed Tasks</div>
    </div>
    <div class="card">
        <div class="stat-value" style="<?= $overdueTasks > 0 ? 'color:var(--danger);' : '' ?>"><?= (int)$overdueTasks ?></div>
        <div class="stat-label">Overdue Tasks</div>
        <?php if ($overdueTasks > 0): ?>
        <div class="muted mono" style="font-size:11px; margin-top:4px; color:var(--danger);">Requires attention</div>
        <?php else: ?>
        <div class="muted mono" style="font-size:11px; margin-top:4px; color:var(--success);">All on track</div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-24">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin-bottom:0;">Project Status Breakdown</div>
        <div class="muted mono" style="font-size:12px;"><?= (int)$totalProjects ?> Total Projects</div>
    </div>
    <div style="display:flex; height:10px; border-radius:3px; overflow:hidden; background:var(--grid-line); margin-bottom:14px;">
        <?php if ($totalProjects > 0): ?>
            <?php if ($statusCounts['planning'] > 0): ?>
                <div style="width:<?= round(($statusCounts['planning'] / $totalProjects) * 100, 1) ?>%; background:var(--text-dim);" title="Planning: <?= $statusCounts['planning'] ?>"></div>
            <?php endif; ?>
            <?php if ($statusCounts['active'] > 0): ?>
                <div style="width:<?= round(($statusCounts['active'] / $totalProjects) * 100, 1) ?>%; background:var(--info);" title="Active: <?= $statusCounts['active'] ?>"></div>
            <?php endif; ?>
            <?php if ($statusCounts['on_hold'] > 0): ?>
                <div style="width:<?= round(($statusCounts['on_hold'] / $totalProjects) * 100, 1) ?>%; background:var(--accent);" title="On Hold: <?= $statusCounts['on_hold'] ?>"></div>
            <?php endif; ?>
            <?php if ($statusCounts['completed'] > 0): ?>
                <div style="width:<?= round(($statusCounts['completed'] / $totalProjects) * 100, 1) ?>%; background:var(--success);" title="Completed: <?= $statusCounts['completed'] ?>"></div>
            <?php endif; ?>
            <?php if ($statusCounts['cancelled'] > 0): ?>
                <div style="width:<?= round(($statusCounts['cancelled'] / $totalProjects) * 100, 1) ?>%; background:var(--danger);" title="Cancelled: <?= $statusCounts['cancelled'] ?>"></div>
            <?php endif; ?>
        <?php else: ?>
            <div style="width:100%; background:var(--grid-line);"></div>
        <?php endif; ?>
    </div>
    <div class="flex" style="flex-wrap:wrap; gap:16px; font-size:12px;">
        <div class="flex gap-8"><span style="width:10px; height:10px; border-radius:2px; background:var(--text-dim); display:inline-block;"></span><span class="muted">Planning:</span> <strong><?= (int)$statusCounts['planning'] ?></strong></div>
        <div class="flex gap-8"><span style="width:10px; height:10px; border-radius:2px; background:var(--info); display:inline-block;"></span><span class="muted">Active:</span> <strong><?= (int)$statusCounts['active'] ?></strong></div>
        <div class="flex gap-8"><span style="width:10px; height:10px; border-radius:2px; background:var(--accent); display:inline-block;"></span><span class="muted">On Hold:</span> <strong><?= (int)$statusCounts['on_hold'] ?></strong></div>
        <div class="flex gap-8"><span style="width:10px; height:10px; border-radius:2px; background:var(--success); display:inline-block;"></span><span class="muted">Completed:</span> <strong><?= (int)$statusCounts['completed'] ?></strong></div>
        <div class="flex gap-8"><span style="width:10px; height:10px; border-radius:2px; background:var(--danger); display:inline-block;"></span><span class="muted">Cancelled:</span> <strong><?= (int)$statusCounts['cancelled'] ?></strong></div>
    </div>
</div>

<div class="grid grid-2">
    <div>
        <div class="card mb-24">
            <div class="flex-between mb-16">
                <div class="card-title" style="margin-bottom:0;">Active Projects</div>
                <a href="<?= url('modules/projects/index.php?status=active') ?>" class="muted" style="font-size:12px;">View all active →</a>
            </div>
            <?php if (!$activeProjects): ?>
                <div class="empty-state"><div class="glyph">∅</div>No active projects right now.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Project</th><th>Owner</th><th>Progress</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($activeProjects as $p): ?>
                    <tr onclick="location.href='<?= url('modules/projects/view.php?id=' . $p['id']) ?>'" style="cursor:pointer;">
                        <td>
                            <div style="font-weight:600;"><?= e($p['name']) ?></div>
                            <div class="muted mono" style="font-size:11px;"><?= e($p['code']) ?> · <?= (int)$p['task_count'] ?> tasks</div>
                        </td>
                        <td><?= e($p['owner_name'] ?? '—') ?></td>
                        <td style="width:120px;">
                            <div class="progress-track"><div class="progress-fill" style="width:<?= (int)$p['progress'] ?>%"></div></div>
                        </td>
                        <td><span class="<?= statusBadgeClass($p['status']) ?>"><?= e(str_replace('_',' ',$p['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">Upcoming Tasks</div>
            <?php if (!$upcomingTasks): ?>
                <div class="empty-state"><div class="glyph">∅</div>No upcoming tasks.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Task</th><th>Project</th><th>Assignee</th><th>Due</th><th>Priority</th></tr></thead>
                <tbody>
                <?php foreach ($upcomingTasks as $t): ?>
                    <tr onclick="location.href='<?= url('modules/tasks/view.php?id=' . $t['id']) ?>'" style="cursor:pointer;">
                        <td><?= e($t['title']) ?></td>
                        <td class="muted"><?= e($t['project_name']) ?></td>
                        <td><?= e($t['assignee_name'] ?? '—') ?></td>
                        <td class="mono"><?= formatDate($t['due_date']) ?></td>
                        <td><span class="<?= priorityBadgeClass($t['priority']) ?>"><?= e($t['priority']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="align-self:start;">
        <div class="card-title">Activity Log</div>
        <?php if (!$activity): ?>
            <div class="empty-state"><div class="glyph">∅</div>No activity yet.</div>
        <?php else: ?>
        <ul style="display:flex; flex-direction:column; gap:14px;">
            <?php foreach ($activity as $a): ?>
                <li style="border-left:2px solid var(--border); padding-left:10px;">
                    <div style="font-size:13px;"><?= e($a['description']) ?></div>
                    <div class="muted mono" style="font-size:10.5px; margin-top:2px;">
                        <?= e($a['full_name'] ?? 'System') ?> · <?= date('M d, H:i', strtotime($a['created_at'])) ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
