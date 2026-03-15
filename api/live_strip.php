<?php
/**
 * Rani Mobiles ERP — Live Strip API
 * GET /api/live_strip.php?scope=branch|super
 * Returns JSON with dashboard KPIs.
 */
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    // Try token auth for APK
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $pdo = db();
    $q = $pdo->prepare(
        "SELECT u.*, b.name AS branch_name, r.name AS role_name
         FROM users u
         JOIN branches b ON b.id = u.branch_id
         JOIN roles    r ON r.id = u.role_id
         WHERE u.api_token = ? AND u.is_active = 1"
    );
    $q->execute([$token]);
    $tokenUser = $q->fetch();
    if (!$tokenUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    // Regenerate session to prevent session fixation
    session_regenerate_id(true);
    $_SESSION['user_id']   = $tokenUser['id'];
    $_SESSION['branch_id'] = $tokenUser['branch_id'];
    $_SESSION['role']      = $tokenUser['role_name'];
    $_SESSION['branch']    = $tokenUser['branch_name'];
}

$user      = current_user();
$branch_id = $user['branch_id'];
$pdo       = db();
$today     = date('Y-m-d');
$scope     = $_GET['scope'] ?? 'branch';

update_presence();

if ($scope === 'super' && can_see_all_branches()) {
    // Supervisor strip — all branches
    $data     = [];
    $branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY id")->fetchAll();

    foreach ($branches as $b) {
        $bid = $b['id'];

        $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date=?");
        $q->execute([$bid, $today]);
        $sales = (float)$q->fetchColumn();

        $q2 = $pdo->prepare(
            "SELECT COALESCE(SUM(s.qty * p.purchase_cost),0)
             FROM stock s JOIN products p ON p.id=s.product_id
             WHERE s.branch_id=?"
        );
        $q2->execute([$bid]);
        $stock_val = (float)$q2->fetchColumn();

        $q3 = $pdo->prepare("SELECT * FROM day_close WHERE branch_id=? AND business_date=?");
        $q3->execute([$bid, $today]);
        $dc      = $q3->fetch();
        $counted = $dc ? (float)$dc['counted_cash'] : 0;

        $q4 = $pdo->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM cash_handovers WHERE branch_id=? AND business_date=? AND status='CONFIRMED'"
        );
        $q4->execute([$bid, $today]);
        $confirmed = (float)$q4->fetchColumn();

        $q5 = $pdo->prepare(
            "SELECT COUNT(*) FROM service_jobs WHERE branch_id=? AND status NOT IN ('delivered','DELIVERED')"
        );
        $q5->execute([$bid]);
        $service_pending = (int)$q5->fetchColumn();

        $q6 = $pdo->prepare(
            "SELECT COUNT(*) FROM alerts WHERE branch_id=? AND status='OPEN' AND alert_type='SHORT'"
        );
        $q6->execute([$bid]);
        $reorder_count = (int)$q6->fetchColumn();

        $data[] = [
            'branch_id'       => $bid,
            'branch_code'     => $b['code'],
            'branch_name'     => $b['name'],
            'sales_total'     => $sales,
            'stock_value'     => $stock_val,
            'counted_cash'    => $counted,
            'confirmed_cash'  => $confirmed,
            'pending_cash'    => $counted - $confirmed,
            'service_pending' => $service_pending,
            'reorder_alerts'  => $reorder_count,
        ];
    }

    echo json_encode(['scope' => 'super', 'date' => $today, 'branches' => $data]);
} else {
    // Branch strip
    $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date=?");
    $q->execute([$branch_id, $today]);
    $sales = (float)$q->fetchColumn();

    $q2 = $pdo->prepare(
        "SELECT COALESCE(SUM(s.qty * p.purchase_cost),0)
         FROM stock s JOIN products p ON p.id=s.product_id
         WHERE s.branch_id=?"
    );
    $q2->execute([$branch_id]);
    $stock_val = (float)$q2->fetchColumn();

    $q3 = $pdo->prepare("SELECT * FROM day_close WHERE branch_id=? AND business_date=?");
    $q3->execute([$branch_id, $today]);
    $dc = $q3->fetch();

    $q4 = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM cash_handovers WHERE branch_id=? AND business_date=? AND status='CONFIRMED'"
    );
    $q4->execute([$branch_id, $today]);
    $confirmed = (float)$q4->fetchColumn();

    $q5 = $pdo->prepare(
        "SELECT COUNT(*) FROM service_jobs WHERE branch_id=? AND status NOT IN ('delivered','DELIVERED')"
    );
    $q5->execute([$branch_id]);
    $service_pending = (int)$q5->fetchColumn();

    $q6 = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE branch_id=? AND status='OPEN'");
    $q6->execute([$branch_id]);
    $open_alerts = (int)$q6->fetchColumn();

    $q7 = $pdo->prepare("SELECT daily_target FROM branch_collection_targets WHERE branch_id=?");
    $q7->execute([$branch_id]);
    $target = (float)($q7->fetchColumn() ?: 0);

    // Active staff (seen in last 5 minutes)
    $q8 = $pdo->prepare(
        "SELECT u.full_name, up.last_seen
         FROM user_presence up
         JOIN users u ON u.id = up.user_id
         WHERE up.branch_id=? AND up.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $q8->execute([$branch_id]);
    $active_staff = $q8->fetchAll(PDO::FETCH_ASSOC);

    $counted = $dc ? (float)$dc['counted_cash'] : 0;

    echo json_encode([
        'scope'              => 'branch',
        'branch_id'          => $branch_id,
        'date'               => $today,
        'sales_total'        => $sales,
        'stock_value'        => $stock_val,
        'counted_cash'       => $counted,
        'confirmed_cash'     => $confirmed,
        'pending_cash'       => $counted - $confirmed,
        'service_pending'    => $service_pending,
        'open_alerts'        => $open_alerts,
        'collection_target'  => $target,
        'active_staff'       => $active_staff,
    ]);
}
