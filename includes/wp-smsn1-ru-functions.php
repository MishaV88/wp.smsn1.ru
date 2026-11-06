<?php
/**
 * Клиент API smsn1.ru: подпись, отправка СМС, баланс, отправители.
 *
 * Все запросы идут по HTTPS через wp_remote_get() с таймаутом. Схема подписи
 * (md5 от конкатенации параметров) задана протоколом провайдера — её нельзя
 * заменить на HMAC без изменения на стороне сервиса, поэтому алгоритм сохранён
 * и вынесен в фильтр wp_smsn1_ru_signature.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_SMSN1_RU_API_HOST')) {
    define('WP_SMSN1_RU_API_HOST', 'direct.docs-group.ru');
}
if (!defined('WP_SMSN1_RU_BALANCE_ERROR')) {
    define('WP_SMSN1_RU_BALANCE_ERROR', 'Проблема с соединением');
}
if (!defined('WP_SMSN1_RU_API_TIMEOUT')) {
    define('WP_SMSN1_RU_API_TIMEOUT', 8);
}

/**
 * Подпись запроса по протоколу smsn1.ru: md5 от конкатенации параметров и API-ключа.
 */
function wp_smsn1_ru_Signature_md5($params, $api_key)
{
    ksort($params);
    reset($params);

    $signature = md5(implode($params) . $api_key);

    /**
     * Позволяет заменить схему подписи, когда провайдер её обновит.
     *
     * @param string $signature Подпись.
     * @param array  $params    Параметры запроса.
     * @param string $api_key   API-ключ.
     */
    return apply_filters('wp_smsn1_ru_signature', $signature, $params, $api_key);
}

/**
 * Запрос к API провайдера.
 *
 * @param string $endpoint Имя endpoint'а: timestamp, send, balance, senders.
 * @param array  $params   Параметры запроса.
 * @param bool   $raw      true — вернуть тело ответа строкой (timestamp.php отдаёт число).
 * @return array|string|WP_Error
 */
function wp_smsn1_ru_api_request($endpoint, array $params, $raw = false)
{
    $url = add_query_arg(
        $params,
        'https://' . WP_SMSN1_RU_API_HOST . '/get/' . rawurlencode($endpoint) . '.php'
    );

    $args = array(
        'timeout'     => (int) apply_filters('wp_smsn1_ru_api_timeout', WP_SMSN1_RU_API_TIMEOUT),
        'redirection' => 0,
        'sslverify'   => (bool) apply_filters('wp_smsn1_ru_sslverify', true),
        'headers'     => array('Accept' => 'application/json'),
        'user-agent'  => 'WP.SMSn1.RU/' . (defined('WP_SMSN1_RU_VERSION') ? WP_SMSN1_RU_VERSION : 'unknown')
            . '; ' . home_url('/'),
    );

    $response = wp_remote_get($url, $args);

    /*
     * Запасной вариант для устаревших хостингов, где не проходит проверка TLS.
     * По умолчанию выключен: HTTP отдаёт API-ключ и текст СМС открытым текстом.
     */
    if (is_wp_error($response) && defined('WP_SMSN1_RU_ALLOW_HTTP_FALLBACK') && WP_SMSN1_RU_ALLOW_HTTP_FALLBACK) {
        $response = wp_remote_get(str_replace('https://', 'http://', $url), $args);
    }

    if (is_wp_error($response)) {
        wp_smsn1_ru_remember_api_error($response->get_error_message());
        return new WP_Error('wp_smsn1_ru_http', $response->get_error_message());
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);

    if ($status < 200 || $status >= 300) {
        $message = sprintf('HTTP %d от %s', $status, WP_SMSN1_RU_API_HOST);
        wp_smsn1_ru_remember_api_error($message);
        return new WP_Error('wp_smsn1_ru_http_status', $message);
    }

    if ($raw) {
        return trim($body);
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        wp_smsn1_ru_remember_api_error('Некорректный ответ API smsn1.ru');
        return new WP_Error('wp_smsn1_ru_bad_json', 'Некорректный ответ API smsn1.ru');
    }

    return $data;
}

/**
 * Запоминает последнюю ошибку API, чтобы показать её администратору.
 */
function wp_smsn1_ru_remember_api_error($message)
{
    if (!function_exists('set_transient')) {
        return;
    }

    set_transient(
        'wp_smsn1_ru_last_api_error',
        array('message' => (string) $message, 'time' => time()),
        HOUR_IN_SECONDS
    );
}

/**
 * Метка времени сервиса.
 *
 * @return string|false
 */
function wp_smsn1_ru_get_timestamp()
{
    $timestamp = wp_smsn1_ru_api_request('timestamp', array(), true);

    if (is_wp_error($timestamp) || !ctype_digit((string) $timestamp)) {
        return false;
    }

    return (string) $timestamp;
}

/**
 * Приводит номер к формату, который ждёт API: 79991234567.
 */
function wp_smsn1_ru_api_phone($phone_number)
{
    $digits = preg_replace('/\D+/', '', (string) $phone_number);

    if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }

    return $digits;
}

/**
 * Общая отправка СМС.
 *
 * @return true|string true при успехе, иначе текст ошибки.
 */
