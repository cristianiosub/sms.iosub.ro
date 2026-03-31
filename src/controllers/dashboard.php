<?php
function renderDashboard(): void {
    $client = requireClient();
    $api    = getApiForClient($client);

    $stats = DB::fetchOne(
        'SELECT COUNT(*) as total_messages,
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN (32,16) THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as sent,
                SUM(cost) as total_cost
         FROM messages WHERE client_id = ?',
        [$client['id']]
    );

    $statsToday = DB::fetchOne(
        'SELECT COUNT(*) as total, SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered
         FROM messages WHERE client_id = ? AND DATE(created_at) = CURDATE()',
        [$client['id']]
    );

    $statsMonth = DB::fetchOne(
        'SELECT COUNT(*) as total, SUM(cost) as cost
         FROM messages WHERE client_id = ? AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())',
        [$client['id']]
    );

    $recentCampaigns = DB::fetchAll(
        'SELECT * FROM campaigns WHERE client_id = ? ORDER BY created_at DESC LIMIT 5',
        [$client['id']]
    );

    $recentMessages = DB::fetchAll(
        'SELECT m.*, c.name as campaign_name FROM messages m
         LEFT JOIN campaigns c ON c.id = m.campaign_id
         WHERE m.client_id = ? ORDER BY m.created_at DESC LIMIT 10',
        [$client['id']]
    );

    $chartData = DB::fetchAll(
        'SELECT DATE(created_at) as day, COUNT(*) as total,
                SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered
         FROM messages WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at) ORDER BY day',
        [$client['id']]
    );

    $balance = null;
    try {
        $balRes = $api->getBalance();
        if (isset($balRes['details'])) $balance = $balRes['details'];
    } catch (Exception $e) {}

    require ROOT . '/templates/dashboard.php';
}
