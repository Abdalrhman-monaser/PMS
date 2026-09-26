<?php
/**
 * PMS Integration Verification Suite — Issue #105
 * 
 * Verifies end-to-end system integration across:
 * 1. Project CRUD & Validation Hardening (Issue #101)
 * 2. Projects UI, Filters, and Permissions (Issue #102)
 * 3. Project ↔ Teams & Tasks Integration via project_members (Issue #103)
 * 4. Dashboard Metrics, Overdue Tasks & Status Distribution (Issue #104)
 * 5. Full End-to-End Workflow & Regressions (Issue #105)
 */

require_once __DIR__ . '/../config/app.php';

$pdo = db();
$projectRepo = new ProjectRepository();
$taskRepo = new TaskRepository();
$userRepo = new UserRepository();
$attachmentRepo = new AttachmentRepository();

$total = 0;
$passed = 0;
$failed = 0;
$failures = [];

function check(bool $condition, string $title, string $details = ''): void {
    global $total, $passed, $failed, $failures;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [\033[32mPASS\033[0m] $title\n";
    } else {
        $failed++;
        $failures[] = "$title: $details";
        echo "  [\033[31mFAIL\033[0m] $title ($details)\n";
    }
}

echo "====================================================================\n";
echo "       PMS FULL SYSTEM INTEGRATION VERIFICATION (ISSUE #105)        \n";
echo "====================================================================\n\n";

// Cleanup routine for test data
$cleanup = function() use ($pdo) {
    $pdo->exec("DELETE FROM task_comments WHERE task_id IN (SELECT id FROM tasks WHERE title LIKE 'IV105_%')");
    $pdo->exec("DELETE FROM tasks WHERE title LIKE 'IV105_%'");
    $pdo->exec("DELETE FROM project_members WHERE project_id IN (SELECT id FROM projects WHERE code LIKE 'IV_%')");
    $pdo->exec("DELETE FROM attachments WHERE project_id IN (SELECT id FROM projects WHERE code LIKE 'IV_%')");
    $pdo->exec("DELETE FROM activity_log WHERE description LIKE '%IV_%' OR description LIKE '%IV105_%' OR description LIKE '%Integration Alpha%'");
    $pdo->exec("DELETE FROM projects WHERE code LIKE 'IV_%'");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'iv105_%@pms.test'");
};
$cleanup();

function createTestProject(ProjectRepository $repo, array $data): int {
    $payload = [
        'name'         => $data['name'] ?? 'IV Project',
        'code'         => $data['code'] ?? 'IV_' . rand(1000, 9999),
        'description'  => $data['description'] ?? 'Integration test project',
        'project_type' => $data['project_type'] ?? 'Internal',
        'status'       => $data['status'] ?? 'planning',
        'priority'     => $data['priority'] ?? 'medium',
        'progress'     => (int)($data['progress'] ?? 0),
        'start_date'   => $data['start_date'] ?? date('Y-m-d'),
        'end_date'     => $data['end_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'owner_id'     => $data['owner_id'] ?? null,
        'created_by'   => $data['created_by'] ?? null,
    ];
    return $repo->create($payload);
}

try {
    // ---------------------------------------------------------
    // AREA 1: Project CRUD & Validation Hardening (Issue #101)
    // ---------------------------------------------------------
    echo "--- Area 1: Project CRUD & Server-side Validation Hardening ---\n";

    // 1.1 Project creation & codeExists check
    $pCode1 = 'IV_CODE01';
    check(!$projectRepo->codeExists($pCode1), "codeExists returns false for non-existent code");

    $p1 = createTestProject($projectRepo, ['code' => $pCode1, 'name' => 'Integration Alpha', 'status' => 'active', 'priority' => 'high']);
    check($p1 > 0, "Project successfully created with valid data", "ID: $p1");
    check($projectRepo->codeExists($pCode1), "codeExists returns true after creation");
    check(!$projectRepo->codeExists($pCode1, $p1), "codeExists returns false when excluding current project ID");

    // 1.2 Fetch and verify fields
    $fetched = $projectRepo->find($p1);
    check($fetched !== null && $fetched['code'] === $pCode1, "find() returns correct project record");
    check($fetched['status'] === 'active', "Project status matches inserted value (active)");
    check($fetched['priority'] === 'high', "Project priority matches inserted value (high)");

    // 1.3 Project update
    $projectRepo->update($p1, [
        'name'         => 'Integration Alpha Updated',
        'code'         => $pCode1,
        'description'  => 'Updated description',
        'project_type' => 'Client',
        'status'       => 'completed',
        'priority'     => 'critical',
        'progress'     => 100,
        'start_date'   => date('Y-m-d'),
        'end_date'     => date('Y-m-d', strtotime('+45 days')),
        'owner_id'     => null,
    ]);
    $updated = $projectRepo->find($p1);
    check($updated['name'] === 'Integration Alpha Updated', "Project name updated successfully");
    check($updated['status'] === 'completed', "Project status updated to completed");
    check((int)$updated['progress'] === 100, "Project progress updated to 100%");

    // 1.4 Soft delete
    $projectRepo->softDelete($p1);
    check($projectRepo->find($p1) === null, "find() returns null for soft-deleted project");
    $rawDeleted = $pdo->query("SELECT * FROM projects WHERE id = $p1")->fetch();
    check($rawDeleted !== false && $rawDeleted['deleted_at'] !== null, "Soft delete preserves record with deleted_at timestamp in database");
    check($projectRepo->find(999999) === null, "find() returns null for non-existent project ID");

    // ---------------------------------------------------------
    // AREA 2: Project Listing & Filters & Permissions (Issue #102)
    // ---------------------------------------------------------
    echo "\n--- Area 2: Project Listing, Filters, and Permissions ---\n";

    // Create a set of projects for filter testing
    $pActHigh = createTestProject($projectRepo, ['code' => 'IV_ACT_H', 'name' => 'Searchable Engine Alpha', 'status' => 'active', 'priority' => 'high']);
    $pActLow  = createTestProject($projectRepo, ['code' => 'IV_ACT_L', 'name' => 'Searchable Database Beta', 'status' => 'active', 'priority' => 'low']);
    $pPlnHigh = createTestProject($projectRepo, ['code' => 'IV_PLN_H', 'name' => 'Searchable API Gamma', 'status' => 'planning', 'priority' => 'high']);

    // 2.1 Filter by Status
    $activeFilter = $projectRepo->search(['status' => 'active']);
    $activeIds = array_column($activeFilter, 'id');
    check(in_array($pActHigh, $activeIds) && in_array($pActLow, $activeIds), "search(status='active') includes active projects");
    check(!in_array($pPlnHigh, $activeIds), "search(status='active') strictly excludes planning projects");

    // 2.2 Filter by Priority
    $highFilter = $projectRepo->search(['priority' => 'high']);
    $highIds = array_column($highFilter, 'id');
    check(in_array($pActHigh, $highIds) && in_array($pPlnHigh, $highIds), "search(priority='high') includes high priority projects");
    check(!in_array($pActLow, $highIds), "search(priority='high') excludes low priority projects");

    // 2.3 Search by Query text (q)
    $qResults = $projectRepo->search(['q' => 'Engine Alpha']);
    $qIds = array_column($qResults, 'id');
    check(in_array($pActHigh, $qIds), "search(q='Engine Alpha') finds project by name substring");
    check(!in_array($pActLow, $qIds), "search(q='Engine Alpha') excludes non-matching projects");

    // 2.4 Combined Filters
    $combResults = $projectRepo->search(['status' => 'active', 'priority' => 'high']);
    $combIds = array_column($combResults, 'id');
    check(in_array($pActHigh, $combIds), "Combined status+priority filter includes matching project");
    check(!in_array($pActLow, $combIds) && !in_array($pPlnHigh, $combIds), "Combined filter excludes mismatched projects");

    // 2.5 Role Permissions Check
    $permRepo = new PermissionRepository();
    check($permRepo->can('admin', 'projects.create') && $permRepo->can('admin', 'settings.manage'), "Admin role has full access including settings.manage");
    check($permRepo->can('manager', 'projects.create') && !$permRepo->can('manager', 'settings.manage'), "Manager can create projects but cannot manage settings");
    check(!$permRepo->can('member', 'projects.delete') && !$permRepo->can('member', 'team.manage'), "Member cannot delete projects or manage team members");

    // ---------------------------------------------------------
    // AREA 3: Project Teams Integration (Issue #103)
    // ---------------------------------------------------------
    echo "\n--- Area 3: Project Teams Integration via project_members ---\n";

    // Create 2 test users
    $stmtU = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, avatar_color) VALUES (?, ?, 'hash', 'member', 'active', ?)");
    $stmtU->execute(['IV User Alice', 'iv105_alice@pms.test', '#3b82f6']);
    $uAlice = (int)$pdo->lastInsertId();
    $stmtU->execute(['IV User Bob', 'iv105_bob@pms.test', '#10b981']);
    $uBob = (int)$pdo->lastInsertId();

    $pTeam = createTestProject($projectRepo, ['code' => 'IV_TEAM_01', 'name' => 'Team Collaboration Project', 'status' => 'active']);

    // 3.1 Initial membership check
    check(!$projectRepo->isMember($pTeam, $uAlice), "isMember returns false before adding user");

    // 3.2 Add Member
    $addAlice = $projectRepo->addMember($pTeam, $uAlice, 'Lead');
    check($addAlice === true, "addMember returns true on success");
    check($projectRepo->isMember($pTeam, $uAlice), "isMember returns true after adding user");

    // 3.3 Prevent Duplicate Membership
    $dupAdd = $projectRepo->addMember($pTeam, $uAlice, 'Member');
    check($dupAdd === false, "addMember prevents duplicate membership and returns false");

    // 3.4 Members List with avatar and role
    $members = $projectRepo->members($pTeam);
    $memberUserIds = array_column($members, 'user_id');
    check(in_array($uAlice, $memberUserIds), "members() returns Alice as active project member");
    check($members[0]['role_in_project'] === 'Lead', "members() returns correct role_in_project (Lead)");
    check(!empty($members[0]['avatar_color']), "members() returns user avatar color");

    // 3.5 Candidate Members (Non-members)
    $candidates = $projectRepo->candidateMembers($pTeam);
    $candidateIds = array_column($candidates, 'id');
    check(!in_array($uAlice, $candidateIds), "candidateMembers excludes Alice who is already a member");
    check(in_array($uBob, $candidateIds), "candidateMembers includes Bob who is not yet a member");

    // 3.6 Remove Member
    $remAlice = $projectRepo->removeMember($pTeam, $uAlice);
    check($remAlice === true, "removeMember returns true on success");
    check(!$projectRepo->isMember($pTeam, $uAlice), "isMember returns false after removal");

    // 3.7 Cannot add member to soft-deleted project
    $pDeletedProj = createTestProject($projectRepo, ['code' => 'IV_DEL_PRJ', 'name' => 'Deleted Project']);
    $projectRepo->softDelete($pDeletedProj);
    $addDeleted = $projectRepo->addMember($pDeletedProj, $uAlice, 'Member');
    check($addDeleted === false, "addMember rejects adding member to soft-deleted project");
    check($projectRepo->addMember($pTeam, 999999, 'Member') === false, "addMember rejects non-existent user ID");

    // ---------------------------------------------------------
    // AREA 4: Task Linking & Project Lifecycle (Issue #103)
    // ---------------------------------------------------------
    echo "\n--- Area 4: Task Linking & Cascade Soft-Delete Isolation ---\n";

    $pTaskProj = createTestProject($projectRepo, ['code' => 'IV_TSK_PRJ', 'name' => 'Task Container Project', 'status' => 'active']);

    // Create tasks
    $stmtT = $pdo->prepare("INSERT INTO tasks (project_id, title, status, priority, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmtT->execute([$pTaskProj, 'IV105_Task_A', 'in_progress', 'high', date('Y-m-d', strtotime('+7 days'))]);
    $tA = (int)$pdo->lastInsertId();
    $stmtT->execute([$pTaskProj, 'IV105_Task_B', 'done', 'medium', date('Y-m-d')]);
    $tB = (int)$pdo->lastInsertId();

    // 4.1 forProject query
    $tasksForP = $taskRepo->forProject($pTaskProj);
    $tTitles = array_column($tasksForP, 'title');
    check(in_array('IV105_Task_A', $tTitles) && in_array('IV105_Task_B', $tTitles), "forProject retrieves all non-deleted project tasks");

    // 4.2 Exclude tasks of soft-deleted projects
    $openBefore = $taskRepo->countByStatusNot('done');
    $doneBefore = $taskRepo->countByStatus('done');

    $projectRepo->softDelete($pTaskProj);

    $openAfter = $taskRepo->countByStatusNot('done');
    $doneAfter = $taskRepo->countByStatus('done');
    check($openAfter === $openBefore - 1, "countByStatusNot('done') excludes tasks of soft-deleted project");
    check($doneAfter === $doneBefore - 1, "countByStatus('done') excludes tasks of soft-deleted project");

    $tasksAfterDel = $taskRepo->forProject($pTaskProj);
    check(empty($tasksAfterDel), "forProject returns empty for soft-deleted project");

    // 4.3 Direct database cascade check on tasks table
    $cascadedTask = $pdo->query("SELECT deleted_at FROM tasks WHERE id = $tA")->fetch();
    check($cascadedTask !== false && $cascadedTask['deleted_at'] !== null, "softDelete cascades deleted_at timestamp directly to tasks in database");

    // ---------------------------------------------------------
    // AREA 5: Dashboard Metrics Alignment (Issue #104)
    // ---------------------------------------------------------
    echo "\n--- Area 5: Dashboard Metrics Alignment & Accuracy ---\n";

    // Setup fresh projects for dashboard verification
    $dAct1 = createTestProject($projectRepo, ['code' => 'IV_D_ACT1', 'name' => 'Dash Active 1', 'status' => 'active']);
    $dAct2 = createTestProject($projectRepo, ['code' => 'IV_D_ACT2', 'name' => 'Dash Active 2', 'status' => 'active']);
    $dPln  = createTestProject($projectRepo, ['code' => 'IV_D_PLN',  'name' => 'Dash Planning',  'status' => 'planning']);
    $dCmp  = createTestProject($projectRepo, ['code' => 'IV_D_CMP',  'name' => 'Dash Completed', 'status' => 'completed']);
    $dHld  = createTestProject($projectRepo, ['code' => 'IV_D_HLD',  'name' => 'Dash On Hold',   'status' => 'on_hold']);
    $dCan  = createTestProject($projectRepo, ['code' => 'IV_D_CAN',  'name' => 'Dash Cancelled', 'status' => 'cancelled']);

    // 5.1 activeProjects strictly returns active projects only
    $dashActive = $projectRepo->activeProjects(10);
    $dashActiveCodes = array_column($dashActive, 'code');
    check(in_array('IV_D_ACT1', $dashActiveCodes) && in_array('IV_D_ACT2', $dashActiveCodes), "activeProjects contains active projects");
    check(!in_array('IV_D_PLN', $dashActiveCodes), "activeProjects strictly excludes planning project");
    check(!in_array('IV_D_CMP', $dashActiveCodes), "activeProjects strictly excludes completed project");
    check(!in_array('IV_D_HLD', $dashActiveCodes), "activeProjects strictly excludes on_hold project");
    check(!in_array('IV_D_CAN', $dashActiveCodes), "activeProjects strictly excludes cancelled project");

    // 5.2 totalCount and statusCounts
    $tCount = $projectRepo->totalCount();
    $sCounts = $projectRepo->statusCounts();
    check(isset($sCounts['planning'], $sCounts['active'], $sCounts['on_hold'], $sCounts['completed'], $sCounts['cancelled']), "statusCounts provides all 5 status keys");
    check(array_sum($sCounts) === $tCount, "Sum of statusCounts matches totalCount exactly");

    // 5.3 Overdue Tasks
    $initialOverdue = $taskRepo->countOverdue();
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Overdue task 1
    $stmtT->execute([$dAct1, 'IV105_Overdue_Task', 'in_progress', 'high', $yesterday]);
    $tOverdue = (int)$pdo->lastInsertId();
    check($taskRepo->countOverdue() === $initialOverdue + 1, "countOverdue increments for past-due uncompleted task");

    // Mark task done -> overdue decreases
    $pdo->prepare("UPDATE tasks SET status = 'done' WHERE id = ?")->execute([$tOverdue]);
    check($taskRepo->countOverdue() === $initialOverdue, "countOverdue decrements when overdue task is marked done");

    // Add overdue task then soft delete its project
    $stmtT->execute([$dAct2, 'IV105_Overdue_Cascade', 'todo', 'high', $yesterday]);
    $tOverdueCascade = (int)$pdo->lastInsertId();
    check($taskRepo->countOverdue() === $initialOverdue + 1, "countOverdue increments for second overdue task");

    $projectRepo->softDelete($dAct2);
    check($taskRepo->countOverdue() === $initialOverdue, "countOverdue excludes overdue tasks of soft-deleted projects");

    // ---------------------------------------------------------
    // AREA 6: Full HTTP Integration & Page Verification
    // ---------------------------------------------------------
    echo "\n--- Area 6: HTTP Live Web Application Endpoints ---\n";

    $cookieFile = tempnam(sys_get_temp_dir(), 'pms_iv105_');
    $baseUrl = 'http://localhost/PMS';

    // 6.1 Create test admin user and authenticate
    $stmtAdmin = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, avatar_color) VALUES ('IV Admin', 'iv105_admin@pms.test', :hash, 'admin', 'active', '#ef4444')");
    $adminPassword = 'Password123!';
    $stmtAdmin->execute(['hash' => password_hash($adminPassword, PASSWORD_DEFAULT)]);
    $adminId = (int)$pdo->lastInsertId();

    $ch = curl_init("$baseUrl/modules/auth/login.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $loginPage = curl_exec($ch);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
    $csrf = $m[1] ?? '';

    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/modules/auth/login.php",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $csrf,
            'email' => 'iv105_admin@pms.test',
            'password' => $adminPassword,
        ]),
    ]);
    $loginResp = curl_exec($ch);
    $loginUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    check(strpos($loginUrl, 'dashboard') !== false, "HTTP login succeeds and redirects to dashboard");

    // 6.2 Dashboard HTTP response
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/modules/dashboard/index.php",
        CURLOPT_POST => false,
    ]);
    $dashContent = curl_exec($ch);
    $dashCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    check($dashCode === 200, "Dashboard loads with HTTP 200 OK");
    check(strpos($dashContent, 'Active Projects') !== false, "Dashboard renders Active Projects card");
    check(strpos($dashContent, 'Overdue Tasks') !== false, "Dashboard renders Overdue Tasks card");
    check(strpos($dashContent, 'Project Status Breakdown') !== false, "Dashboard renders Project Status Breakdown bar");
    check(strpos($dashContent, 'Planning:') !== false && strpos($dashContent, 'Active:') !== false, "Dashboard renders complete status legend");

    // 6.3 Projects List HTTP response
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/modules/projects/index.php",
        CURLOPT_POST => false,
    ]);
    $listContent = curl_exec($ch);
    $listCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    check($listCode === 200, "Projects list loads with HTTP 200 OK");
    check(strpos($listContent, 'name="status"') !== false, "Projects list contains Status filter dropdown");
    check(strpos($listContent, 'name="priority"') !== false, "Projects list contains Priority filter dropdown");
    check(strpos($listContent, '+ New Project') !== false, "Admin sees '+ New Project' action button");

    // 6.4 Project Details HTTP response
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/modules/projects/view.php?id=$dAct1",
        CURLOPT_POST => false,
    ]);
    $viewContent = curl_exec($ch);
    $viewCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    check($viewCode === 200, "Project view loads with HTTP 200 OK");
    check(strpos($viewContent, 'Team') !== false, "Project view renders Team section");
    check(strpos($viewContent, 'Add Member') !== false || strpos($viewContent, 'add_member') !== false, "Project view includes Add Member capability");
    check(strpos($viewContent, 'Tasks') !== false, "Project view renders Tasks section");

    curl_close($ch);
    @unlink($cookieFile);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$adminId]);

} catch (Throwable $e) {
    echo "  [\033[31mEXCEPTION\033[0m] " . $e->getMessage() . "\n";
    $failed++;
    $failures[] = "Exception: " . $e->getMessage();
} finally {
    $cleanup();
}

echo "\n====================================================================\n";
echo "                      INTEGRATION TEST SUMMARY                      \n";
echo "====================================================================\n";
echo "Total Verification Checks: $total\n";
echo "Passed: \033[32m$passed\033[0m\n";
echo "Failed: " . ($failed > 0 ? "\033[31m$failed\033[0m" : "0") . "\n";

if ($failed > 0) {
    echo "\nFailure Details:\n";
    foreach ($failures as $f) {
        echo " - $f\n";
    }
}

exit($failed === 0 ? 0 : 1);
