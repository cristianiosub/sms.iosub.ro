#!/usr/bin/env php
<?php
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/helpers/DB.php';
require ROOT . '/src/helpers/SendSmsApi.php';
require ROOT . '/src/helpers/SmsApiRo.php';
require ROOT . '/src/helpers/SmsoRo.php';

$messages = DB::fetchAll(
    'SELECT m.id, m.sendsms_uuid, m.provider_msg_id, m.provider, m.client_id, m.status, m.cost
     FROM messages m
     WHERE m.sendsms_uuid IS NOT NULL AND m.sendsms_uuid!=""
       AND ((m.status IN(0,1,8) AND m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
         OR (m.status=4 AND (m.cost IS NULL OR m.cost=0) AND m.provider != "smso"
             AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))
     ORDER BY m.created_at DESC LIMIT 100'
);

if (empty($messages)) { echo date('Y-m-d H:i:s') . " — Nothing to sync\n"; exit(0); }

$updated = 0; $errors = 0; $cache = [];

foreach ($messages as $msg) {
    $cid = $msg['client_id'];
    if (!isset($cache[$cid])) {
        $cache[$cid] = DB::fetchOne('SELECT * FROM clients WHERE id=?', [$cid]);
    }
    $client = $cache[$cid];
    if (!$client) { $errors++; continue; }

    $provider  = $msg['provider'] ?? 'sendsms';
    $uuid      = $msg['sendsms_uuid'];
    $newStatus = -1; $cost = null; $parts = 1;

    try {
        if ($provider === 'smso' && !empty($client['smso_token'])) {
            $api    = new SmsoRo($client['smso_token']);
            $result = $api->messageStatus($uuid);
            if ($result['delivered']) { $newStatus = 4; }
            elseif ($result['failed']) { $newStatus = 32; }
            else { $newStatus = 1; }
            // SMSO: costul vine la trimitere, nu la sync

        } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api    = new SmsApiRo($client['smsapi_token']);
            $result = $api->messageStatus($uuid);
            if ($result['delivered']) { $newStatus = 4; $cost = $result['cost']; }
            elseif ($result['failed']) { $newStatus = 32; }
            else { $newStatus = 1; }

        } else {
            $api    = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
            $result = $api->messageStatus($uuid);
            $parsed = $api->parseMessageStatus($result);
            $newStatus = $parsed['status'];
            $cost      = $parsed['cost'];
            $parts     = $parsed['parts'];
        }

        if ($newStatus <= 0) { $errors++; usleep(200000); continue; }

        if ($newStatus === 4) {
            $sets = ['status=4', 'delivered_at=COALESCE(delivered_at,NOW())'];
            $p    = [];
            if ($cost !== null && $cost > 0) { $sets[] = 'cost=?'; $sets[] = 'parts=?'; $p[] = $cost; $p[] = $parts; }
            $p[] = $msg['id'];
            DB::execute('UPDATE messages SET ' . implode(',', $sets) . ' WHERE id=?', $p);
            if ((int)$msg['status'] !== 4) $updated++;
        } elseif (in_array($newStatus, [16,32,64])) {
            DB::execute('UPDATE messages SET status=?, failed_at=COALESCE(failed_at,NOW()) WHERE id=?', [$newStatus, $msg['id']]);
            if ((int)$msg['status'] !== $newStatus) $updated++;
        } elseif (in_array($newStatus, [1,8])) {
            if ((int)$msg['status'] !== $newStatus) {
                DB::execute('UPDATE messages SET status=? WHERE id=?', [$newStatus, $msg['id']]);
                $updated++;
            }
        }

        usleep(150000);
    } catch (Exception $e) {
        error_log('sync_costs error msg#' . $msg['id'] . ': ' . $e->getMessage());
        $errors++;
    }
}

echo date('Y-m-d H:i:s') . " — Updated: {$updated}, Errors: {$errors}, Checked: " . count($messages) . "\n";
