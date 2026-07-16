<?php

namespace App\Service\Settings;

/**
 * At-rest encryption for the SMTP password stored on EmailSettings.
 *
 * Goals:
 *  - the database dump alone is not enough to recover the password
 *  - the value is recoverable on the application server using APP_SECRET
 *
 * Limitations to keep in mind for the MVP:
 *  - anyone with read access to APP_SECRET (or to the running PHP process)
 *    can decrypt the password - this only protects against database
 *    exfiltration scenarios, not full application compromise
 *  - APP_SECRET must remain stable; rotating it invalidates stored
 *    ciphertext and the password will need to be re-entered
 *
 * Output format: `enc1:` + base64(nonce || ciphertext). The prefix lets us
 * detect ciphertext vs. plain/legacy values and skip decryption gracefully.
 */
class SmtpPasswordEncrypter
{
    private const PREFIX = 'enc1:';

    /**
     * 32-byte symmetric key derived from the application secret.
     */
    private string $key;

    public function __construct(string $appSecret)
    {
        if ($appSecret === '') {
            throw new \LogicException('APP_SECRET must be configured to encrypt SMTP credentials.');
        }

        $this->key = sodium_crypto_generichash(
            'carstio.smtp.v1|' . $appSecret,
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * Returns the decrypted password, or null if the input is empty,
     * malformed, or fails authentication (e.g. APP_SECRET changed).
     * Never throws, so callers that only need to know "is a password set"
     * can keep working when the ciphertext is unreadable.
     */
    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!str_starts_with($value, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        return $plaintext === false ? null : $plaintext;
    }
}
