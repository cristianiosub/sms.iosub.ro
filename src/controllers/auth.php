<?php
function renderLogin(): void {
    $error = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    $next       = htmlspecialchars($_GET['next'] ?? '/');
    $show2fa    = $_SESSION['pending_2fa_user_id'] ?? false;
    require ROOT . '/templates/login.php';
}

function handleLogin(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $next     = $_POST['next'] ?? '/';
    $ip       = Auth::getClientIP();

    // 0. Honeypot: botii completeaza acest camp ascuns
    if (!empty($_POST['_hp'])) {
        // Simuleaza un raspuns normal ca sa nu alertam botul
        usleep(random_int(300000, 800000));
        $_SESSION['login_error'] = 'Email sau parola incorecta.';
        redirect('/login'); return;
    }

    // 1. Geo-restrictie: doar Romania
    if (!Auth::isAllowedCountry($ip)) {
        $_SESSION['login_error'] = 'Autentificarea este permisa doar din Romania.';
        redirect('/login'); return;
    }

    // 2. Rate-limit per IP + cookie
    $cookieId = Auth::getOrSetLoginCookie();
    $rlResult = Auth::checkLoginRateLimit($ip, $cookieId);
    if ($rlResult === 'blocked') {
        $_SESSION['login_error'] = 'Prea multe incercari esuate. Asteptati 15 minute.';
        redirect('/login'); return;
    }
    if ($rlResult === 'cooldown') {
        $_SESSION['login_error'] = 'Asteptati cel putin 5 secunde intre incercari.';
        redirect('/login'); return;
    }

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Completati toate campurile.';
        redirect('/login'); return;
    }

    if (!Auth::checkBruteForce($email)) {
        $_SESSION['login_error'] = 'Contul este blocat temporar. Incercati din nou in cateva minute.';
        redirect('/login'); return;
    }

    $user = DB::fetchOne('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        Auth::recordFailedLogin($email);
        Auth::recordLoginAttempt($ip, $cookieId);
        usleep(random_int(200000, 500000));
        $_SESSION['login_error'] = 'Email sau parola incorecta.';
        redirect('/login'); return;
    }

    Auth::clearFailedLogin($email);
    Auth::clearLoginAttempts($ip, $cookieId);

    // 2FA activat? — TOTP are prioritate fata de SMS OTP
    if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
        $_SESSION['pending_2fa_user_id'] = $user['id'];
        $_SESSION['pending_2fa_next']    = $next;
        $_SESSION['pending_2fa_type']    = 'totp';
        $_SESSION['2fa_attempts']        = 0;
        redirect('/login?next=' . urlencode($next));
        return;
    }

    if (!empty($user['two_fa_enabled']) && !empty($user['phone'])) {
        // Genereaza cod OTP
        $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 min
        DB::execute('UPDATE users SET two_fa_code=?, two_fa_expires=? WHERE id=?',
            [$code, $expires, $user['id']]);

        // Trimite SMS cu codul
        send2faCode($user['phone'], $code);

        // Stocam user_id in sesiune temporara (fara a loga)
        $_SESSION['pending_2fa_user_id'] = $user['id'];
        $_SESSION['pending_2fa_next']    = $next;
        $_SESSION['pending_2fa_type']    = 'sms';
        $_SESSION['2fa_attempts']        = 0;
        redirect('/login?next=' . urlencode($next));
        return;
    }

    // Login direct (fara 2FA)
    completeLogin($user, $next);
}

function verify2fa(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();

    $userId = $_SESSION['pending_2fa_user_id'] ?? null;
    $next   = $_SESSION['pending_2fa_next'] ?? '/';
    $code   = trim($_POST['code'] ?? '');

    if (!$userId || !$code) {
        $_SESSION['login_error'] = 'Sesiune expirata. Logati-va din nou.';
        redirect('/login'); return;
    }

    // Brute-force: max 5 incercari gresite → invalideaza sesiunea 2FA
    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
    if ($_SESSION['2fa_attempts'] > 5) {
        // Invalideaza si codul SMS din DB daca exista
        DB::execute('UPDATE users SET two_fa_code=NULL, two_fa_expires=NULL WHERE id=?', [$userId]);
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_next'],
              $_SESSION['pending_2fa_type'], $_SESSION['2fa_attempts']);
        $_SESSION['login_error'] = 'Prea multe incercari gresite. Autentificati-va din nou.';
        redirect('/login'); return;
    }

    $type = $_SESSION['pending_2fa_type'] ?? 'sms';

    if ($type === 'totp') {
        $user = DB::fetchOne('SELECT * FROM users WHERE id=? AND totp_enabled=1 AND is_active=1', [$userId]);
        if (!$user || !TOTP::verify($user['totp_secret'], $code)) {
            $_SESSION['login_error'] = 'Cod TOTP incorect. Incercari ramase: ' . (5 - $_SESSION['2fa_attempts']) . '.';
            redirect('/login'); return;
        }
    } else {
        $user = DB::fetchOne(
            'SELECT * FROM users WHERE id=? AND two_fa_code=? AND two_fa_expires > NOW() AND is_active=1',
            [$userId, $code]
        );
        if (!$user) {
            $_SESSION['login_error'] = 'Cod incorect sau expirat. Incercari ramase: ' . (5 - $_SESSION['2fa_attempts']) . '.';
            redirect('/login'); return;
        }
        DB::execute('UPDATE users SET two_fa_code=NULL, two_fa_expires=NULL WHERE id=?', [$userId]);
    }

    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_next'],
          $_SESSION['pending_2fa_type'], $_SESSION['2fa_attempts']);

    completeLogin($user, $next);
}

function completeLogin(array $user, string $next): void {
    Auth::login($user);
    DB::execute('UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?',
        [$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
    audit('login');

    // Superadmin: selecteaza primul client disponibil
    if (Auth::isSuperAdmin()) {
        $first = DB::fetchOne('SELECT id FROM clients WHERE is_active=1 ORDER BY name LIMIT 1');
        if ($first) Auth::setActiveClient($first['id']);
    } else {
        $clients = DB::fetchAll(
            'SELECT c.id FROM clients c
             JOIN user_clients uc ON uc.client_id = c.id
             WHERE uc.user_id = ? AND c.is_active = 1 ORDER BY c.name',
            [$user['id']]
        );
        if (count($clients) >= 1) {
            Auth::setActiveClient($clients[0]['id']);
        }
    }

    $next = (str_starts_with($next, '/') && !str_starts_with($next, '//')) ? $next : '/';
    redirect($next);
}

function send2faCode(string $phone, string $code): void {
    // Gaseste primul client activ pentru a trimite SMS-ul
    $client = DB::fetchOne(
        'SELECT c.* FROM clients c WHERE c.is_active=1 LIMIT 1'
    );
    if (!$client) { error_log('2FA: no active client for sending code'); return; }

    try {
        $provider = $client['sms_provider'] ?? 'sendsms';
        if ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
            $api = new SmsApiRo($client['smsapi_token']);
            $api->sendMessage($phone, "Codul tau de verificare SMS Platform: $code (valid 5 minute)", 'SMSPlatf');
        } elseif ($provider === 'smso' && !empty($client['smso_token'])) {
            $api = new SmsoRo($client['smso_token']);
            $api->sendMessage($phone, "Codul tau de verificare SMS Platform: $code (valid 5 minute)", 'SMSPlatf');
        } else {
            $api = new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
            $api->sendMessage($phone, "Codul tau de verificare SMS Platform: $code (valid 5 minute)", 'SMSPlatf');
        }
    } catch (Exception $e) {
        error_log('2FA send error: ' . $e->getMessage());
    }
}
