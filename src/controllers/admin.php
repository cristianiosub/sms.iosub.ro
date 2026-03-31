<?php
// ── Helpers ───────────────────────────────────────────────
function requireSuperAdmin(): void {
    Auth::require();
    if (!Auth::isSuperAdmin()) { http_response_code(403); die('Access denied'); }
}

/**
 * Migrare lazy: adauga coloana smso_token daca nu exista.
 * Ruleaza cel mult o data per process PHP (static flag).
 */
function ensureSmsoTokenColumn(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        DB::fetchOne('SELECT smso_token FROM clients LIMIT 1');
    } catch (Exception $e) {
        try {
            DB::execute('ALTER TABLE clients ADD COLUMN smso_token VARCHAR(500) NULL DEFAULT NULL AFTER smsapi_token');
        } catch (Exception $e2) {
            error_log('ensureSmsoTokenColumn: ' . $e2->getMessage());
        }
    }
}

// ── Clients ───────────────────────────────────────────────
function listClients(): void {
    requireSuperAdmin();
    $clients = DB::fetchAll('SELECT * FROM clients ORDER BY name');
    require ROOT . '/templates/admin_clients.php';
}

function newClient(): void {
    requireSuperAdmin();
    require ROOT . '/templates/admin_client_new.php';
}

function createClient(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    requireSuperAdmin();
    ensureSmsoTokenColumn();

    $name       = trim($_POST['name'] ?? '');
    $slug       = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $smsUser    = trim($_POST['sendsms_username'] ?? '');
    $smsKey     = trim($_POST['sendsms_apikey'] ?? '');
    $smsapiTok  = trim($_POST['smsapi_token'] ?? '');
    $smsoTok    = trim($_POST['smso_apikey'] ?? '');
    $sender     = trim($_POST['default_sender'] ?? '');
    $provider   = in_array($_POST['sms_provider'] ?? '', ['sendsms','smsapi','smso'])
                  ? $_POST['sms_provider'] : 'sendsms';

    if (!$name || !$slug) {
        jsonResponse(['error' => 'Campuri obligatorii lipsa'], 422); return;
    }
    if ($provider === 'sendsms' && (!$smsUser || !$smsKey)) {
        jsonResponse(['error' => 'Username si API key sendsms.ro sunt obligatorii'], 422); return;
    }
    if ($provider === 'smsapi' && !$smsapiTok) {
        jsonResponse(['error' => 'Token smsapi.ro este obligatoriu'], 422); return;
    }
    if ($provider === 'smso' && !$smsoTok) {
        jsonResponse(['error' => 'API Key smso.ro este obligatoriu'], 422); return;
    }

    $id = DB::insert(
        'INSERT INTO clients (slug,name,sendsms_username,sendsms_apikey,default_sender,sms_provider,smsapi_token,smso_token)
         VALUES (?,?,?,?,?,?,?,?)',
        [$slug, $name, $smsUser ?: '', $smsKey ?: '', $sender ?: null, $provider,
         $smsapiTok ?: null, $smsoTok ?: null]
    );
    audit('create_client', 'client', (string)$id);
    redirect('/admin/clients');
}

function editClient(int $id): void {
    requireSuperAdmin();
    $client = DB::fetchOne('SELECT * FROM clients WHERE id=?', [$id]);
    if (!$client) { http_response_code(404); require ROOT . '/templates/404.php'; return; }
    require ROOT . '/templates/admin_client_edit.php';
}

