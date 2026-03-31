<?php
/**
 * ProviderFactory — returneaza adapterul corect pe baza configuratiei clientului.
 * Provideri suportati: sendsms, smsapi, smso
 */
class ProviderFactory {

    /**
     * @return SendSmsApi|SmsApiRo|SmsoRo
     */
    public static function make(array $client): SendSmsApi|SmsApiRo|SmsoRo {
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
     * Trimite SMS si salveaza in DB.
     * Verifica blacklist si duplicate automat.
     */
    public static function sendAndSave(
        array $client,
        string $phone,
        string $text,
        string $sender = '',
        ?int $campaignId = null,
        ?int $contactId = null
    ): array {
        $provider = $client['sms_provider'] ?? 'sendsms';
        $api      = self::make($client);

        // Normalizeaza numarul in functie de provider
        if ($provider === 'smso') {
            $normalPhone = SmsoRo::normalizePhone($phone);
            // SmsoRo adauga + — pentru DB salvam fara +
            $dbPhone = ltrim($normalPhone, '+');
        } elseif ($provider === 'smsapi') {
            $normalPhone = SmsApiRo::normalizePhone($phone);
            $dbPhone     = $normalPhone;
        } else {
            $normalPhone = SendSmsApi::normalizePhone($phone);
            $dbPhone     = $normalPhone;
        }

        // Verifica blacklist
        $blocked = DB::fetchOne(
            'SELECT id FROM optout WHERE client_id=? AND phone IN (?,?)',
            [$client['id'], $dbPhone, ltrim($dbPhone, '+')]
        );
        if ($blocked) {
            return ['success' => false, 'error' => 'Numar in blacklist', 'blocked' => true];
        }

        // Detectie duplicate — acelasi mesaj in ultimele 5 minute
        $dup = DB::fetchOne(
            'SELECT id FROM messages WHERE client_id=? AND phone=? AND message_text=?
             AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND status != 32 LIMIT 1',
            [$client['id'], $dbPhone, $text]
        );
        if ($dup) {
            return ['success' => false, 'error' => 'Mesaj duplicat (trimis recent)', 'duplicate' => true];
        }

        // Inserare initiala
        $msgId = DB::insert(
            'INSERT INTO messages (client_id, campaign_id, contact_id, phone, message_text, sender, provider, status, sent_at)
             VALUES (?,?,?,?,?,?,?,0,NOW())',
            [$client['id'], $campaignId, $contactId, $dbPhone, $text, $sender, $provider]
        );

        $reportUrl = REPORT_URL_BASE . '?msg=' . $msgId . '&provider=' . $provider;
        $result    = $api->sendMessage($normalPhone, $text, $sender, $reportUrl);

        if ($result['success'] ?? false) {
            $providerMsgId = $result['provider_msg_id'] ?? $result['details'] ?? null;

            // SMSO returneaza costul direct la trimitere (eurocents -> EUR deja convertit)
            $cost = isset($result['cost']) && $result['cost'] > 0 ? $result['cost'] : null;

            DB::execute(
                'UPDATE messages SET status=1, sendsms_uuid=?, provider_msg_id=?' .
                ($cost ? ', cost=?' : '') . ' WHERE id=?',
                $cost
                    ? [$providerMsgId, $providerMsgId, $cost, $msgId]
                    : [$providerMsgId, $providerMsgId, $msgId]
            );
            return ['success' => true, 'msg_id' => $msgId, 'provider_msg_id' => $providerMsgId];
        } else {
            $errorMsg = $result['error'] ?? $result['message'] ?? 'Unknown';
            DB::execute('UPDATE messages SET status=32, failed_at=NOW() WHERE id=?', [$msgId]);
            return ['success' => false, 'error' => $errorMsg, 'msg_id' => $msgId];
        }
    }
}
