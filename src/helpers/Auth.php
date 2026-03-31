<?php
class Auth {

    // ================================================================
    // SESIUNE SECURIZATA
    // ================================================================

    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = (APP_ENV === 'production');
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,            // cookie de sesiune (nu persistent)
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Strict',     // Schimbat din Lax pentru protectie CSRF mai buna
            ]);
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_start();
            if (empty($_SESSION['__initiated'])) {
                session_regenerate_id(true);
                $_SESSION['__initiated'] = true;
            }
        }
    }

    public static function check(): bool {
        self::init();
        if (empty($_SESSION['user_id'])) return false;

        // Timeout inactivitate
        if (!empty($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }

        // Timeout absolut (8 ore de la autentificare)
        if (!empty($_SESSION['session_start']) &&
            (time() - $_SESSION['session_start']) > SESSION_ABSOLUTE_LIFETIME) {
            self::logout();
            return false;
        }

        // Binding IP (session hijacking basic check)
        if (!empty($_SESSION['bound_ip']) && $_SESSION['bound_ip'] !== self::getClientIP()) {
            self::logout();
            return false;
        }

        // Binding User-Agent
        if (!empty($_SESSION['bound_ua']) && $_SESSION['bound_ua'] !== self::getUAHash()) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function require(): void {
        if (!self::check()) {
            header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }

    public static function login(array $user): void {
        self::init();
        session_regenerate_id(true);
        $_SESSION['user_id']          = $user['id'];
        $_SESSION['user_email']       = $user['email'];
        $_SESSION['user_name']        = $user['full_name'];
        $_SESSION['user_role']        = $user['role'];
        $_SESSION['last_activity']    = time();
        $_SESSION['session_start']    = time();  // pentru timeout absolut
        $_SESSION['bound_ip']         = self::getClientIP();
        $_SESSION['bound_ua']         = self::getUAHash();  // binding UA
        $_SESSION['active_client_id'] = null;
    }

    public static function logout(): void {
        self::init();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function userId(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        return [
            'id'    => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name'  => $_SESSION['user_name'],
            'role'  => $_SESSION['user_role'],
        ];
    }

    public static function isSuperAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'superadmin';
    }

    public static function activeClientId(): ?int {
        return isset($_SESSION['active_client_id']) ? (int)$_SESSION['active_client_id'] : null;
    }

    public static function setActiveClient(?int $clientId): void {
        $_SESSION['active_client_id'] = $clientId;
    }

    // ================================================================
    // CSRF — token rotat dupa fiecare utilizare
    // ================================================================

    public static function csrfToken(): string {
        self::init();
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function csrfVerify(): bool {
        $token  = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        if (empty($stored) || empty($token)) return false;
        $valid = hash_equals($stored, $token);
        if ($valid) {
            // Roteste token-ul dupa utilizare
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $valid;
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    // ================================================================
    // BRUTE-FORCE per cont (in DB, existent)
    // ================================================================

    public static function checkBruteForce(string $email): bool {
        $user = DB::fetchOne('SELECT failed_attempts, locked_until FROM users WHERE email = ?', [$email]);
        if (!$user) return true;
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return false;
        }
        return true;
    }

    public static function recordFailedLogin(string $email): void {
        DB::execute(
            'UPDATE users SET failed_attempts = failed_attempts + 1,
             locked_until = IF(failed_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until)
             WHERE email = ?',
            [MAX_LOGIN_ATTEMPTS, LOCKOUT_MINUTES, $email]
        );
    }

    public static function clearFailedLogin(string $email): void {
        DB::execute('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE email = ?', [$email]);
    }

    // ================================================================
    // GEO-RESTRICTIE — doar Romania (sau tari permise)
    // Rezultatul e cacheuit in DB timp de 7 zile.
    // ================================================================

    public static function isAllowedCountry(string $ip): bool {
        // Permite intotdeauna IP-uri locale / private
        if (in_array($ip, ['127.0.0.1', '::1'], true) ||
            (bool)preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ip)) {
            return true;
        }

        if (!defined('GEO_RESTRICT_LOGIN') || !GEO_RESTRICT_LOGIN) return true;
        $allowed = GEO_ALLOWED_COUNTRIES;
        $db      = DB::getInstance();

        // Creeaza tabela cache daca nu exista
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS ip_geo_cache (
                ip         VARCHAR(45) NOT NULL PRIMARY KEY,
                country    VARCHAR(5)  NOT NULL DEFAULT '',
                checked_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_checked (checked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // Cauta in cache (valid 7 zile)
        try {
            $stmt = $db->prepare("SELECT country FROM ip_geo_cache WHERE ip=? AND checked_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return in_array($row['country'], $allowed, true);
            }
        } catch (PDOException $e) {}

        // Interogeaza ip-api.com (gratuit, 45 req/min)
        $country = '';
        $ctx = stream_context_create(['http' => [
            'timeout'       => 3,
            'ignore_errors' => true,
            'method'        => 'GET',
        ]]);
        $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false, $ctx);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                $country = $data['countryCode'] ?? '';
            }
        }

        // Stocheaza in cache
        try {
            $db->prepare("INSERT INTO ip_geo_cache (ip, country) VALUES (?,?)
                ON DUPLICATE KEY UPDATE country=?, checked_at=NOW()")
               ->execute([$ip, $country, $country]);
        } catch (PDOException $e) {}

        return $country !== '' && in_array($country, $allowed, true);
    }

    // ================================================================
    // COOKIE RATE-LIMIT LOGIN
    // ================================================================

    public static function getOrSetLoginCookie(): string {
        $name = '_sms_la';
        if (!empty($_COOKIE[$name]) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE[$name])) {
            return $_COOKIE[$name];
        }
        $id = bin2hex(random_bytes(32));
        setcookie($name, $id, [
            'expires'  => time() + 86400 * 30,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        return $id;
    }

    // ================================================================
    // RATE-LIMIT LOGIN per IP + cookie
    // Returneaza: true | 'blocked' | 'cooldown'
    // ================================================================

    public static function checkLoginRateLimit(string $ip, string $cookieId): string|true {
        $db = DB::getInstance();

        // Creeaza tabela daca nu exista
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                ip           VARCHAR(45)  NOT NULL,
                cookie_id    VARCHAR(64)  DEFAULT NULL,
                attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time     (ip,        attempted_at),
                INDEX idx_cookie_time (cookie_id, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {}

        // Curatare periodica (1% sansa per request — evita cresterea nelimitata a tabelelor)
        if (random_int(1, 100) === 1) {
            try {
                $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $db->exec("DELETE FROM ip_geo_cache WHERE checked_at < DATE_SUB(NOW(), INTERVAL 8 DAY)");
            } catch (PDOException $e) {}
        }

        $window   = defined('IP_LOGIN_WINDOW_SECONDS')   ? (int)IP_LOGIN_WINDOW_SECONDS   : 900;
        $maxAtt   = defined('IP_LOGIN_MAX_ATTEMPTS')      ? (int)IP_LOGIN_MAX_ATTEMPTS      : 5;
        $cooldown = defined('IP_LOGIN_COOLDOWN_SECONDS')  ? (int)IP_LOGIN_COOLDOWN_SECONDS  : 5;

        // Rate-limit per IP
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $stmt->execute([$ip, $window]);
            if ((int)$stmt->fetchColumn() >= $maxAtt) return 'blocked';
        } catch (PDOException $e) {}

        // Rate-limit per cookie
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE cookie_id=? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $stmt->execute([$cookieId, $window]);
            if ((int)$stmt->fetchColumn() >= $maxAtt) return 'blocked';
        } catch (PDOException $e) {}

        // Cooldown de 5 secunde intre incercari
        try {
            $stmt = $db->prepare("SELECT MAX(attempted_at) FROM login_attempts WHERE ip=?");
            $stmt->execute([$ip]);
            $last = $stmt->fetchColumn();
            if ($last && (time() - strtotime($last)) < $cooldown) return 'cooldown';
        } catch (PDOException $e) {}

        return true;
    }

    public static function recordLoginAttempt(string $ip, string $cookieId): void {
        try {
            $db = DB::getInstance();
            $db->prepare("INSERT INTO login_attempts (ip, cookie_id, attempted_at) VALUES (?,?,NOW())")
               ->execute([$ip, $cookieId]);
        } catch (PDOException $e) {}
    }

    public static function clearLoginAttempts(string $ip, string $cookieId): void {
        try {
            $db = DB::getInstance();
            $db->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]);
            $db->prepare("DELETE FROM login_attempts WHERE cookie_id=?")->execute([$cookieId]);
        } catch (PDOException $e) {}
    }

    // ================================================================
    // HELPERS PRIVATE
    // ================================================================

    public static function getClientIP(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private static function getUAHash(): string {
        return md5($_SERVER['HTTP_USER_AGENT'] ?? '');
    }
}