function wp_smsn1_ru_send_sms($phone_number, $sms_text)
{
    $phone_number = wp_smsn1_ru_api_phone($phone_number);

    $login   = get_option('wp_smsn1_ru_login');
    $api_key = get_option('wp_smsn1_ru_api_key');
    $sender  = get_option('wp_smsn1_ru_sender');

    if (empty($sender) || $sender === 'Не выбран!') {
        return 'Не выбран отправитель SMS. Зайдите в настройки плагина WP.SMSn1.RU и выберите отправителя.';
    }

    if (empty($login) || empty($api_key)) {
        return 'Не заданы логин и API-ключ smsn1.ru. Проверьте настройки плагина.';
    }

    if ($phone_number === '') {
        return 'Некорректный номер телефона.';
    }

    $timestamp = wp_smsn1_ru_get_timestamp();

    if ($timestamp === false) {
        return 'Не удалось получить метку времени smsn1.ru (проверьте исходящие HTTPS-запросы на сервере).';
    }

    $params = array(
        'timestamp' => $timestamp,
        'login'     => $login,
        'phone'     => $phone_number,
        'text'      => $sms_text,
        'sender'    => $sender,
    );

    $params['signature'] = wp_smsn1_ru_Signature_md5($params, $api_key);

    $response = wp_smsn1_ru_api_request('send', $params);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    if (!empty($response['error'])) {
        return (string) $response['error'];
    }

    return true;
}

/**
 * Отправляет произвольный текст.
 *
 * @return true|string
 */
function wp_smsn1_ru_send_function($phone_number, $sms_text)
{
    return wp_smsn1_ru_send_sms($phone_number, $sms_text);
}

/**
 * Отправляет код подтверждения.
 *
 * @return true|string
 */
function wp_smsn1_ru_send_code_function($phone_number, $sms_code)
{
    $site = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $site = $site ? $site : 'site';

    $text = apply_filters(
        'wp_smsn1_ru_code_message',
        'Код ' . $sms_code . ', для сайта ' . $site,
        $sms_code
    );

    return wp_smsn1_ru_send_sms($phone_number, $text);
}

/**
 * Баланс аккаунта.
 *
 * @return float|string Число или WP_SMSN1_RU_BALANCE_ERROR.
 */
function wp_smsn1_ru_get_balance()
{
    $cached = get_transient('wp_smsn1_ru_get_balance_temp');

    if ($cached !== false) {
        return $cached;
    }

    $login   = get_option('wp_smsn1_ru_login');
    $api_key = get_option('wp_smsn1_ru_api_key');
    $value   = WP_SMSN1_RU_BALANCE_ERROR;
    $ttl     = 60;

    if (!empty($login) && !empty($api_key)) {
        $timestamp = wp_smsn1_ru_get_timestamp();

        if ($timestamp !== false) {
            $params = array(
                'timestamp' => $timestamp,
                'login'     => $login,
            );
            $params['signature'] = wp_smsn1_ru_Signature_md5($params, $api_key);

            $response = wp_smsn1_ru_api_request('balance', $params);

            if (!is_wp_error($response) && !isset($response['error']) && isset($response['money'])) {
                $value = (float) $response['money'];
                $ttl   = 2 * MINUTE_IN_SECONDS;
            }
        }
    }

    set_transient('wp_smsn1_ru_get_balance_temp', $value, $ttl);

    return $value;
}

/**
 * Одобренные имена отправителей.
 *
 * @return array
 */
function wp_smsn1_ru_get_senders()
{
    $cached = get_transient('wp_smsn1_ru_get_senders_temp');

    if (is_array($cached)) {
        return $cached;
    }

    $senders = array();
    $login   = get_option('wp_smsn1_ru_login');
    $api_key = get_option('wp_smsn1_ru_api_key');

    if (!empty($login) && !empty($api_key)) {
        $timestamp = wp_smsn1_ru_get_timestamp();

        if ($timestamp !== false) {
            $params = array(
                'timestamp' => $timestamp,
                'login'     => $login,
            );
            $params['signature'] = wp_smsn1_ru_Signature_md5($params, $api_key);

            $response = wp_smsn1_ru_api_request('senders', $params);

            if (!is_wp_error($response) && empty($response['error'])) {
                foreach ($response as $name => $status) {
                    if ($status === 'completed') {
                        $senders[$name] = $status;
                    }
                }
            }
        }
    }

    set_transient('wp_smsn1_ru_get_senders_temp', $senders, 4 * MINUTE_IN_SECONDS);

    return $senders;
}

/**
 * Регистрирует публичные хуки.
 */
function wp_smsn1_ru_register_public_functions()
{
    add_action('wp_smsn1_ru_send_function', 'wp_smsn1_ru_send_sms', 10, 2);
    add_action('wp_smsn1_ru_get_balance', 'wp_smsn1_ru_get_balance');
    add_action('wp_smsn1_ru_get_senders', 'wp_smsn1_ru_get_senders');
}
add_action('plugins_loaded', 'wp_smsn1_ru_register_public_functions');

/**
 * Обёртки публичного API.
 */
function wp_smsn1_ru_do_send_function($phone_number, $sms_text)
{
    return wp_smsn1_ru_send_sms($phone_number, $sms_text);
}

function wp_smsn1_ru_do_get_balance()
{
    return wp_smsn1_ru_get_balance();
}

function wp_smsn1_ru_do_get_senders()
{
    return wp_smsn1_ru_get_senders();
}
