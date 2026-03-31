<?php
/**
 * Rate limit simplu pentru DLR: max 120 req/minut per IP.
 * Previne flooding-ul webhookului cu statusuri false.
 */
function dlrRateLimit(): void {
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    try {
        $db  = DB::getInstance();
        $now = date('Y-m-d H:i:s');
        $db->exec("CREATE TABLE IF NOT EXISTS dlr_rate_limits (
            ip           VARCHAR(45) NOT NULL PRIMARY KEY,
            req_count    INT UNSIGNED DEFAULT 0,
            window_start DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $db->prepare("SELECT req_count, window_start FROM dlr_rate_limits WHERE ip=?");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $db->prepare("INSERT IGNORE INTO dlr_rate_limits (ip, req_count, window_start) VALUES (?,1,?)")
               ->execute([$ip, $now]);
            return;
        }
        if ((time() - strtotime($row['window_start'])) > 60) {
            $db->prepare("UPDATE dlr_rate_limits SET req_count=1, window_start=? WHERE ip=?")
               ->execute([$now, $ip]);
            return;
        }
        if ((int)$row['req_count'] >= 120) {
            http_response_code(429); echo 'Rate limit exceeded'; exit;
        }
        $db->prepare("UPDATE dlr_rate_limits SET req_count=req_count+1 WHERE ip=?")
           ->execute([$ip]);

        // Curatare probabilistica 1%: sterge intrari vechi
        if (random_int(1, 100) === 1) {
            $db->exec("DELETE FROM dlr_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        }
    } catch (PDOException $e) {
        // Fail open — nu blocam DLR-uri legitime daca DB e down
    }
}

function handleDlr(): void {
    dlrRateLimit();

    // SMSO trimite JSON in body (account webhook) sau form-encoded (per-message webhook)
    $rawBody  = file_get_contents('php://input');
    $jsonBody = !empty($rawBody) ? json_decode($rawBody, true) : null;

    // Log complet pentru debug
    error_log('DLR REQUEST: method=' . ($_SERVER['REQUEST_METHOD'] ?? '?')
        . ' GET=' . json_encode($_GET)
        . ' POST=' . json_encode($_POST)
        . ' rawBody=' . substr($rawBody, 0, 300));

    // Unificam sursele de date: JSON body > POST form > GET params
    $data     = (is_array($jsonBody) && !empty($jsonBody)) ? $jsonBody : (!empty($_POST) ? $_POST : $_GET);
    $provider = $_GET['provider'] ?? $_POST['provider'] ?? ($jsonBody['provider'] ?? 'sendsms');

    // SMSO: detectare dupa provider param sau prezenta uuid in orice sursa
    if ($provider === 'smso' || isset($data['uuid'])) {
        handleDlrSmso($data);
        return;
    }

    // smsapi.ro trimite POST cu: MsgId, Status, Points
    if ($provider === 'smsapi' || isset($_POST['MsgId'])) {
        handleDlrSmsapi();
        return;
    }

    // sendsms.ro — GET cu ?msg=ID&status=CODE
    $msgId      = (int)($_GET['msg']    ?? 0);
    $statusCode = isset($_GET['status']) ? (int)$_GET['status'] : -1;

    if ($msgId <= 0 || $statusCode < 0) {
        http_response_code(200); echo 'OK'; exit;
    }

    try {
        DB::insert('INSERT INTO delivery_reports (message_id, sendsms_uuid, status_code) VALUES (?,?,?)',
            [$msgId, '', $statusCode]);
    } catch (Exception $e) {}

    if ($statusCode === 4) {
        DB::execute('UPDATE messages SET status=4, delivered_at=COALESCE(delivered_at,NOW()) WHERE id=?', [$msgId]);
        fetchCostAfterDelivery($msgId);
    } elseif ($statusCode === 1) {
        DB::execute('UPDATE messages SET status=1 WHERE id=?', [$msgId]);
    } elseif ($statusCode === 8) {
        DB::execute('UPDATE messages SET status=8 WHERE id=?', [$msgId]);
    } elseif (in_array($statusCode, [16,32,64])) {
        DB::execute('UPDATE messages SET status=?, failed_at=COALESCE(failed_at,NOW()) WHERE id=?', [$statusCode, $msgId]);
    } else {
        DB::execute('UPDATE messages SET status=? WHERE id=?', [$statusCode, $msgId]);
    }

    updateCampaignCounters($msgId);
    http_response_code(200); echo 'OK'; exit;
}

function handleDlrSmso(array $data): void {
    // SMSO DLR: JSON body cu uuid, status, sent_at, delivered_at, receiver{number, mcc, mnc}
    error_log('DLR SMSO primit: ' . json_encode($data));

    $parsed = SmsoRo::parseDlrWebhook($data);
    error_log('DLR SMSO parsed: ' . json_encode($parsed));

    if (!$parsed['provider_msg_id']) {
        error_log('DLR SMSO: uuid lipseste, ignor');
        http_response_code(200); echo 'OK'; exit;
    }

    // Gaseste mesajul dupa provider_msg_id sau sendsms_uuid
    $msg = DB::fetchOne(
        'SELECT id, campaign_id FROM messages WHERE provider_msg_id=? OR sendsms_uuid=? LIMIT 1',
        [$parsed['provider_msg_id'], $parsed['provider_msg_id']]
    );
    error_log('DLR SMSO lookup by uuid=' . $parsed['provider_msg_id'] . ' => ' . ($msg ? 'msg#' . $msg['id'] : 'NOT FOUND'));

    // Fallback: cauta si dupa msg_id din URL
    if (!$msg) {
        $msgId = (int)($_GET['msg'] ?? 0);
        if ($msgId > 0) {
            $msg = DB::fetchOne('SELECT id, campaign_id FROM messages WHERE id=?', [$msgId]);
            error_log('DLR SMSO fallback by msg_id=' . $msgId . ' => ' . ($msg ? 'FOUND' : 'NOT FOUND'));
        }
    }

    if (!$msg) { error_log('DLR SMSO: mesaj negasit, ignor'); http_response_code(200); echo 'OK'; exit; }

    $msgId = $msg['id'];

    try {
        DB::insert('INSERT INTO delivery_reports (message_id, sendsms_uuid, status_code) VALUES (?,?,?)',
            [$msgId, $parsed['provider_msg_id'], 0]);
    } catch (Exception $e) {}

    if ($parsed['delivered']) {
        DB::execute('UPDATE messages SET status=4, delivered_at=COALESCE(delivered_at,NOW()) WHERE id=?', [$msgId]);
    } elseif ($parsed['failed']) {
        DB::execute('UPDATE messages SET status=32, failed_at=COALESCE(failed_at,NOW()) WHERE id=?', [$msgId]);
    } elseif ($parsed['status'] === 'SENT') {
        DB::execute('UPDATE messages SET status=1 WHERE id=?', [$msgId]);
    }

    updateCampaignCounters($msgId);
    http_response_code(200); echo 'OK'; exit;
}

function handleDlrSmsapi(): void {
    $providerMsgId = $_POST['MsgId'] ?? $_POST['idx'] ?? '';
    $status        = strtoupper($_POST['Status'] ?? $_POST['status'] ?? '');
    $points        = isset($_POST['Points']) ? (float)$_POST['Points'] : null;

    if (!$providerMsgId) { http_response_code(200); echo 'OK'; exit; }

    $msg = DB::fetchOne(
        'SELECT id, campaign_id FROM messages WHERE provider_msg_id=? OR sendsms_uuid=? LIMIT 1',
        [$providerMsgId, $providerMsgId]
    );
    if (!$msg) { http_response_code(200); echo 'OK'; exit; }

    $msgId = $msg['id'];
    try { DB::insert('INSERT INTO delivery_reports (message_id, sendsms_uuid, status_code) VALUES (?,?,?)',
        [$msgId, $providerMsgId, 0]); } catch (Exception $e) {}

    if ($status === 'DELIVERED') {
        DB::execute('UPDATE messages SET status=4, delivered_at=COALESCE(delivered_at,NOW()) WHERE id=?', [$msgId]);
        if ($points !== null && $points > 0) {
            DB::execute('UPDATE messages SET cost=? WHERE id=? AND (cost IS NULL OR cost=0)', [$points, $msgId]);
        }
    } elseif (in_array($status, ['UNDELIVERED','EXPIRED','UNKNOWN'])) {
        DB::execute('UPDATE messages SET status=32, failed_at=COALESCE(failed_at,NOW()) WHERE id=?', [$msgId]);
    } elseif ($status === 'SENT') {
        DB::execute('UPDATE messages SET status=1 WHERE id=?', [$msgId]);
    }

    updateCampaignCounters($msgId);
    http_response_code(200); echo 'OK'; exit;
}

function fetchCostAfterDelivery(int $msgId): void {
    try {
        $msg = DB::fetchOne('SELECT m.sendsms_uuid, m.client_id, m.provider FROM messages m WHERE m.id=?', [$msgId]);
        if (!$msg || empty($msg['sendsms_uuid'])) return;

        $client   = DB::fetchOne('SELECT * FROM clients WHERE id=?', [$msg['client_id']]);
        if (!$client) return;

        $provider = $msg['provider'] ?? 'sendsms';

        if ($provider === 'smso') {
            // SMSO: costul vine direct la trimitere sau la webhook, nu din status
            return;
        } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api    = new SmsApiRo($client['smsapi_token']);
            $result = $api->messageStatus($msg['sendsms_uuid']);
            if (!empty($result['cost']) && $result['cost'] > 0) {
                DB::execute('UPDATE messages SET cost=? WHERE id=? AND (cost IS NULL OR cost=0)',
                    [$result['cost'], $msgId]);
            }
        } else {
            $api    = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
            $result = $api->messageStatus($msg['sendsms_uuid']);
            $parsed = $api->parseMessageStatus($result);
            if ($parsed['cost'] !== null && $parsed['cost'] > 0) {
                DB::execute('UPDATE messages SET cost=?, parts=? WHERE id=? AND (cost IS NULL OR cost=0)',
                    [$parsed['cost'], $parsed['parts'], $msgId]);
            }
        }
    } catch (Exception $e) {
        error_log('DLR cost fetch error msg#' . $msgId . ': ' . $e->getMessage());
    }
}

function updateCampaignCounters(int $msgId): void {
    try {
        $msg = DB::fetchOne('SELECT campaign_id FROM messages WHERE id=?', [$msgId]);
        if ($msg && $msg['campaign_id']) {
            DB::execute(
                'UPDATE campaigns SET
                    delivered_count=(SELECT COUNT(*) FROM messages WHERE campaign_id=? AND status=4),
                    failed_count=(SELECT COUNT(*) FROM messages WHERE campaign_id=? AND status IN(32,16,64))
                 WHERE id=?',
                [$msg['campaign_id'], $msg['campaign_id'], $msg['campaign_id']]
            );
        }
    } catch (Exception $e) {
        error_log('DLR campaign update: ' . $e->getMessage());
    }
}
