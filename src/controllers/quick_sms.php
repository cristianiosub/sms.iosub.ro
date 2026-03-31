<?php
function renderQuickSms(): void {
    $client    = requireClient();
    $templates = DB::fetchAll(
        'SELECT id, name, body FROM message_templates WHERE client_id=? ORDER BY name',
        [$client['id']]
    );
    require ROOT . '/templates/quick_sms.php';
}

function handleQuickSms(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    try {
        $phones  = array_filter(array_map('trim', explode("\n", $_POST['phones'] ?? '')));
        $text    = trim($_POST['message_text'] ?? '');
        $sender  = trim($_POST['sender'] ?? $client['default_sender'] ?? '');
        $schedAt = trim($_POST['scheduled_at'] ?? '');

        if (empty($phones) || empty($text)) {
            jsonResponse(['error' => 'Campuri obligatorii lipsa'], 422); return;
        }

        $results = [];

        foreach ($phones as $rawPhone) {
            $phone = SendSmsApi::normalizePhone($rawPhone);
            if (strlen($phone) < 10) {
                $results[] = ['phone' => $rawPhone, 'status' => 'invalid', 'error' => 'Format invalid'];
                continue;
            }

            // Verifica blacklist
            $blocked = DB::fetchOne(
                'SELECT id FROM optout WHERE client_id=? AND phone=?',
                [$client['id'], $phone]
            );
            if ($blocked) {
                $results[] = ['phone' => $phone, 'status' => 'blocked', 'error' => 'Numar in blacklist'];
                continue;
            }

            $finalText = interpolateMessage($text, ['phone' => $phone], $client['id']);

            if ($schedAt) {
                // Verifica duplicate pentru programate
                $dup = DB::fetchOne(
                    'SELECT id FROM messages WHERE client_id=? AND phone=? AND message_text=?
                     AND is_scheduled=1 AND scheduled_at=? LIMIT 1',
                    [$client['id'], $phone, $finalText, date('Y-m-d H:i:s', strtotime($schedAt))]
                );
                if ($dup) {
                    $results[] = ['phone' => $phone, 'status' => 'duplicate', 'error' => 'Deja programat'];
                    continue;
                }
                $provider = $client['sms_provider'] ?? 'sendsms';
                DB::insert(
                    'INSERT INTO messages (client_id, phone, message_text, sender, provider, status, is_scheduled, scheduled_at)
                     VALUES (?,?,?,?,?,0,1,?)',
                    [$client['id'], $phone, $finalText, $sender, $provider,
                     date('Y-m-d H:i:s', strtotime($schedAt))]
                );
                $results[] = ['phone' => $phone, 'status' => 'scheduled'];
            } else {
                // Trimitere imediata via ProviderFactory
                $r = ProviderFactory::sendAndSave($client, $phone, $finalText, $sender);
                if ($r['success']) {
                    $results[] = ['phone' => $phone, 'status' => 'sent', 'uuid' => $r['provider_msg_id'] ?? null];
                } elseif ($r['blocked'] ?? false) {
                    $results[] = ['phone' => $phone, 'status' => 'blocked', 'error' => $r['error']];
                } elseif ($r['duplicate'] ?? false) {
                    $results[] = ['phone' => $phone, 'status' => 'duplicate', 'error' => $r['error']];
                } else {
                    $results[] = ['phone' => $phone, 'status' => 'failed', 'error' => $r['error'] ?? 'Unknown'];
                }
            }
        }

        audit('quick_sms');
        jsonResponse(['success' => true, 'results' => $results]);
    } catch (\Throwable $e) {
        error_log('handleQuickSms CRASH: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        jsonResponse(['error' => 'Eroare server: ' . $e->getMessage()], 500);
    }
}
