<?php
function listCampaigns(): void {
    $client = requireClient();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20; $offset = ($page-1)*$limit;
    $status = $_GET['status'] ?? '';
    $where  = 'WHERE client_id=?'; $params = [$client['id']];
    if ($status) { $where .= ' AND status=?'; $params[] = $status; }
    $total     = (int)DB::fetchOne("SELECT COUNT(*) as n FROM campaigns $where", $params)['n'];
    $campaigns = DB::fetchAll("SELECT * FROM campaigns $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);
    require ROOT . '/templates/campaigns_list.php';
}

function newCampaign(): void {
    $client    = requireClient();
    $lists     = DB::fetchAll('SELECT id, name, total_contacts FROM contact_lists WHERE client_id=? ORDER BY name', [$client['id']]);
    $templates = DB::fetchAll('SELECT id, name, body FROM message_templates WHERE client_id=? ORDER BY name', [$client['id']]);
    require ROOT . '/templates/campaign_new.php';
}

function createCampaign(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    $name        = trim($_POST['name'] ?? '');
    $listId      = (int)($_POST['list_id'] ?? 0);
    $messageText = trim($_POST['message_text'] ?? '');
    $sender      = trim($_POST['sender'] ?? $client['default_sender'] ?? '');
    $scheduledAt = trim($_POST['scheduled_at'] ?? '');
    $batchSize   = max(0, (int)($_POST['batch_size'] ?? 0));
    $batchIntvl  = max(0, (int)($_POST['batch_interval'] ?? 0));

    if (!$name || !$messageText) { jsonResponse(['error' => 'Campuri obligatorii lipsa'], 422); return; }

    $list = null;
    if ($listId) {
        $list = DB::fetchOne('SELECT id, total_contacts FROM contact_lists WHERE id=? AND client_id=?', [$listId, $client['id']]);
        if (!$list) { jsonResponse(['error' => 'Lista invalida'], 422); return; }
    }

    $status      = $scheduledAt ? 'scheduled' : 'draft';
    $scheduledDt = $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null;

    $id = DB::insert(
        'INSERT INTO campaigns (client_id,list_id,name,message_text,sender,status,scheduled_at,total_recipients,batch_size,batch_interval,created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$client['id'], $listId ?: null, $name, $messageText, $sender, $status, $scheduledDt,
         $list ? (int)$list['total_contacts'] : 0, $batchSize, $batchIntvl, Auth::userId()]
    );
    audit('create_campaign', 'campaign', (string)$id);
    redirect('/campaigns/' . $id);
}

function viewCampaign(int $id): void {
    $client   = requireClient();
    $campaign = DB::fetchOne('SELECT * FROM campaigns WHERE id=? AND client_id=?', [$id, $client['id']]);
    if (!$campaign) { http_response_code(404); require ROOT . '/templates/404.php'; return; }

    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = 100; $offset = ($page-1)*$limit;
    $totalMsg = (int)DB::fetchOne('SELECT COUNT(*) as n FROM messages WHERE campaign_id=?', [$id])['n'];
    $messages = DB::fetchAll('SELECT * FROM messages WHERE campaign_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?', [$id, $limit, $offset]);
    $firstMsg = DB::fetchOne('SELECT provider FROM messages WHERE campaign_id=? LIMIT 1', [$id]);
    $campaignProvider = $firstMsg['provider'] ?? $client['sms_provider'] ?? 'sendsms';
    $msgStats = DB::fetchOne(
        'SELECT COUNT(*) as total,
                SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN(32,16,64) THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN(0,1,8) THEN 1 ELSE 0 END) as pending,
                SUM(cost) as total_cost
         FROM messages WHERE campaign_id=?', [$id]
    );
    require ROOT . '/templates/campaign_view.php';
}

