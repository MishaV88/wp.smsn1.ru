<?php
/**
 * Генерация PKCE-пары (RFC 7636) — без побочных эффектов, чтобы покрыть тестами.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Случайные байты в hex. random_bytes есть с PHP 7, openssl — с 5.3.
 *
 * @param int $bytes Длина.
 * @return string
 */
function wp_smsn1_ru_random_hex($bytes = 16)
{
    $bytes = max(8, (int) $bytes);

    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $raw = openssl_random_pseudo_bytes($bytes);
        if ($raw !== false) {
            return bin2hex($raw);
        }
    }

    // Крайний случай: не криптостойко, но лучше, чем ничего.
    return substr(md5(uniqid('', true) . mt_rand()), 0, $bytes * 2);
}

/**
 * code_verifier для PKCE: 43–128 символов из [A-Za-z0-9-._~].
 *
 * @return string
 */
function wp_smsn1_ru_pkce_verifier()
{
    return rtrim(strtr(base64_encode(wp_smsn1_ru_random_hex(32)), '+/', '-_'), '=');
}

/**
 * code_challenge = BASE64URL(SHA256(verifier)), метод S256.
 *
 * @param string $verifier code_verifier.
 * @return string
 */
function wp_smsn1_ru_pkce_challenge($verifier)
{
    return rtrim(strtr(base64_encode(hash('sha256', (string) $verifier, true)), '+/', '-_'), '=');
}

/**
 * UUID v4 — VK ID требует device_id при обмене кода.
 *
 * @return string
 */
function wp_smsn1_ru_device_id()
{
    $hex = wp_smsn1_ru_random_hex(16);
    $hex[12] = '4';

    $variant = dechex(8 | (hexdec($hex[16]) & 0x3));
    $hex[16] = $variant;

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}
