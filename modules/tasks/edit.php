<?php
require_once '../../includes/repositories/TaskRepository.php';

// بافتراض وجود اتصال قاعدة البيانات $dbConnection ومعرف المدير في بيئة العمل
$managerId = 1; // رقم تجريبي لمدير المشروع

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int)$_POST['task_id'];
    $taskTitle = $_POST['title'];
    $oldAssignee = (int)$_POST['old_assigned_to'];
    $newAssignee = (int)$_POST['assigned_to'];
    $oldStatus = $_POST['old_status'];
    $newStatus = $_POST['status'];
    $dueDate = $_POST['due_date'];
    
    $taskRepo = new TaskRepository($dbConnection);
    $success = $taskRepo->updateTaskWithNotifications($taskId, $taskTitle, $oldAssignee, $newAssignee, $oldStatus, $newStatus, $dueDate, $managerId);
    
    if ($success) {
        header("Location: /dashboard/tasks?msg=success");
        exit();
    } else {
        echo "حدث خطأ أثناء تحديث المهمة وإرسال الإشعارات.";
    }
}
?>