function sendCampaign(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client   = requireClient();
    $campaign = DB::fetchOne('SELECT * FROM campaigns WHERE id=? AND client_id=?', [$id, $client['id']]);

    if (!$campaign || !in_array($campaign['status'], ['draft','scheduled','paused'])) {
        jsonResponse(['error' => 'Campanie invalida sau deja trimisa'], 422); return;
    }
    if (!$campaign['list_id']) {
        jsonResponse(['error' => 'Campania nu are o lista asociata'], 422); return;
    }

    DB::execute('UPDATE campaigns SET status="running", started_at=NOW() WHERE id=?', [$id]);
    $contacts  = DB::fetchAll('SELECT * FROM contacts WHERE list_id=? AND is_blocked=0', [$campaign['list_id']]);
    $sender    = $campaign['sender'] ?? $client['default_sender'] ?? '';
    $batchSize = (int)$campaign['batch_size'];
    $sent = 0; $failed = 0; $blocked = 0;

    DB::beginTransaction();
    try {
        foreach ($contacts as $i => $contact) {
            // Rate limiting intre loturi
            if ($batchSize > 0 && $i > 0 && $i % $batchSize === 0) {
                sleep(max(1, (int)$campaign['batch_interval']));
            }

            // Interpoleaza mesaj cu toate variabilele inclusiv {optout_url}
            $text = interpolateMessage($campaign['message_text'], $contact, $client['id']);

            $r = ProviderFactory::sendAndSave($client, $contact['phone'], $text, $sender, $id, $contact['id']);

            if ($r['success']) { $sent++; }
            elseif ($r['blocked'] ?? false) { $blocked++; }
            else { $failed++; }
        }

        DB::execute(
            'UPDATE campaigns SET status="completed", completed_at=NOW(), sent_count=?, failed_count=?, total_recipients=? WHERE id=?',
            [$sent, $failed, count($contacts), $id]
        );
        DB::commit();
        audit('send_campaign', 'campaign', (string)$id);
        jsonResponse(['success' => true, 'sent' => $sent, 'failed' => $failed, 'blocked' => $blocked]);
    } catch (Exception $e) {
        DB::rollback();
        DB::execute('UPDATE campaigns SET status="failed" WHERE id=?', [$id]);
        error_log('Campaign send error: ' . $e->getMessage());
        jsonResponse(['error' => 'Eroare la trimitere: ' . $e->getMessage()], 500);
    }
}

function pauseCampaign(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    DB::execute('UPDATE campaigns SET status="paused" WHERE id=? AND client_id=? AND status="running"', [$id, $client['id']]);
    audit('pause_campaign', 'campaign', (string)$id);
    jsonResponse(['success' => true]);
}

function deleteCampaign(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client   = requireClient();
    $campaign = DB::fetchOne('SELECT status FROM campaigns WHERE id=? AND client_id=?', [$id, $client['id']]);
    if (!$campaign || $campaign['status'] === 'running') {
        jsonResponse(['error' => 'Nu se poate sterge o campanie in desfasurare'], 422); return;
    }
    DB::execute('DELETE FROM campaigns WHERE id=? AND client_id=?', [$id, $client['id']]);
    audit('delete_campaign', 'campaign', (string)$id);
    jsonResponse(['success' => true]);
}

function exportCampaignCsv(int $id): void {
    $client   = requireClient();
    $campaign = DB::fetchOne('SELECT * FROM campaigns WHERE id=? AND client_id=?', [$id, $client['id']]);
    if (!$campaign) { http_response_code(404); exit; }

    $messages = DB::fetchAll(
        'SELECT phone, message_text, status, cost, parts, sendsms_uuid, provider, sent_at, delivered_at, failed_at
         FROM messages WHERE campaign_id=? ORDER BY created_at ASC', [$id]
    );

    $statusLabels = [0=>'Pending',1=>'Trimis',4=>'Livrat',8=>'La retea',16=>'Esuat retea',32=>'Esuat',64=>'Filtrat'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campanie-'.$id.'-'.date('Ymd').'.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Telefon','Mesaj','Status','Cost (EUR)','Parts','UUID','Provider','Trimis la','Livrat la','Esuat la']);
    foreach ($messages as $m) {
        fputcsv($out, [
            $m['phone'], $m['message_text'],
            $statusLabels[(int)$m['status']] ?? $m['status'],
            $m['cost'] ? number_format((float)$m['cost'],5,'.','') : '',
            $m['parts'], $m['sendsms_uuid'] ?? '', $m['provider'] ?? 'sendsms',
            $m['sent_at'] ?? '', $m['delivered_at'] ?? '', $m['failed_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
