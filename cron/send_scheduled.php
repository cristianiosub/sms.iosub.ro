#!/usr/bin/env php
<?php
// ============================================================
// cron/send_scheduled.php
// Ruleaza la fiecare minut: * * * * * php /path/to/cron/send_scheduled.php
// ============================================================
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/helpers/DB.php';
require ROOT . '/src/helpers/SendSmsApi.php';

define('REPORT_URL_BASE', BASE_URL . '/dlr');

// Lock file - previne rulari multiple simultan
$lockFile = sys_get_temp_dir() . '/sms_cron.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 55) {
    exit(0);
}
file_put_contents($lockFile, getmypid());

// ── 1. Mesaje individuale programate ─────────────────────────
$messages = DB::fetchAll(
    'SELECT m.*, c.sendsms_username, c.sendsms_apikey
     FROM messages m
     JOIN clients c ON c.id = m.client_id
     WHERE m.is_scheduled = 1
       AND m.status = 0
       AND m.scheduled_at <= NOW()
       AND c.is_active = 1
     LIMIT 200'
);

foreach ($messages as $msg) {
    $api       = new SendSmsApi($msg['sendsms_username'], $msg['sendsms_apikey']);
    $reportUrl = REPORT_URL_BASE . '?msg=' . $msg['id'] . '&status=%d';
    $result    = $api->sendMessage($msg['phone'], $msg['message_text'], $msg['sender'] ?? '', $reportUrl);

    if (isset($result['status']) && $result['status'] === 1) {
        DB::execute(
            'UPDATE messages SET status=1, is_scheduled=0, sendsms_uuid=?, sent_at=NOW() WHERE id=?',
            [$result['details'] ?? null, $msg['id']]
        );
    } else {
        // Retry de max 3 ori, la 5 minute interval
        $retries = (int)($msg['parts'] ?? 0);
        if ($retries >= 3) {
            DB::execute('UPDATE messages SET status=32, failed_at=NOW(), is_scheduled=0 WHERE id=?', [$msg['id']]);
        } else {
            DB::execute(
                'UPDATE messages SET parts=parts+1, scheduled_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id=?',
                [$msg['id']]
            );
        }
    }
}

// ── 2. Campanii programate ────────────────────────────────────
$campaigns = DB::fetchAll(
    'SELECT ca.*, cl.sendsms_username, cl.sendsms_apikey
     FROM campaigns ca
     JOIN clients cl ON cl.id = ca.client_id
     WHERE ca.status = "scheduled"
       AND ca.scheduled_at <= NOW()
       AND cl.is_active = 1'
);

foreach ($campaigns as $campaign) {
    DB::execute('UPDATE campaigns SET status="running", started_at=NOW() WHERE id=?', [$campaign['id']]);

    $contacts = DB::fetchAll(
        'SELECT * FROM contacts WHERE list_id=? AND is_blocked=0',
        [$campaign['list_id']]
    );

    $api    = new SendSmsApi($campaign['sendsms_username'], $campaign['sendsms_apikey']);
    $sent   = 0;
    $failed = 0;

    foreach ($contacts as $contact) {
        $text = str_replace(
            ['{nume}', '{prenume}', '{telefon}'],
            [$contact['last_name'] ?? '', $contact['first_name'] ?? '', $contact['phone']],
            $campaign['message_text']
        );

        $msgId     = DB::insert(
            'INSERT INTO messages (client_id, campaign_id, contact_id, phone, message_text, sender, status, sent_at)
             VALUES (?,?,?,?,?,?,0,NOW())',
            [$campaign['client_id'], $campaign['id'], $contact['id'], $contact['phone'], $text, $campaign['sender']]
        );
        $reportUrl = REPORT_URL_BASE . '?msg=' . $msgId . '&status=%d';
        $result    = $api->sendMessage($contact['phone'], $text, $campaign['sender'] ?? '', $reportUrl);

        if (isset($result['status']) && $result['status'] === 1) {
            DB::execute('UPDATE messages SET status=1, sendsms_uuid=? WHERE id=?', [$result['details'] ?? null, $msgId]);
            $sent++;
        } else {
            DB::execute('UPDATE messages SET status=32, failed_at=NOW() WHERE id=?', [$msgId]);
            $failed++;
        }
        usleep(50000); // 50ms throttle intre mesaje
    }

    DB::execute(
        'UPDATE campaigns SET status="completed", completed_at=NOW(), sent_count=?, failed_count=?, total_recipients=? WHERE id=?',
        [$sent, $failed, count($contacts), $campaign['id']]
    );
}

@unlink($lockFile);
echo date('Y-m-d H:i:s') . " — Procesate: " . count($messages) . " mesaje, " . count($campaigns) . " campanii\n";
