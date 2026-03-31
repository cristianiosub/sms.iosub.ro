<?php
class SmsApiRo {
    private string $token;
    private int $timeout = 15;
    private const BASE = 'https://api.smsapi.ro';

    public function __construct(string $token) {
        $this->token = $token;
    }

    private function post(string $path, array $params = []): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            error_log("SmsApiRo cURL error [$errno]: $error");
            return ['error' => true, 'message' => 'Network error'];
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log("SmsApiRo invalid response [$httpCode]: $response");
            return ['error' => true, 'message' => 'Invalid API response', 'raw' => substr($response, 0, 200)];
        }
        return $data;
    }

    private function get(string $path): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->token],
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);
        if ($errno) return ['error' => true, 'message' => 'Network error'];
        $data = json_decode($response, true);
        return is_array($data) ? $data : ['error' => true, 'raw' => substr($response ?? '', 0, 200)];
    }

    public function sendMessage(string $to, string $text, string $from = '', ?string $reportUrl = null): array {
        $params = [
            'to'      => self::normalizePhone($to),
            'message' => $text,
            'format'  => 'json',
        ];
        if ($from) $params['from'] = $from;
        if ($reportUrl) $params['notify_url'] = $reportUrl;
        if (!self::isGsm7($text)) $params['encoding'] = 'utf-8';

        $result = $this->post('/sms.do', $params);

        if (isset($result['list'][0]['id'])) {
            $msg = $result['list'][0];
            return [
                'success'         => true,
                'provider_msg_id' => $msg['id'],
                'status'          => $msg['status'] ?? 'QUEUE',
                'points'          => $result['points'] ?? null,
            ];
        }
        $errorMsg = $result['message'] ?? ($result['invalid_numbers']['message'] ?? 'Unknown error');
        error_log("SmsApiRo sendMessage error: " . json_encode($result));
        return ['success' => false, 'error' => $errorMsg, 'raw' => $result];
    }

    public function messageStatus(string $msgId): array {
        $result = $this->get('/sms.do?action=check_idx&unique_id=' . urlencode($msgId) . '&format=json');
        if (isset($result['list'][0])) {
            $msg    = $result['list'][0];
            $status = strtoupper($msg['status'] ?? '');
            return [
                'delivered' => $status === 'DELIVERED',
                'failed'    => in_array($status, ['UNDELIVERED', 'EXPIRED', 'UNKNOWN']),
                'status'    => $status,
                'cost'      => isset($msg['points']) ? (float)$msg['points'] : null,
                'raw'       => $msg,
            ];
        }
        return ['delivered' => false, 'failed' => false, 'status' => 'UNKNOWN', 'cost' => null];
    }

    public function getBalance(): array {
        $result = $this->get('/user.do?format=json&credits=1');
        if (isset($result['points'])) {
            return ['success' => true, 'balance' => (float)$result['points'], 'currency' => 'points'];
        }
        return ['success' => false, 'balance' => null];
    }

    /**
     * Returneaza lista de senderi aprobati din contul smsapi.ro
     * GET /sender.do?format=json
     * Raspuns: {"list":[{"sender":"CyberShield","status":"ACTIVE","default":true}, ...]}
     */
    public function getSenders(): array {
        $result = $this->get('/sender.do?format=json');

        if (isset($result['error']) && $result['error']) {
            return ['success' => false, 'senders' => [], 'error' => $result['message'] ?? 'API error'];
        }

        $senders = [];
        $list    = $result['list'] ?? $result['collection'] ?? [];
        if (is_array($list)) {
            foreach ($list as $s) {
                $name   = $s['sender'] ?? $s['name'] ?? null;
                $status = strtoupper($s['status'] ?? 'ACTIVE');
                if ($name && in_array($status, ['ACTIVE', 'APPROVED', ''])) {
                    $senders[] = $name;
                }
            }
        }
        return ['success' => true, 'senders' => $senders, 'raw' => $list];
    }

    public static function isGsm7(string $text): bool {
        static $gsm7 = "@£\$¥èéùìòÇ\nØø\rÅå\x1BΔ_ΦΓΛΩΠΨΣΘΞ !\"%&'()*+,-./" .
                       '0123456789:;<=>?' .
                       '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§' .
                       '¿abcdefghijklmnopqrstuvwxyzäöñüà';
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            if (strpos($gsm7, $text[$i]) === false) return false;
        }
        return true;
    }

    public static function normalizePhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) $phone = '4' . $phone;
        if (strlen($phone) === 9) $phone = '40' . $phone;
        return $phone;
    }

    public function ping(): bool {
        return $this->getBalance()['success'] === true;
    }
}