function updateClient(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    requireSuperAdmin();
    ensureSmsoTokenColumn();

    $client = DB::fetchOne('SELECT id FROM clients WHERE id=?', [$id]);
    if (!$client) { jsonResponse(['error' => 'Client negasit'], 404); return; }

    $name       = trim($_POST['name'] ?? '');
    $sender     = trim($_POST['default_sender'] ?? '');
    $provider   = in_array($_POST['sms_provider'] ?? '', ['sendsms','smsapi','smso'])
                  ? $_POST['sms_provider'] : 'sendsms';
    // Toate credentialele — formul trimite toate campurile (chiar si cele ascunse)
    $smsUser    = trim($_POST['sendsms_username'] ?? '');
    $smsKey     = trim($_POST['sendsms_apikey'] ?? '');
    $smsapiTok  = trim($_POST['smsapi_token'] ?? '');
    $smsoTok    = trim($_POST['smso_apikey'] ?? '');

    $alertPhone = trim($_POST['alert_email'] ?? '');
    if ($alertPhone) {
        $alertPhone = SendSmsApi::normalizePhone($alertPhone);
    }
    $alertThr = (float)($_POST['alert_threshold'] ?? 5);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) { jsonResponse(['error' => 'Numele este obligatoriu'], 422); return; }
    if ($provider === 'smsapi' && !$smsapiTok) { jsonResponse(['error' => 'Token smsapi.ro este obligatoriu'], 422); return; }
    if ($provider === 'smso'   && !$smsoTok)   { jsonResponse(['error' => 'API Key smso.ro este obligatoriu'], 422); return; }

    DB::execute(
        'UPDATE clients SET name=?, default_sender=?, sms_provider=?,
         sendsms_username=?, sendsms_apikey=?, smsapi_token=?, smso_token=?,
         alert_email=?, alert_threshold=?, is_active=?
         WHERE id=?',
        [
            $name,
            $sender ?: null,
            $provider,
            $smsUser ?: '',
            $smsKey ?: '',
            $smsapiTok ?: null,
            $smsoTok ?: null,
            $alertPhone ?: null,
            max(0, $alertThr),
            $isActive,
            $id
        ]
    );
    audit('update_client', 'client', (string)$id);
    jsonResponse(['success' => true]);
}

// ── Users ─────────────────────────────────────────────────
function listUsers(): void {
    requireSuperAdmin();
    $users = DB::fetchAll('SELECT * FROM users ORDER BY created_at DESC');
    require ROOT . '/templates/admin_users.php';
}

function newUser(): void {
    requireSuperAdmin();
    $clients = DB::fetchAll('SELECT id, name FROM clients WHERE is_active=1 ORDER BY name');
    require ROOT . '/templates/admin_user_new.php';
}

function createUser(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    requireSuperAdmin();

    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['superadmin','admin','user']) ? $_POST['role'] : 'user';
    $clients  = array_map('intval', (array)($_POST['clients'] ?? []));

    if (!$email || !$fullName || strlen($password) < 10) {
        jsonResponse(['error' => 'Date invalide (parola min 10 caractere)'], 422); return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $id   = DB::insert(
        'INSERT INTO users (email, full_name, password_hash, role) VALUES (?,?,?,?)',
        [$email, $fullName, $hash, $role]
    );
    foreach ($clients as $cid) {
        if ($cid) DB::execute('INSERT IGNORE INTO user_clients (user_id,client_id) VALUES (?,?)', [$id, $cid]);
    }
    audit('create_user', 'user', (string)$id);
    redirect('/admin/users');
}

// ── Audit log ─────────────────────────────────────────────
function listAuditLog(): void {
    requireSuperAdmin();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;
    $filter = trim($_GET['filter'] ?? '');

    $where  = '1=1'; $params = [];
    if ($filter) { $where .= ' AND a.action LIKE ?'; $params[] = '%'.$filter.'%'; }

    $total = (int)DB::fetchOne("SELECT COUNT(*) as n FROM audit_log a WHERE $where", $params)['n'];
    $logs  = DB::fetchAll(
        "SELECT a.*, u.full_name, u.email, c.name as client_name
         FROM audit_log a
         LEFT JOIN users u ON u.id=a.user_id
         LEFT JOIN clients c ON c.id=a.client_id
         WHERE $where ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset",
        $params
    );
    require ROOT . '/templates/admin_audit.php';
}

// ── Settings ──────────────────────────────────────────────
function renderSettings(): void {
    $client = requireClient();
    TOTP::ensureColumns();
    $user   = DB::fetchOne('SELECT * FROM users WHERE id=?', [Auth::userId()]);
    $twoFa  = !empty($user['two_fa_enabled']);
    $totpEnabled = !empty($user['totp_enabled']);
    require ROOT . '/templates/settings.php';
}

