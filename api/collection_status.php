<?php
/**
 * Rani Mobiles ERP — Collection Target Status API
 * Called by APK WorkManager at 6 PM to check daily collection status.
 */
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user      = current_user();
$branch_id = $user['branch_id'];
$pdo       = db();
$today     = date('Y-m-d');

update_presence();

if (can_see_all_branches()) {
    $branchList = $pdo->query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
} else {
    $q = $pdo->prepare("SELECT * FROM branches WHERE id=?");
    $q->execute([$branch_id]);
    $branchList = $q->fetchAll();
}

$result = [];
foreach ($branchList as $b) {
    $bid = $b['id'];

    $q = $pdo->prepare("SELECT daily_target FROM branch_collection_targets WHERE branch_id=?");
    $q->execute([$bid]);
    $target = (float)($q->fetchColumn() ?: 0);

    $q2 = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM cash_handovers WHERE branch_id=? AND business_date=? AND status='CONFIRMED'"
    );
    $q2->execute([$bid, $today]);
    $confirmed = (float)$q2->fetchColumn();

    $q3 = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date=?");
    $q3->execute([$bid, $today]);
    $sales_total = (float)$q3->fetchColumn();

    $remaining = max(0, $target - $confirmed);

    // Suppress reminder when there are no sales (closed/no-activity day)
    $suppress = ($sales_total == 0);

    $result[] = [
        'branch_id'          => $bid,
        'branch_code'        => $b['code'],
        'target'             => $target,
        'confirmed'          => $confirmed,
        'remaining'          => $remaining,
        'sales_total'        => $sales_total,
        'suppress_reminder'  => $suppress,
        'alert'              => !$suppress && $remaining > 0,
    ];
}

echo json_encode(['date' => $today, 'branches' => $result]);
