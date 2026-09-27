<?php
/**
 * Shared layout header. Expects $pageTitle and $activeNav to be set
 * by the including page before this file is required.
 */
requireLogin();
$user = currentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$unreadNotifications = (new NotificationRepository())->unreadCount($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="mark">P</div>
            <div>
                <div class="name">PMS</div>
                <div class="sub">Engineering Console</div>
            </div>
        </div>

        <nav class="nav-section">
            <div class="nav-label">Workspace</div>
            <a href="<?= url('modules/dashboard/index.php') ?>" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <span class="ico">▣</span> Dashboard
            </a>
            <a href="<?= url('modules/projects/index.php') ?>" class="nav-item <?= $activeNav === 'projects' ? 'active' : '' ?>">
                <span class="ico">▦</span> Projects
            </a>
            <a href="<?= url('modules/tasks/index.php') ?>" class="nav-item <?= $activeNav === 'tasks' ? 'active' : '' ?>">
                <span class="ico">☑</span> Tasks
            </a>
            <a href="<?= url('modules/tasks/my.php') ?>" class="nav-item <?= $activeNav === 'my-tasks' ? 'active' : '' ?>">
                <span class="ico">◆</span> My Tasks
            </a>
            <a href="<?= url('modules/team/index.php') ?>" class="nav-item <?= $activeNav === 'team' ? 'active' : '' ?>">
                <span class="ico">◎</span> Team
            </a>

            <div class="nav-label" style="margin-top:14px;">Insights</div>
            <?php if (userCan('reports.view')): ?>
            <a href="<?= url('modules/reports/index.php') ?>" class="nav-item <?= $activeNav === 'reports' ? 'active' : '' ?>">
                <span class="ico">▤</span> Reports
            </a>
            <?php endif; ?>

            <?php if (userCan('settings.manage')): ?>
            <div class="nav-label" style="margin-top:14px;">System</div>
            <a href="<?= url('modules/settings/permissions.php') ?>" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
                <span class="ico">⚙</span> Permissions
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="avatar" style="background: <?= e($user['avatar_color']) ?>">
                <?= e(initials($user['full_name'])) ?>
            </div>
            <div>
                <div class="who"><?= e($user['full_name']) ?></div>
                <div class="role"><?= e($user['role']) ?></div>
            </div>
            <form method="post" action="<?= url('modules/auth/logout.php') ?>">
                <button type="submit" class="icon-btn" title="Log out">⏻</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div>
                <div class="title"><?= e($pageTitle) ?></div>
            </div>
            <div class="flex gap-12">
                <div class="crumb mono" style="align-self:center;"><?= date('D, M d Y') ?></div>
                <a href="<?= url('modules/notifications/index.php') ?>" class="icon-btn" style="position:relative; font-size:16px;" title="Notifications">
                    🔔
                    <?php if ($unreadNotifications > 0): ?>
                        <span style="position:absolute; top:-4px; right:-6px; background:var(--danger); color:#fff; font-size:9px; font-family:var(--font-mono); font-weight:600; border-radius:8px; padding:1px 5px; line-height:1.3;"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <div class="content">
        <?php
            $success = flash('success');
<?php
$userId = $_SESSION['user_id'] ?? 1; 
$unreadCount = $notificationRepo->getUnreadCount($userId);
$latestNotifications = $notificationRepo->getLatestNotifications($userId, 5);

function getRelativeTime($datetime) {
    $diff = date_diff(new DateTime(), new DateTime($datetime));
    if ($diff->d > 0) return "منذ " . $diff->d . " يوم";
    if ($diff->h > 0) return "منذ " . $diff->h . " ساعة";
    if ($diff->i > 0) return "منذ " . $diff->i . " دقيقة";
    return "الآن";
}
?>

<style>
    .notif-container { position: relative; display: inline-block; font-family: sans-serif; }
    .notif-bell { font-size: 24px; cursor: pointer; background: none; border: none; padding: 10px; }
    .notif-badge { position: absolute; top: 2px; right: 2px; background-color: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; font-weight: bold; }
    .notif-dropdown { display: none; position: absolute; right: 0; background: white; width: 320px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; z-index: 1000; overflow: hidden; }
    .notif-container:hover .notif-dropdown { display: block; }
    .notif-header { padding: 12px 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; font-weight: bold; text-align: right; }
    .notif-item { display: block; padding: 12px 15px; border-bottom: 1px solid #eee; text-decoration: none; color: #333; text-align: right; }
    .notif-item:hover { background: #f1f5f9; }
    .notif-time { font-size: 11px; color: #888; display: block; margin-top: 5px; }
</style>

<div class="notif-container">
    <button class="notif-bell">🔔
        <?php if($unreadCount > 0): ?>
            <span class="notif-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
    </button>
    <div class="notif-dropdown">
        <div class="notif-header">الإشعارات (<?= $unreadCount ?>)</div>
        <?php if(empty($latestNotifications)): ?>
            <div class="notif-item" style="text-align: center; color: #777;">لا توجد إشعارات جديدة</div>
        <?php else: ?>
            <?php foreach($latestNotifications as $notif): ?>
                <a href="/modules/notifications/read.php?id=<?= $notif['id'] ?>" class="notif-item">
                    <strong><?= htmlspecialchars($notif['title']) ?></strong>
                    <span style="display:block; font-size: 13px; margin-top: 3px;"><?= htmlspecialchars($notif['message']) ?></span>
                    <span class="notif-time"><?= getRelativeTime($notif['created_at']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
            $error = flash('error');
        ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
