<?php
function listOptout(): void {
    $client = requireClient();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['q'] ?? '');

    $where  = 'WHERE client_id=?';
    $params = [$client['id']];
    if ($search) { $where .= ' AND phone LIKE ?'; $params[] = '%' . $search . '%'; }

    $total   = (int)DB::fetchOne("SELECT COUNT(*) as n FROM optout $where", $params)['n'];
    $entries = DB::fetchAll("SELECT * FROM optout $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);
    require ROOT . '/templates/optout.php';
}

function addOptout(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    $phones = array_filter(array_map('trim', preg_split('/[\s,;]+/', $_POST['phones'] ?? '')));
    $reason = trim($_POST['reason'] ?? 'manual');
    if (empty($phones)) { jsonResponse(['error' => 'Niciun numar introdus'], 422); return; }

    $added  = 0;
    $skipped = 0;
    foreach ($phones as $raw) {
        $phone = SendSmsApi::normalizePhone($raw);
        if (strlen($phone) < 9) { $skipped++; continue; }
        try {
            DB::insert(
                'INSERT IGNORE INTO optout (client_id, phone, reason) VALUES (?,?,?)',
                [$client['id'], $phone, $reason]
            );
            // Blocheaza si in contacts daca exista
            DB::execute('UPDATE contacts SET is_blocked=1 WHERE client_id=? AND phone=?', [$client['id'], $phone]);
            $added++;
        } catch (Exception $e) { $skipped++; }
    }
    audit('add_optout');
    jsonResponse(['success' => true, 'added' => $added, 'skipped' => $skipped]);
}

function deleteOptout(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    DB::execute('DELETE FROM optout WHERE id=? AND client_id=?', [$id, $client['id']]);
    audit('delete_optout', 'optout', (string)$id);
    jsonResponse(['success' => true]);
}

function importOptout(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    if (empty($_FILES['csv_file']['tmp_name'])) {
        jsonResponse(['error' => 'Niciun fisier'], 422); return;
    }

    // Limita marime fisier: max 5 MB
    if (($_FILES['csv_file']['size'] ?? 0) > 5 * 1024 * 1024) {
        jsonResponse(['error' => 'Fisierul depaseste limita de 5 MB'], 413); return;
    }

    // Rate limit: max 5 importuri blacklist per ora per utilizator
    $recentImports = (int)(DB::fetchOne(
        "SELECT COUNT(*) as n FROM audit_log WHERE user_id=? AND action='import_optout' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [Auth::userId()]
    )['n'] ?? 0);
    if ($recentImports >= 5) {
        jsonResponse(['error' => 'Limita atinsa: max 5 importuri pe ora. Reveniti mai tarziu.'], 429); return;
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $added  = 0; $skipped = 0;

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $raw   = trim($row[0] ?? '');
        $phone = SendSmsApi::normalizePhone($raw);
        if (strlen($phone) < 9) { $skipped++; continue; }
        try {
            DB::insert('INSERT IGNORE INTO optout (client_id, phone, reason) VALUES (?,?,?)',
                [$client['id'], $phone, 'import']);
            DB::execute('UPDATE contacts SET is_blocked=1 WHERE client_id=? AND phone=?',
                [$client['id'], $phone]);
            $added++;
        } catch (Exception $e) { $skipped++; }
    }
    fclose($handle);
    audit('import_optout');
    jsonResponse(['success' => true, 'added' => $added, 'skipped' => $skipped]);
}

/** Pagina publica unde destinatarii pot face opt-out */
function handleStopPage(): void {
    $phone    = trim($_GET['phone'] ?? '');
    $clientId = (int)($_GET['c'] ?? 0);
    $token    = $_GET['t'] ?? '';

    // Verifica token HMAC — accepta si tokene vechi de 12 chars (backward compat)
    $hmac      = hash_hmac('sha256', $phone . ':' . $clientId, SECRET_KEY);
    $expected32 = substr($hmac, 0, 32);
    $expected12 = substr($hmac, 0, 12);
    $valid = ($clientId > 0 && $phone &&
              (hash_equals($expected32, $token) || hash_equals($expected12, $token)));

    require ROOT . '/templates/optout_stop.php';
}

function handleStopSubmit(): void {
    $phone    = trim($_POST['phone'] ?? '');
    $clientId = (int)($_POST['client_id'] ?? 0);
    $token    = $_POST['token'] ?? '';

    $hmac       = hash_hmac('sha256', $phone . ':' . $clientId, SECRET_KEY);
    $expected32 = substr($hmac, 0, 32);
    $expected12 = substr($hmac, 0, 12);
    if (!$clientId || !$phone ||
        (!hash_equals($expected32, $token) && !hash_equals($expected12, $token))) {
        http_response_code(400); echo 'Invalid request'; exit;
    }

    $phone = SendSmsApi::normalizePhone($phone);
    try {
        DB::insert('INSERT IGNORE INTO optout (client_id, phone, reason) VALUES (?,?,?)',
            [$clientId, $phone, 'STOP']);
        DB::execute('UPDATE contacts SET is_blocked=1 WHERE client_id=? AND phone=?',
            [$clientId, $phone]);
    } catch (Exception $e) {
        error_log('Optout STOP error: ' . $e->getMessage());
    }

    require ROOT . '/templates/optout_confirmed.php';
}

/** Genereaza URL de optout pentru un numar (pentru inserare in mesaje) */
function generateOptoutUrl(string $phone, int $clientId): string {
    $token = substr(hash_hmac('sha256', $phone . ':' . $clientId, SECRET_KEY), 0, 32);
    return BASE_URL . '/stop?phone=' . urlencode($phone) . '&c=' . $clientId . '&t=' . $token;
}
