<?php
/**
 * SmsoRo — Adapter pentru smso.ro
 * API Base: https://app.smso.ro/api/v1/
 * Auth: Header X-Authorization: API-KEY
 */
class SmsoRo {
    private string $apiKey;
    private int $timeout = 15;
    private const BASE = 'https://app.smso.ro/api/v1';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    private function request(string $method, string $path, array $params = []): array {
        $url  = self::BASE . $path;
        $ch   = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'X-Authorization: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($params) {
            $url .= '?' . http_build_query($params);
        }

        $opts[CURLOPT_URL] = $url;
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            error_log("SmsoRo cURL [$errno]: $error on $path");
            return ['__error' => true, 'message' => 'Network error', '__http_code' => 0];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log("SmsoRo invalid JSON [$httpCode] on $path: " . substr($response ?? '', 0, 300));
            return ['__error' => true, 'message' => 'Invalid API response', '__http_code' => $httpCode];
        }

        // Stocam http_code cu prefix __ ca sa nu interfere cu datele
        $data['__http_code'] = $httpCode;
        return $data;
    }

    /**
     * Lista senderi disponibili.
     * Raspuns SMSO: [{"id":4,"name":"CyberShield","pricePerMessage":3}, ...]
     * Array direct, nu obiect wrapper.
     */
    public function getSenders(): array {
        $result = $this->request('GET', '/senders');

        if (!empty($result['__error'])) {
            return ['success' => false, 'senders' => [], 'sender_map' => [], 'error' => $result['message'] ?? 'API error'];
        }

        $senders   = [];
        $senderMap = [];

        // SMSO returneaza array direct: [{"id":4,"name":"..."},...]
        // Filtram cheile cu __ (metadate interne) si iteram doar obiectele cu id+name
        foreach ($result as $key => $s) {
            if (str_starts_with((string)$key, '__')) continue; // sari metadatele interne
            if (!is_array($s) || !isset($s['id'], $s['name'])) continue;
            $senders[]             = $s['name'];
            $senderMap[$s['name']] = (int)$s['id'];
        }

        return [
            'success'    => true,
            'senders'    => $senders,
            'sender_map' => $senderMap,
        ];
    }

    /**
     * Trimite SMS. Sender = ID numeric la SMSO (cauta in lista daca e string).
     */
    public function sendMessage(string $to, string $text, string $from = '', ?string $reportUrl = null): array {
        $senderId = null;

        if (is_numeric($from) && (int)$from > 0) {
            $senderId = (int)$from;
        } elseif ($from) {
            $sendersResult = $this->getSenders();
            $senderId      = $sendersResult['sender_map'][$from] ?? null;
            if (!$senderId && !empty($sendersResult['sender_map'])) {
                // Fallback: primul sender disponibil
                $senderId = reset($sendersResult['sender_map']);
                error_log("SmsoRo: sender '$from' not found, fallback to id=$senderId");
            }
        }

        if (!$senderId) {
            // Ultima sansa: ia primul sender din cont
            $sendersResult = $this->getSenders();
            if (!empty($sendersResult['sender_map'])) {
                $senderId = reset($sendersResult['sender_map']);
                error_log("SmsoRo: no sender specified, using first available id=$senderId");
            }
        }

        if (!$senderId) {
            return ['success' => false, 'error' => 'Niciun sender disponibil in contul SMSO'];
        }

        $params = [
            'to'     => self::normalizePhone($to),
            'body'   => $text,
            'sender' => $senderId,
        ];
        if ($reportUrl) {
            $params['webhook_status'] = $reportUrl;
        }

        $result = $this->request('POST', '/send', $params);

        // Succes: {"status":200,"responseToken":"uuid","transaction_cost":3.5}
        // transaction_cost e in eurocenti — convertim in EUR
        if (($result['status'] ?? 0) === 200 && isset($result['responseToken'])) {
            return [
                'success'         => true,
                'provider_msg_id' => $result['responseToken'],
                'cost'            => isset($result['transaction_cost'])
                                     ? round((float)$result['transaction_cost'] / 100, 4)
                                     : null,
            ];
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? ('HTTP ' . ($result['__http_code'] ?? '?'));
        error_log("SmsoRo sendMessage error: " . json_encode($result));
        return ['success' => false, 'error' => $errorMsg];
    }

    /**
     * Status mesaj dupa responseToken.
     */
    public function messageStatus(string $responseToken): array {
        $result = $this->request('GET', '/status', ['responseToken' => $responseToken]);

        if (($result['status'] ?? 0) !== 200 || !isset($result['data'])) {
            return ['delivered' => false, 'failed' => false, 'status' => 'UNKNOWN', 'cost' => null];
        }

        $d      = $result['data'];
        $status = strtolower($d['status'] ?? '');
        return [
            'delivered'    => $status === 'delivered',
            'failed'       => in_array($status, ['undelivered', 'expired', 'error']),
            'status'       => strtoupper($status),
            'cost'         => null,
            'delivered_at' => $d['delivered_at'] ?? null,
        ];
    }

    /**
     * Sold cont. credit_value e direct in EUR (ex: 3.5 = 3,50 EUR).
     */
    public function getBalance(): array {
        $result = $this->request('GET', '/credit-check');

        if (($result['status'] ?? 0) === 200 && isset($result['credit_value'])) {
            return [
                'success'  => true,
                'balance'  => round((float)$result['credit_value'], 4),
                'currency' => 'EUR',
            ];
        }

        error_log("SmsoRo getBalance error: " . json_encode($result));
        return ['success' => false, 'balance' => null, 'currency' => 'EUR'];
    }

    public static function parseDlrWebhook(array $post): array {
        $status = strtolower($post['status'] ?? '');
        return [
            'provider_msg_id' => $post['uuid'] ?? null,
            'delivered'       => $status === 'delivered',
            'failed'          => in_array($status, ['undelivered', 'expired', 'error']),
            'status'          => strtoupper($status),
            'sent_at'         => $post['sent_at'] ?? null,
            'delivered_at'    => $post['delivered_at'] ?? null,
        ];
    }

    public static function normalizePhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) $phone = '4' . $phone;
        if (strlen($phone) === 9) $phone = '40' . $phone;
        return '+' . $phone;
    }

    public function ping(): bool {
        return ($this->getBalance()['success'] ?? false) === true;
    }
}
