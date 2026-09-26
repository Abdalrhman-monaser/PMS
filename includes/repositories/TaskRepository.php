<?php
require_once 'NotificationRepository.php';

class TaskRepository {
    private $db;
    private $notificationRepo;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->notificationRepo = new NotificationRepository($dbConnection);
    }

    // دالة تحديث المهمة مع الترانزاكشن (Transaction) لضمان الاتساق
    public function updateTaskWithNotifications($taskId, $taskTitle, $oldAssignee, $newAssignee, $oldStatus, $newStatus, $dueDate, $managerId) {
        try {
            // بدء الترانزاكشن
            $this->db->beginTransaction();

            // 1. تحديث بيانات المهمة الأساسية
            $stmt = $this->db->prepare("UPDATE tasks SET assigned_to = ?, status = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$newAssignee, $newStatus, $dueDate, $taskId]);

            // 2. إشعار إسناد مهمة لمستخدم جديد
            if ($newAssignee && $newAssignee != $oldAssignee) {
                $msg = "تم إسناد مهمة جديدة إليك: " . $taskTitle;
                $this->notificationRepo->createNotification($newAssignee, 'assignment', 'مهمة جديدة', $msg, "/modules/tasks/view.php?id=$taskId");
            }

            // 3. إشعار اكتمال المهمة لمدير المشروع
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $msg = "تم إكمال المهمة: " . $taskTitle;
                $this->notificationRepo->createNotification($managerId, 'completion', 'إنجاز مهمة', $msg, "/modules/tasks/view.php?id=$taskId");
            }

            // 4. فحص الموعد النهائي (أقل من 24 ساعة)
            if ($dueDate && $newStatus !== 'completed') {
                $dueTime = strtotime($dueDate);
                $now = time();
                $diffHours = ($dueTime - $now) / 3600;
                
                if ($diffHours > 0 && $diffHours <= 24) {
                    $msg = "المهمة تقترب من موعد التسليم: " . $taskTitle;
                    $this->notificationRepo->createNotification($newAssignee, 'deadline', 'تنبيه موعد', $msg, "/modules/tasks/view.php?id=$taskId");
                }
            }

            // تأكيد العملية وحفظها في قاعدة البيانات
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // التراجع عن كل شيء في حال حدوث خطأ
            $this->db->rollBack();
            return false;
        }
    }
}
