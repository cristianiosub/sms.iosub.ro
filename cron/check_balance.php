#!/usr/bin/env php
<?php
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/helpers/DB.php';
require ROOT . '/src/helpers/SendSmsApi.php';
require ROOT . '/src/helpers/SmsApiRo.php';
require ROOT . '/src/helpers/SmsoRo.php';

$clients = DB::fetchAll(
    'SELECT * FROM clients WHERE is_active=1 AND alert_email IS NOT NULL AND alert_threshold > 0'
);

foreach ($clients as $client) {
    try {
        $provider   = $client['sms_provider'] ?? 'sendsms';
        $balance    = null;
        $currency   = 'EUR';

        if ($provider === 'smso' && !empty($client['smso_token'])) {
            $api    = new SmsoRo($client['smso_token']);
            $result = $api->getBalance();
            if ($result['success']) { $balance = (float)$result['balance']; }
        } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api    = new SmsApiRo($client['smsapi_token']);
            $result = $api->getBalance();
            if ($result['success']) { $balance = (float)$result['balance']; $currency = 'puncte'; }
        } else {
            $api    = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
            $result = $api->getBalance();
            if (isset($result['status']) && $result['status'] >= 0) {
                $balance = (float)($result['details'] ?? 0);
            }
        }

        if ($balance === null) continue;

        $threshold  = (float)$client['alert_threshold'];
        $alertPhone = $client['alert_email'];

        if ($balance <= $threshold) {
            if ($client['alert_sent_at'] && (time() - strtotime($client['alert_sent_at'])) < 43200) continue;

            $text = "SMS Platform: Soldul contului {$client['name']} a scazut la "
                  . number_format($balance, 2) . " $currency"
                  . " (prag: " . number_format($threshold, 2) . "). Reincarca!";

            $sent = false;
            try {
                if ($provider === 'smso' && !empty($client['smso_token'])) {
                    $smsApi = new SmsoRo($client['smso_token']);
                    // Obtine primul sender disponibil
                    $senders = $smsApi->getSenders();
                    $sender  = $senders['senders'][0] ?? '';
                    $result  = $smsApi->sendMessage($alertPhone, $text, $sender);
                    $sent    = $result['success'] ?? false;
                } elseif ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
                    $smsApi = new SmsApiRo($client['smsapi_token']);
                    $result = $smsApi->sendMessage($alertPhone, $text, 'SMSPlatf');
                    $sent   = $result['success'] ?? false;
                } else {
                    $smsApi = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
                    $result = $smsApi->sendMessage($alertPhone, $text, 'SMSPlatf');
                    $sent   = isset($result['status']) && $result['status'] === 1;
                }
            } catch (Exception $e) {
                error_log("check_balance SMS error: " . $e->getMessage());
            }

            if ($sent) {
                DB::execute('UPDATE clients SET alert_sent_at=NOW() WHERE id=?', [$client['id']]);
                echo date('Y-m-d H:i:s') . " — Alerta SMS trimisa la {$alertPhone} pentru {$client['name']} (sold: {$balance} {$currency})\n";
            } else {
                echo date('Y-m-d H:i:s') . " — EROARE trimitere alerta pentru {$client['name']}\n";
            }
        } else {
            if ($client['alert_sent_at']) {
                DB::execute('UPDATE clients SET alert_sent_at=NULL WHERE id=?', [$client['id']]);
            }
            echo date('Y-m-d H:i:s') . " — {$client['name']}: sold OK ({$balance} {$currency})\n";
        }
    } catch (Exception $e) {
        error_log("check_balance error client#{$client['id']}: " . $e->getMessage());
    }
}

echo date('Y-m-d H:i:s') . " — Balance check done for " . count($clients) . " clients\n";