function saveSettings(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    $action = $_POST['action'] ?? 'general';

    if ($action === 'general') {
        $sender = trim($_POST['default_sender'] ?? '');
        DB::execute('UPDATE clients SET default_sender=? WHERE id=?', [$sender ?: null, $client['id']]);
        audit('save_settings');
        jsonResponse(['success' => true]);
        return;
    }

    if ($action === 'password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $user    = DB::fetchOne('SELECT * FROM users WHERE id=?', [Auth::userId()]);
        if (!$user || !password_verify($oldPass, $user['password_hash'])) {
            jsonResponse(['error' => 'Parola curenta incorecta'], 422); return;
        }
        if (strlen($newPass) < 10) {
            jsonResponse(['error' => 'Parola noua trebuie sa aiba minim 10 caractere'], 422); return;
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        DB::execute('UPDATE users SET password_hash=? WHERE id=?', [$hash, Auth::userId()]);
        audit('change_password');
        jsonResponse(['success' => true]);
        return;
    }

    if ($action === '2fa') {
        $phone  = trim($_POST['phone'] ?? '');
        $enable = (int)($_POST['enable'] ?? 0);
        $phone  = SendSmsApi::normalizePhone($phone);
        if ($enable && strlen($phone) < 9) {
            jsonResponse(['error' => 'Numar de telefon invalid'], 422); return;
        }
        DB::execute(
            'UPDATE users SET two_fa_enabled=?, phone=? WHERE id=?',
            [$enable, $enable ? $phone : null, Auth::userId()]
        );
        audit('toggle_2fa');
        jsonResponse(['success' => true, 'enabled' => (bool)$enable]);
        return;
    }

    if ($action === 'alert') {
        $phone = trim($_POST['alert_phone'] ?? '');
        if ($phone) $phone = SendSmsApi::normalizePhone($phone);
        $thr   = (float)($_POST['alert_threshold'] ?? 5);
        if ($phone && strlen($phone) < 9) {
            jsonResponse(['error' => 'Numar de telefon invalid'], 422); return;
        }
        DB::execute(
            'UPDATE clients SET alert_email=?, alert_threshold=? WHERE id=?',
            [$phone ?: null, max(0, $thr), $client['id']]
        );
        audit('save_alert_settings');
        jsonResponse(['success' => true]);
        return;
    }

    if ($action === 'totp_setup') {
        TOTP::ensureColumns();
        $userId = Auth::userId();
        $user   = DB::fetchOne('SELECT email FROM users WHERE id=?', [$userId]);
        $secret = TOTP::generateSecret();
        // Stocam temporar secretul pana la confirmare
        DB::execute('UPDATE users SET totp_pending=? WHERE id=?', [$secret, $userId]);
        $uri = TOTP::getProvisioningUri($secret, $user['email'] ?? 'user');
        audit('totp_setup_initiated');
        jsonResponse(['success' => true, 'secret' => $secret, 'uri' => $uri]);
        return;
    }

    if ($action === 'totp_confirm') {
        $userId = Auth::userId();
        $code   = trim($_POST['code'] ?? '');
        $user   = DB::fetchOne('SELECT totp_pending FROM users WHERE id=?', [$userId]);
        if (empty($user['totp_pending'])) {
            jsonResponse(['error' => 'Nu exista un secret TOTP in asteptare. Reluati setup-ul.'], 422); return;
        }
        if (!TOTP::verify($user['totp_pending'], $code)) {
            jsonResponse(['error' => 'Cod incorect. Verificati ora sistemului si reincercati.'], 422); return;
        }
        DB::execute(
            'UPDATE users SET totp_secret=totp_pending, totp_pending=NULL, totp_enabled=1 WHERE id=?',
            [$userId]
        );
        audit('totp_enabled');
        jsonResponse(['success' => true]);
        return;
    }

    if ($action === 'totp_disable') {
        $userId  = Auth::userId();
        $oldPass = $_POST['password'] ?? '';
        $user    = DB::fetchOne('SELECT password_hash FROM users WHERE id=?', [$userId]);
        if (!$user || !password_verify($oldPass, $user['password_hash'])) {
            jsonResponse(['error' => 'Parola incorecta'], 422); return;
        }
        DB::execute(
            'UPDATE users SET totp_secret=NULL, totp_pending=NULL, totp_enabled=0 WHERE id=?',
            [$userId]
        );
        audit('totp_disabled');
        jsonResponse(['success' => true]);
        return;
    }

    jsonResponse(['error' => 'Actiune invalida'], 422);
}
