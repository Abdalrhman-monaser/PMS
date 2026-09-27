<?php
require_once '../../includes/repositories/NotificationRepository.php';

$notifId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notifId > 0) {
    $notificationRepo = new NotificationRepository($dbConnection);
    $notificationRepo->markAsRead($notifId);
    
    header("Location: /dashboard/tasks");
    exit();
}

header("Location: /");
exit();
