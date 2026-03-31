<?php
function apiBalance(): void {
    $client   = requireClient();
    $provider = $client['sms_provider'] ?? 'sendsms';
    try {
        if ($provider === 'smso' && !empty($client['smso_token'])) {
            $api    = new SmsoRo($client['smso_token']);
            $result = $api->getBalance();
            if ($result['success']) {
                jsonResponse(['success' => true, 'balance' => $result['balance'], 'details' => (string)$result['balance'], 'currency' => 'EUR']);
            } else {
                jsonResponse(['success' => false, 'balance' => null, 'details' => null]);
            }
        } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api    = new SmsApiRo($client['smsapi_token']);
            $result = $api->getBalance();
            if ($result['success']) {
                jsonResponse(['success' => true, 'balance' => $result['balance'], 'details' => (string)$result['balance'], 'currency' => 'points']);
            } else {
                jsonResponse(['success' => false, 'balance' => null, 'details' => null]);
            }
        } else {
            $api    = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
            $result = $api->getBalance();
            if (isset($result['status']) && $result['status'] >= 0 && isset($result['details'])) {
                jsonResponse(['success' => true, 'balance' => (float)$result['details'], 'details' => $result['details'], 'currency' => 'EUR']);
            } else {
                jsonResponse(['success' => false, 'balance' => null, 'details' => null, 'error' => $result['message'] ?? 'API error']);
            }
        }
    } catch (Exception $e) {
        error_log('apiBalance error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'balance' => null, 'details' => null]);
    }
}

function apiCampaignStatus(int $id): void {
    $client   = requireClient();
    $campaign = DB::fetchOne(
        'SELECT id, status, sent_count, delivered_count, failed_count, total_recipients FROM campaigns WHERE id=? AND client_id=?',
        [$id, $client['id']]
    );
    if (!$campaign) { jsonResponse(['error' => 'Not found'], 404); return; }
    $pct = $campaign['total_recipients'] > 0
        ? round($campaign['sent_count'] / $campaign['total_recipients'] * 100) : 0;
    jsonResponse(['campaign' => $campaign, 'progress_pct' => $pct]);
}

function apiSendSms(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    // Rate limit: max 10 SMS/minut per client (Quick SMS)
    $recentCount = (int)DB::fetchOne(
        'SELECT COUNT(*) as n FROM messages
         WHERE client_id=? AND campaign_id IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
        [$client['id']]
    )['n'];
    if ($recentCount >= 10) {
        jsonResponse(['error' => 'Limita de 10 SMS/minut depasita. Asteapta putin.'], 429);
        return;
    }

    $phone  = trim($_POST['phone'] ?? '');
    $text   = trim($_POST['text'] ?? '');
    $sender = trim($_POST['sender'] ?? $client['default_sender'] ?? '');
    if (!$phone || !$text) { jsonResponse(['error' => 'Date lipsa'], 422); return; }
    $r = ProviderFactory::sendAndSave($client, $phone, $text, $sender);
    if ($r['success']) {
        jsonResponse(['success' => true, 'uuid' => $r['provider_msg_id'], 'msg_id' => $r['msg_id']]);
    } else {
        jsonResponse(['error' => $r['error'] ?? 'Send failed'], 500);
    }
}

function apiStats(): void {
    $client = requireClient();
    $from   = $_GET['from'] ?? date('Y-m-01');
    $to     = $_GET['to']   ?? date('Y-m-d');
    $daily  = DB::fetchAll(
        'SELECT DATE(created_at) as day, COUNT(*) as total,
                SUM(CASE WHEN status=4 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN(32,16) THEN 1 ELSE 0 END) as failed
         FROM messages WHERE client_id=? AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY day',
        [$client['id'], $from, $to]
    );
    jsonResponse(['daily' => $daily]);
}

function apiGetTemplates(): void {
    $client    = requireClient();
    $templates = DB::fetchAll(
        'SELECT id, name, body, category FROM message_templates WHERE client_id=? ORDER BY name',
        [$client['id']]
    );
    jsonResponse(['templates' => $templates]);
}

