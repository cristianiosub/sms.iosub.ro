<?php
function renderReports(): void {
    $client   = requireClient();
    $dateFrom = $_GET['from'] ?? date('Y-m-01');
    $dateTo   = $_GET['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

    $summary = DB::fetchOne(
        'SELECT COUNT(*) as total,
                SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN(32,16,64) THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN(0,1,8) THEN 1 ELSE 0 END) as pending,
                SUM(parts) as total_parts,
                SUM(cost) as total_cost
         FROM messages WHERE client_id=? AND DATE(created_at) BETWEEN ? AND ?',
        [$client['id'], $dateFrom, $dateTo]
    );

    $daily = DB::fetchAll(
        'SELECT DATE(created_at) as day,
                COUNT(*) as total,
                SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN(32,16,64) THEN 1 ELSE 0 END) as failed
         FROM messages WHERE client_id=? AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY day ASC',
        [$client['id'], $dateFrom, $dateTo]
    );

    $byCampaign = DB::fetchAll(
        'SELECT COALESCE(c.name, "Quick SMS") as name,
                COUNT(m.id) as total,
                SUM(CASE WHEN m.status=4 THEN 1 ELSE 0 END) as delivered,
                SUM(m.cost) as cost
         FROM messages m LEFT JOIN campaigns c ON c.id=m.campaign_id
         WHERE m.client_id=? AND DATE(m.created_at) BETWEEN ? AND ?
         GROUP BY m.campaign_id ORDER BY total DESC LIMIT 20',
        [$client['id'], $dateFrom, $dateTo]
    );

    $syncableCount = (int)DB::fetchOne(
        'SELECT COUNT(*) as n FROM messages
         WHERE client_id=? AND sendsms_uuid IS NOT NULL AND sendsms_uuid!=""
           AND ((status IN(0,1,8) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
             OR (status=4 AND (cost IS NULL OR cost=0) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))
           AND DATE(created_at) BETWEEN ? AND ?',
        [$client['id'], $dateFrom, $dateTo]
    )['n'];

    require ROOT . '/templates/reports.php';
}

function contactReport(): void {
    $client = requireClient();
    $phone  = trim($_GET['phone'] ?? '');
    if (!$phone) { redirect('/reports'); return; }
    $phone    = SendSmsApi::normalizePhone($phone);
    $messages = DB::fetchAll(
        'SELECT m.*, c.name as campaign_name FROM messages m
         LEFT JOIN campaigns c ON c.id=m.campaign_id
         WHERE m.client_id=? AND m.phone=? ORDER BY m.created_at DESC',
        [$client['id'], $phone]
    );
    require ROOT . '/templates/contact_report.php';
}

function deleteFailedMessages(): void {
    requirePost();
    if (!Auth::csrfVerify()) { jsonResponse(['error' => 'CSRF'], 403); return; }
    $client   = requireClient();
    $dateFrom = $_POST['from'] ?? date('Y-m-01');
    $dateTo   = $_POST['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');
    $deleted = DB::execute(
        'DELETE FROM messages WHERE client_id=? AND status IN(32,16,64) AND DATE(created_at) BETWEEN ? AND ?',
        [$client['id'], $dateFrom, $dateTo]
    );
    jsonResponse(['success' => true, 'deleted' => $deleted]);
}

function syncCostsNow(): void {
    requirePost();
    if (!Auth::csrfVerify()) { jsonResponse(['error' => 'CSRF'], 403); return; }
    $client = requireClient();

    $messages = DB::fetchAll(
        'SELECT m.id, m.sendsms_uuid, m.provider_msg_id, m.provider, m.status, m.cost
         FROM messages m
         WHERE m.client_id=? AND m.sendsms_uuid IS NOT NULL AND m.sendsms_uuid!=""
           AND ((m.status IN(0,1,8) AND m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
             OR (m.status=4 AND (m.cost IS NULL OR m.cost=0) AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))
         ORDER BY m.created_at DESC LIMIT 50',
        [$client['id']]
    );

    if (empty($messages)) {
        jsonResponse(['success' => true, 'checked' => 0, 'synced_cost' => 0, 'updated_status' => 0, 'message' => 'Totul este la zi']);
        return;
    }

    $api           = getApiForClient($client);
    $syncedCost    = 0;
    $updatedStatus = 0;

    foreach ($messages as $msg) {
        try {
            $result = $api->messageStatus($msg['sendsms_uuid']);
            $parsed = $api->parseMessageStatus($result);
            $newStatus = $parsed['status'];
            $cost      = $parsed['cost'];
            $parts     = $parsed['parts'];

            if ($newStatus <= 0) { usleep(200000); continue; }

            if ($newStatus === 4) {
                $sets = ['status=4', 'delivered_at=COALESCE(delivered_at,NOW())'];
                $p    = [];
                if ($cost !== null && $cost > 0) {
                    $sets[] = 'cost=?'; $sets[] = 'parts=?';
                    $p[] = $cost; $p[] = $parts;
                    $syncedCost++;
                }
                $p[] = $msg['id'];
                DB::execute('UPDATE messages SET '.implode(',', $sets).' WHERE id=?', $p);
                if ((int)$msg['status'] !== 4) $updatedStatus++;
            } elseif (in_array($newStatus, [16,32,64])) {
                DB::execute('UPDATE messages SET status=?, failed_at=COALESCE(failed_at,NOW()) WHERE id=?', [$newStatus, $msg['id']]);
                if ((int)$msg['status'] !== $newStatus) $updatedStatus++;
            } elseif (in_array($newStatus, [1,8])) {
                if ((int)$msg['status'] !== $newStatus) {
                    DB::execute('UPDATE messages SET status=? WHERE id=?', [$newStatus, $msg['id']]);
                    $updatedStatus++;
                }
            }
            usleep(150000);
        } catch (Exception $e) {
            error_log('syncCostsNow error msg#'.$msg['id'].': '.$e->getMessage());
        }
    }

    jsonResponse(['success' => true, 'checked' => count($messages), 'synced_cost' => $syncedCost, 'updated_status' => $updatedStatus]);
}

function exportReportCsv(): void {
    $client   = requireClient();
    $dateFrom = $_GET['from'] ?? date('Y-m-01');
    $dateTo   = $_GET['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

    $messages = DB::fetchAll(
        'SELECT m.phone, m.message_text, m.status, m.cost, m.parts, m.provider,
                m.sent_at, m.delivered_at, m.failed_at, m.created_at,
                COALESCE(c.name, "Quick SMS") as campaign_name
         FROM messages m
         LEFT JOIN campaigns c ON c.id=m.campaign_id
         WHERE m.client_id=? AND DATE(m.created_at) BETWEEN ? AND ?
         ORDER BY m.created_at DESC',
        [$client['id'], $dateFrom, $dateTo]
    );

    $statusLabels = [0=>'Pending',1=>'Trimis',4=>'Livrat',8=>'La retea',16=>'Esuat retea',32=>'Esuat',64=>'Filtrat'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="raport-' . $dateFrom . '_' . $dateTo . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Telefon','Campanie','Mesaj','Status','Cost (EUR)','Parts','Provider','Creat','Trimis','Livrat']);
    foreach ($messages as $m) {
        fputcsv($out, [
            $m['phone'], $m['campaign_name'], $m['message_text'],
            $statusLabels[(int)$m['status']] ?? $m['status'],
            $m['cost'] ? number_format((float)$m['cost'], 5, '.', '') : '',
            $m['parts'], $m['provider'],
            $m['created_at'], $m['sent_at'] ?? '', $m['delivered_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
