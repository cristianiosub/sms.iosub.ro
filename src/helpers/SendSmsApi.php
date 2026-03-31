<?php
class SendSmsApi {
    private string $username;
    private string $apiKey;
    private int $timeout = 15;

    public function __construct(string $username, string $apiKey) {
        $this->username = $username;
        $this->apiKey   = $apiKey;
    }

    private function call(string $action, array $params = []): array {
        $params = array_merge($params, [
            'action'   => $action,
            'username' => $this->username,
            'password' => $this->apiKey,
        ]);
        $url = SENDSMS_API_BASE . '?' . http_build_query($params);
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Connection: keep-alive'],
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            error_log("SendSMS cURL error [$errno]: $error");
            return ['status' => -999, 'message' => 'Network error'];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log("SendSMS invalid response for action=$action: $response");
            return ['status' => -998, 'message' => 'Invalid API response', 'raw' => substr($response, 0, 200)];
        }
        return $data;
    }

    public function sendMessage(string $to, string $text, string $from = '', ?string $reportUrl = null, int $reportMask = 19): array {
        $params = [
            'to'                   => $this->normalizePhone($to),
            'text'                 => $text,
            'from'                 => $from ?: '',
            'report_mask'          => $reportMask,
            'charset'              => 'UTF-8',
            'auto_detect_encoding' => 1,
        ];
        if ($reportUrl) {
            $params['report_url'] = $reportUrl;
        }
        return $this->call('message_send', $params);
    }

    public function messageStatus(string $uuid): array {
        return $this->call('message_status', ['message_id' => $uuid]);
    }

    public function parseMessageStatus(array $response): array {
        $parsed = ['status' => -1, 'cost' => null, 'parts' => 1];
        if (!isset($response['status']) || $response['status'] < 0) return $parsed;
        $det = $response['details'] ?? null;
        if (is_string($det)) $det = json_decode($det, true) ?: null;
        if (is_array($det)) {
            $parsed['status'] = isset($det['status']) ? (int)$det['status'] : -1;
            $parsed['cost']   = isset($det['cost'])   ? (float)$det['cost'] : null;
            $parsed['parts']  = isset($det['parts'])  ? (int)$det['parts']  : 1;
        } elseif (isset($response['details']) && is_numeric($response['details'])) {
            $parsed['status'] = (int)$response['details'];
        }
        return $parsed;
    }

    public function getBalance(): array {
        return $this->call('user_get_balance');
    }

    /**
     * Returneaza lista de senderi aprobati din contul sendsms.ro
     * Raspuns: ['status'=>0, 'details'=>[['name'=>'CyberShield','status'=>1], ...]]
     * status sender: 1=aprobat, 0=in asteptare, -1=respins
     */
    public function getSenders(): array {
        $result = $this->call('user_get_sender_names');
        if (!isset($result['status']) || $result['status'] < 0) {
            return ['success' => false, 'senders' => [], 'error' => $result['message'] ?? 'API error'];
        }
        $details = $result['details'] ?? [];
        $senders = [];

        if (is_array($details)) {
            foreach ($details as $key => $val) {
                if (is_array($val)) {
                    // Format 1: [{"name":"CyberShield","status":1}, ...]
                    if (isset($val['name'])) {
                        $senders[] = ['name' => $val['name'], 'status' => (int)($val['status'] ?? 1)];
                    }
                } elseif (is_string($key) && (is_int($val) || is_string($val))) {
                    // Format 2: {"CyberShield": 1, "AltSender": 0}
                    $senders[] = ['name' => $key, 'status' => (int)$val];
                } elseif (is_string($val)) {
                    // Format 3: ["CyberShield", "AltSender"]
                    $senders[] = ['name' => $val, 'status' => 1];
                }
            }
        }

        // Filtreaza: preferinta pentru aprobati (status=1), fallback la toti
        $approved = array_values(array_filter($senders, fn($s) => $s['status'] == 1));
        $names    = array_column(empty($approved) ? $senders : $approved, 'name');

        if (empty($names)) {
            error_log('SendSmsApi getSenders: no senders found. raw details: ' . json_encode($details));
        }

        return ['success' => true, 'senders' => $names, 'raw' => $details];
    }

    public function getLastMessages(): array {
        return $this->call('user_get_last_messages');
    }

    public function ping(): bool {
        $r = $this->call('user_get_balance');
        return isset($r['status']) && $r['status'] >= 0;
    }

    public static function normalizePhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '4' . $phone;
        }
        if (strlen($phone) === 9) {
            $phone = '40' . $phone;
        }
        return $phone;
    }
}
