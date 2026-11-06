<?php
/**
 * OTP: генерация, хранение, проверка с ограничением попыток и защитой от SMS-бомбинга.
 *
 * Модуль намеренно не содержит побочных эффектов (хуков/вывода), чтобы его можно было
 * покрыть тестами без WordPress: tests/otp-test.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_SMSN1_RU_OTP_TTL')) {
    define('WP_SMSN1_RU_OTP_TTL', 90); // 90 секунд
}

/**
 * Ключ транзиента с кодом. Телефон хэшируется, чтобы номер не лежал в имени опции.
 */
function wp_smsn1_ru_otp_phone_key($phone)
{
    return 'wp_smsn1_ru_code_' . md5('phone|' . $phone);
}

function wp_smsn1_ru_otp_generate()
{
    return (string) wp_rand(10000, 99999);
}

/**
 * Сохраняет код. Возвращает true при успехе.
 */
function wp_smsn1_ru_otp_store($phone, $code)
{
    return set_transient(
        wp_smsn1_ru_otp_phone_key($phone),
        array(
            'code'     => (string) $code,
            'attempts' => 0,
            'expires'  => time() + WP_SMSN1_RU_OTP_TTL,
        ),
        WP_SMSN1_RU_OTP_TTL
    );
}

function wp_smsn1_ru_otp_clear($phone)
{
    delete_transient(wp_smsn1_ru_otp_phone_key($phone));
}

/**
 * Читает запись о коде. Поддерживает формат до 1.1.0 (строка с кодом).
 *
 * @return array|false
 */
function wp_smsn1_ru_otp_get($phone)
{
    $stored = get_transient(wp_smsn1_ru_otp_phone_key($phone));

    if ($stored === false) {
        return false;
    }

    if (is_array($stored) && isset($stored['code'])) {
        return array(
            'code'     => (string) $stored['code'],
            'attempts' => isset($stored['attempts']) ? (int) $stored['attempts'] : 0,
            'expires'  => isset($stored['expires']) ? (int) $stored['expires'] : time() + WP_SMSN1_RU_OTP_TTL,
            'legacy'   => false,
        );
    }

    return array(
        'code'     => (string) $stored,
        'attempts' => 0,
        'expires'  => time() + WP_SMSN1_RU_OTP_TTL,
        'legacy'   => true,
    );
}

function wp_smsn1_ru_otp_max_attempts()
{
    return max(1, (int) apply_filters('wp_smsn1_ru_otp_max_attempts', 5));
}

/**
 * Проверяет код.
 *
 * @param string $phone   Номер в формате +7XXXXXXXXXX.
 * @param string $code    Введённый пользователем код.
 * @param bool   $consume Удалять код после успешной проверки (для checkout — false,
 *                        чтобы повторная валидация заказа не сломала оформление).
 * @return true|string true при успехе, иначе текст ошибки для пользователя.
 */
function wp_smsn1_ru_otp_check($phone, $code, $consume = true)
{
    $record = wp_smsn1_ru_otp_get($phone);

    if ($record === false) {
        return 'Код не найден или истёк. Запросите новый код.';
    }

    $code = (string) $code;

    // Коды, выданные до 1.1.0, были четырёхзначными: они доживают свой TTL.
    $expected_length = !empty($record['legacy']) ? 4 : 5;

    if (strlen($code) === $expected_length && hash_equals($record['code'], $code)) {
        if ($consume) {
            wp_smsn1_ru_otp_clear($phone);
        }
        return true;
    }

    $attempts = $record['attempts'] + 1;
    $max      = wp_smsn1_ru_otp_max_attempts();

    if ($attempts >= $max) {
        wp_smsn1_ru_otp_clear($phone);
        return 'Слишком много неверных попыток. Запросите новый код.';
    }

    $ttl                  = max(1, $record['expires'] - time());
    $record['attempts']   = $attempts;
    set_transient(wp_smsn1_ru_otp_phone_key($phone), $record, $ttl);

    return 'Неверный код. Осталось попыток: ' . ($max - $attempts);
}

/* -------------------------------------------------------------------------
 * Ограничение отправки СМС
 * ---------------------------------------------------------------------- */

