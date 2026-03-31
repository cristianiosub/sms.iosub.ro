<?php
// Fisier TEMPORAR de debug — STERGE dupa utilizare!
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/helpers/DB.php';
require ROOT . '/src/helpers/SendSmsApi.php';

header('Content-Type: application/json');

// Ia ultimele 5 mesaje
$messages = DB::fetchAll(
    'SELECT m.id, m.phone, m.status, m.sendsms_uuid, m.cost, m.parts, m.sent_at, m.delivered_at,
            c.sendsms_username, c.sendsms_apikey
     FROM messages m
     JOIN clients c ON c.id = m.client_id
     ORDER BY m.id DESC LIMIT 5'
);

$results = [];
foreach ($messages as $msg) {
    $entry = [
        'id'           => $msg['id'],
        'phone'        => $msg['phone'],
        'db_status'    => $msg['status'],
        'db_cost'      => $msg['cost'],
        'db_parts'     => $msg['parts'],
        'sendsms_uuid' => $msg['sendsms_uuid'],
        'sent_at'      => $msg['sent_at'],
        'delivered_at' => $msg['delivered_at'],
        'report_url'   => BASE_URL . '/dlr?msg=' . $msg['id'] . '&status=%d',
        'api_response' => null,
    ];

    if ($msg['sendsms_uuid']) {
        $api = new SendSmsApi($msg['sendsms_username'], $msg['sendsms_apikey']);
        $entry['api_response'] = $api->messageStatus($msg['sendsms_uuid']);
    }

    // Si verifica si delivery_reports
    $entry['dlr_logs'] = DB::fetchAll(
        'SELECT * FROM delivery_reports WHERE message_id=? ORDER BY received_at DESC',
        [$msg['id']]
    );

    $results[] = $entry;
}

echo json_encode($results, JSON_PRETTY_PRINT);
