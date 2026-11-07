<?php
/**
 * Тесты логики OTP без WordPress: заглушки транзиентов и фильтров.
 *
 * Запуск: php tests/otp-test.php
 * Код возврата: 0 — все проверки прошли, 1 — есть ошибки.
 */

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['wpsmsn1_transients'] = array();

function get_transient($key)
{
    return isset($GLOBALS['wpsmsn1_transients'][$key])
        ? $GLOBALS['wpsmsn1_transients'][$key]['value']
        : false;
}

function set_transient($key, $value, $expiration = 0)
{
    $GLOBALS['wpsmsn1_transients'][$key] = array(
        'value'   => $value,
        'expires' => $expiration ? time() + (int) $expiration : 0,
    );

    return true;
}

function delete_transient($key)
{
    unset($GLOBALS['wpsmsn1_transients'][$key]);

    return true;
}

function apply_filters($tag, $value)
{
    return $value;
}

function wp_rand($min, $max)
{
    return random_int((int) $min, (int) $max);
}

require dirname(__DIR__) . '/includes/wp-smsn1-ru-otp.php';
require dirname(__DIR__) . '/includes/auth/functions-pkce.php';

$GLOBALS['wpsmsn1_failures'] = 0;
$GLOBALS['wpsmsn1_tests']    = 0;

function check($condition, $label)
{
    $GLOBALS['wpsmsn1_tests']++;

    if ($condition) {
        echo "ok   {$label}\n";
        return;
    }

    $GLOBALS['wpsmsn1_failures']++;
    echo "FAIL {$label}\n";
}

/* 1. Формат кода ------------------------------------------------------- */
$code = wp_smsn1_ru_otp_generate();
check((bool) preg_match('/^\d{5}$/', $code), 'код состоит из 5 цифр');

/* 2. Успешная проверка и расход кода ----------------------------------- */
$phone = '+79990000001';
$code  = wp_smsn1_ru_otp_generate();

check(wp_smsn1_ru_otp_store($phone, $code) === true, 'код сохраняется');
check(wp_smsn1_ru_otp_check($phone, $code) === true, 'верный код принимается');
check(wp_smsn1_ru_otp_get($phone) === false, 'после успеха код удалён');
check(wp_smsn1_ru_otp_check($phone, $code) !== true, 'использованный код повторно не принимается');

/* 3. Лимит неверных попыток -------------------------------------------- */
$phone = '+79990000002';
$code  = wp_smsn1_ru_otp_generate();
wp_smsn1_ru_otp_store($phone, $code);

$result = wp_smsn1_ru_otp_check($phone, '00000');
check(is_string($result) && strpos($result, 'Неверный код') === 0, 'неверный код отклоняется');

$record = wp_smsn1_ru_otp_get($phone);
check(is_array($record) && $record['attempts'] === 1, 'счётчик попыток увеличивается');

for ($i = 0; $i < 3; $i++) {
    wp_smsn1_ru_otp_check($phone, '00000');
}

$result = wp_smsn1_ru_otp_check($phone, '00000');
check(is_string($result) && strpos($result, 'Слишком много') !== false, 'после 5 попыток код блокируется');
check(wp_smsn1_ru_otp_get($phone) === false, 'после блокировки код удалён');
check(wp_smsn1_ru_otp_check($phone, $code) !== true, 'верный код после блокировки не принимается');

/* 4. Совместимость с кодами до 1.1.0 (4 цифры) ------------------------- */
$phone = '+79990000006';
$key   = wp_smsn1_ru_otp_phone_key($phone);
set_transient($key, '4321', 300);
check(wp_smsn1_ru_otp_check($phone, '4321') === true, 'старый 4-значный код принимается');
check(wp_smsn1_ru_otp_get($phone) === false, 'старый код после успеха удалён');

/* 5. Пауза между отправками -------------------------------------------- */
$phone = '+79990000003';
$ip    = '203.0.113.1';

check(wp_smsn1_ru_otp_can_send($phone, $ip) === true, 'первая отправка разрешена');

wp_smsn1_ru_otp_mark_sent($phone, $ip);
$result = wp_smsn1_ru_otp_can_send($phone, $ip);
check(is_string($result) && strpos($result, 'Повторить можно через') !== false, 'повторная отправка отложена по таймеру');

/* 6. Часовой лимит на номер -------------------------------------------- */
$phone = '+79990000004';
$ip    = '203.0.113.2';

for ($i = 0; $i < 3; $i++) {
    wp_smsn1_ru_otp_mark_sent($phone, $ip);
    delete_transient(wp_smsn1_ru_otp_cooldown_key($phone));
}

$result = wp_smsn1_ru_otp_can_send($phone, $ip);
check(is_string($result) && strpos($result, 'на этот номер') !== false, 'лимит 3 кода на номер за окно');

/* 7. Часовой лимит на IP ----------------------------------------------- */
$ip = '203.0.113.3';

for ($i = 0; $i < 10; $i++) {
    wp_smsn1_ru_otp_mark_sent('+7999100' . sprintf('%04d', $i), $ip);
}

$result = wp_smsn1_ru_otp_can_send('+79995550000', $ip);
check(is_string($result) && strpos($result, 'Повторите через') !== false, 'лимит 10 кодов на IP за окно');

/* 8. Требования к паролю (режим 2FA) -------------------------------- */
check(wp_smsn1_ru_password_problem('1234567') !== false, 'короткий пароль отклоняется');
check(wp_smsn1_ru_password_problem('12345678') === false, 'пароль из 8 символов принимается');

/* 9. PKCE (RFC 7636) -------------------------------------------------- */
check(
    wp_smsn1_ru_pkce_challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')
        === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    'PKCE S256 совпадает с вектором RFC 7636'
);
check(strlen(wp_smsn1_ru_pkce_verifier()) >= 43, 'code_verifier не короче 43 символов');
check(
    (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', wp_smsn1_ru_device_id()),
    'device_id — UUID v4'
);

/* 10. Независимость номеров --------------------------------------------- */
check(wp_smsn1_ru_otp_can_send('+79998887766', '203.0.113.4') === true, 'другой номер с другого IP не заблокирован');

/* Итог ---------------------------------------------------------------- */
echo "\n";
echo 'Проверок: ' . $GLOBALS['wpsmsn1_tests'] . ', ошибок: ' . $GLOBALS['wpsmsn1_failures'] . "\n";

exit($GLOBALS['wpsmsn1_failures'] === 0 ? 0 : 1);