function wp_smsn1_ru_otp_cooldown_key($phone)
{
    return 'wp_smsn1_ru_send_cd_' . md5('phone|' . $phone);
}

/**
 * Окно лимитов на отправку. По умолчанию 20 минут.
 */
function wp_smsn1_ru_otp_limit_window()
{
    return max(60, (int) apply_filters('wp_smsn1_ru_send_window', 20 * MINUTE_IN_SECONDS));
}

function wp_smsn1_ru_otp_phone_window_key($phone)
{
    $window = wp_smsn1_ru_otp_limit_window();

    return 'wp_smsn1_ru_send_ph_' . md5('phone|' . $phone) . '_' . (int) floor(time() / $window);
}

function wp_smsn1_ru_otp_ip_window_key($ip)
{
    $window = wp_smsn1_ru_otp_limit_window();

    return 'wp_smsn1_ru_send_ip_' . md5('ip|' . $ip) . '_' . (int) floor(time() / $window);
}

/**
 * IP клиента. Заголовкам X-Forwarded-For не доверяем: их подставляет кто угодно.
 */
function wp_smsn1_ru_otp_client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function wp_smsn1_ru_otp_cooldown_seconds()
{
    return max(0, (int) apply_filters('wp_smsn1_ru_send_cooldown', 60));
}

function wp_smsn1_ru_otp_phone_limit()
{
    return max(1, (int) apply_filters('wp_smsn1_ru_send_limit_phone', 3));
}

function wp_smsn1_ru_otp_ip_limit()
{
    return max(1, (int) apply_filters('wp_smsn1_ru_send_limit_ip', 10));
}

/**
 * Сколько осталось до конца окна лимитов — в секундах или минутах.
 */
function wp_smsn1_ru_otp_window_left_text()
{
    $window = wp_smsn1_ru_otp_limit_window();
    $left   = $window - (time() % $window);

    if ($left < 120) {
        return $left . ' сек.';
    }

    return (int) ceil($left / 60) . ' мин.';
}

/**
 * Можно ли отправить код на этот номер.
 *
 * @return true|string true, если можно; иначе текст ошибки.
 */
function wp_smsn1_ru_otp_can_send($phone, $ip = null)
{
    if ($ip === null) {
        $ip = wp_smsn1_ru_otp_client_ip();
    }

    $cooldown_until = (int) get_transient(wp_smsn1_ru_otp_cooldown_key($phone));
    if ($cooldown_until > time()) {
        return 'Код уже отправлен. Повторить можно через ' . ($cooldown_until - time()) . ' сек.';
    }

    $phone_count = (int) get_transient(wp_smsn1_ru_otp_phone_window_key($phone));
    if ($phone_count >= wp_smsn1_ru_otp_phone_limit()) {
        return 'Слишком много запросов кода на этот номер. Повторите через ' . wp_smsn1_ru_otp_window_left_text();
    }

    $ip_count = (int) get_transient(wp_smsn1_ru_otp_ip_window_key($ip));
    if ($ip_count >= wp_smsn1_ru_otp_ip_limit()) {
        return 'Слишком много запросов. Повторите через ' . wp_smsn1_ru_otp_window_left_text();
    }

    return true;
}

/**
 * Отмечает факт успешной отправки: включает задержку и счётчики окна.
 */
function wp_smsn1_ru_otp_mark_sent($phone, $ip = null)
{
    if ($ip === null) {
        $ip = wp_smsn1_ru_otp_client_ip();
    }

    $cooldown = wp_smsn1_ru_otp_cooldown_seconds();
    if ($cooldown > 0) {
        set_transient(wp_smsn1_ru_otp_cooldown_key($phone), time() + $cooldown, $cooldown);
    }

    $window = wp_smsn1_ru_otp_limit_window();

    set_transient(
        wp_smsn1_ru_otp_phone_window_key($phone),
        (int) get_transient(wp_smsn1_ru_otp_phone_window_key($phone)) + 1,
        $window
    );

    set_transient(
        wp_smsn1_ru_otp_ip_window_key($ip),
        (int) get_transient(wp_smsn1_ru_otp_ip_window_key($ip)) + 1,
        $window
    );
}