/**
 * Returneaza senderii disponibili EXCLUSIV din providerul activ al clientului.
 * Nu amesteca niciodata senderi din provideri diferiti.
 * GET /api/senders
 */
function apiGetSenders(): void {
    $client   = requireClient();
    $provider = $client['sms_provider'] ?? 'sendsms';

    try {
        // Instantiaza EXPLICIT providerul activ — nu prin getApiForClient care poate face fallback
        if ($provider === 'smso' && !empty($client['smso_token'])) {
            $api = new SmsoRo($client['smso_token']);
        } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api = new SmsApiRo($client['smsapi_token']);
        } else {
            $api = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
        }

        $result  = $api->getSenders();
        $senders = $result['senders'] ?? [];

        // Adauga senderul implicit DOAR daca apartine aceluiasi provider
        // (nu adaugam senderi sendsms cand providerul e smso sau invers)
        if (!empty($client['default_sender']) && !in_array($client['default_sender'], $senders)) {
            // Adaugam doar ca fallback daca lista e goala
            if (empty($senders)) {
                $senders[] = $client['default_sender'];
            }
        }

        jsonResponse([
            'success'  => true,
            'senders'  => $senders,
            'default'  => $client['default_sender'] ?? '',
            'provider' => $provider,
        ]);
    } catch (Exception $e) {
        error_log('apiGetSenders error: ' . $e->getMessage());
        $fallback = !empty($client['default_sender']) ? [$client['default_sender']] : [];
        jsonResponse([
            'success'  => false,
            'senders'  => $fallback,
            'default'  => $client['default_sender'] ?? '',
            'provider' => $provider,
            'error'    => $e->getMessage(),
        ]);
    }
}

function apiImportPreview(): void {
    $client = requireClient();
    if (empty($_FILES['csv_file']['tmp_name'])) { jsonResponse(['error' => 'No file'], 422); return; }
    $file      = $_FILES['csv_file']['tmp_name'];
    require ROOT . '/src/controllers/lists.php';
    $delimiter = detectDelimiter($file);
    $handle    = fopen($file, 'r');
    $rows = []; $i = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $i < 5) { $rows[] = $row; $i++; }
    fclose($handle);
    jsonResponse(['rows' => $rows, 'delimiter' => $delimiter]);
}

function apiDebugMessage(): void {
    $client = requireClient();
    if (!Auth::isSuperAdmin()) { jsonResponse(['error' => 'Forbidden'], 403); return; }
    $msgId = (int)($_GET['msg_id'] ?? 0);
    if (!$msgId) { jsonResponse(['error' => 'msg_id required'], 422); return; }
    $msg = DB::fetchOne(
        'SELECT m.*, c.sendsms_username, c.sendsms_apikey, c.sms_provider, c.smsapi_token, c.smso_token
         FROM messages m JOIN clients c ON c.id=m.client_id
         WHERE m.id=? AND m.client_id=?',
        [$msgId, $client['id']]
    );
    if (!$msg) { jsonResponse(['error' => 'Not found'], 404); return; }
    $apiResult = null;
    if ($msg['sendsms_uuid']) {
        try {
            $prov = $msg['sms_provider'] ?? 'sendsms';
            if ($prov === 'smso') {
                $api = new SmsoRo($msg['smso_token'] ?? $msg['smsapi_token'] ?? '');
            } elseif ($prov === 'smsapi') {
                $api = new SmsApiRo($msg['smsapi_token']);
            } else {
                $api = new SendSmsApi($msg['sendsms_username'], $msg['sendsms_apikey']);
            }
            $apiResult = $api->messageStatus($msg['sendsms_uuid']);
        } catch (Exception $e) { $apiResult = ['error' => $e->getMessage()]; }
    }
    $dlrLogs = DB::fetchAll('SELECT * FROM delivery_reports WHERE message_id=? ORDER BY received_at DESC', [$msgId]);
    jsonResponse([
        'message'    => $msg,
        'api_status' => $apiResult,
        'dlr_logs'   => $dlrLogs,
        'provider'   => $client['sms_provider'] ?? 'sendsms',
    ]);
}
