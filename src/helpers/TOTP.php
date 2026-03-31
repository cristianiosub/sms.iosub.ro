<?php
/**
 * TOTP (Time-based One-Time Password) — RFC 6238
 * Pure PHP, zero dependinte externe.
 */
class TOTP {
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS    = 6;
    private const PERIOD    = 30;  // secunde
    private const ALGORITHM = 'sha1';
    private const SECRET_BYTES = 20; // 160 biti

    // ----------------------------------------------------------------
    // API public
    // ----------------------------------------------------------------

    /** Genereaza un secret nou (base32, 32 caractere). */
    public static function generateSecret(): string {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * Verifica un cod TOTP.
     * $discrepancy = numarul de ferestre acceptate inainte/dupa (implicit 1 = ±30s).
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1): bool {
        $code = preg_replace('/\s/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $timestamp = (int)floor(time() / self::PERIOD);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (hash_equals(self::hotp($secret, $timestamp + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returneaza URI-ul de provizionare pentru Google Authenticator / Authy.
     * otpauth://totp/Issuer:account?secret=...&issuer=...
     */
    public static function getProvisioningUri(string $secret, string $account, string $issuer = 'SMS Platform'): string {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Asigura ca tabelul users are coloanele TOTP.
     * Se apeleaza o singura data la setup.
     */
    public static function ensureColumns(): void {
        try {
            $db = DB::getInstance();
            // Adauga coloanele daca nu exista (MySQL 8+ / MariaDB 10.3+)
            $db->exec("ALTER TABLE users
                ADD COLUMN IF NOT EXISTS totp_secret  VARCHAR(64)  NULL DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1)   NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS totp_pending VARCHAR(64)  NULL DEFAULT NULL
            ");
        } catch (PDOException $e) {
            // Ignora eroarea daca coloanele exista deja (MySQL < 8 nu suporta IF NOT EXISTS)
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log('TOTP ensureColumns: ' . $e->getMessage());
            }
        }
    }

    // ----------------------------------------------------------------
    // Implementare interna
    // ----------------------------------------------------------------

    /** HOTP (RFC 4226): genereaza codul pentru un counter dat. */
    private static function hotp(string $secret, int $counter): string {
        $key  = self::base32Decode($secret);
        // Pack counter ca 64-bit big-endian (PHP >= 5.6.3)
        $msg  = pack('J', $counter);
        $hmac = hash_hmac(self::ALGORITHM, $msg, $key, true);

        // Dynamic truncation
        $offset = ord($hmac[19]) & 0x0F;
        $otp = (
              ((ord($hmac[$offset])     & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) <<  8)
            |  (ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string {
        $output = '';
        $v      = 0;
        $bits   = 0;
        foreach (str_split($data) as $char) {
            $v    = ($v << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits   -= 5;
                $output .= self::ALPHABET[($v >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $output .= self::ALPHABET[($v << (5 - $bits)) & 0x1F];
        }
        return $output;
    }

    private static function base32Decode(string $data): string {
        $data   = strtoupper(preg_replace('/[\s=]/', '', $data));
        $output = '';
        $v      = 0;
        $bits   = 0;
        foreach (str_split($data) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) continue;
            $v    = ($v << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $output .= chr(($v >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
