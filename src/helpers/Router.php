<?php
class Router {
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $this->patternToRegex($pattern),
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable $handler): void  { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, callable $handler): void { $this->add('POST', $pattern, $handler); }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = '/' . trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') continue;
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        http_response_code(404);
        require __DIR__ . '/../../templates/404.php';
    }

    private function patternToRegex(string $pattern): string {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    // Includem noul CSRF token ca JS-ul sa-l actualizeze dupa rotatie
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[CSRF_TOKEN_NAME])) {
        $data['_csrf_token'] = $_SESSION[CSRF_TOKEN_NAME];
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function csrfFail(): void {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); exit;
    }
}

function requireClient(): array {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

    $clientId = Auth::activeClientId();
    if (!$clientId) {
        if ($isAjax) jsonResponse(['error' => 'Niciun client selectat', 'redirect' => '/select-client'], 403);
        redirect('/select-client'); exit;
    }

    $client = DB::fetchOne('SELECT * FROM clients WHERE id=? AND is_active=1', [$clientId]);
    if (!$client) {
        Auth::setActiveClient(null);
        if ($isAjax) jsonResponse(['error' => 'Clientul nu mai este activ', 'redirect' => '/select-client'], 403);
        redirect('/select-client'); exit;
    }

    $access = DB::fetchOne(
        'SELECT 1 FROM user_clients WHERE user_id=? AND client_id=?',
        [Auth::userId(), $clientId]
    );
    if (!$access && !Auth::isSuperAdmin()) { http_response_code(403); die('Access denied'); }
    return $client;
}

/**
 * Returneaza adapterul SMS corect pentru clientul dat.
 * @return SendSmsApi|SmsApiRo|SmsoRo
 */
function getApiForClient(array $client): SendSmsApi|SmsApiRo|SmsoRo {
    $provider = $client['sms_provider'] ?? 'sendsms';

    if ($provider === 'smsapi' && !empty($client['smsapi_token'])) {
        return new SmsApiRo($client['smsapi_token']);
    }

    if ($provider === 'smso' && !empty($client['smso_token'])) {
        return new SmsoRo($client['smso_token']);
    }

    return new SendSmsApi($client['sendsms_username'], $client['sendsms_apikey']);
}

/**
 * Inlocuieste variabilele din mesaj, inclusiv {optout_url}.
 */
function interpolateMessage(string $text, array $contact, int $clientId): string {
    $phone     = $contact['phone'] ?? '';
    $token     = substr(hash_hmac('sha256', $phone . ':' . $clientId, SECRET_KEY), 0, 32);
    $optoutUrl = BASE_URL . '/stop?phone=' . urlencode($phone) . '&c=' . $clientId . '&t=' . $token;

    return str_replace(
        ['{prenume}', '{nume}', '{telefon}', '{optout_url}'],
        [$contact['first_name'] ?? '', $contact['last_name'] ?? '', $phone, $optoutUrl],
        $text
    );
}

function audit(string $action, ?string $entityType = null, ?string $entityId = null): void {
    $clientId = Auth::activeClientId();
    try {
        DB::execute(
            'INSERT INTO audit_log (user_id, client_id, action, entity_type, entity_id, ip, user_agent) VALUES (?,?,?,?,?,?,?)',
            [
                Auth::userId(), $clientId, $action, $entityType, $entityId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 299),
            ]
        );
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}
