<?php
/**
 * Провайдер входа Telegram (Login Widget).
 *
 * Документация: https://core.telegram.org/widgets/login
 *
 * Подпись: secret = SHA256(bot_token), hash = HMAC-SHA256(data_check_string, secret),
 * где data_check_string — все поля кроме hash, отсортированные по имени, «key=value» через \n.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SMSN1_RU_Provider_Telegram implements WP_SMSN1_RU_Auth_Provider_Widget
{
    const WIDGET_URL = 'https://telegram.org/js/telegram-widget.js?22';

    public function get_id()
    {
        return 'telegram';
    }

    public function get_label()
    {
        return 'Telegram';
    }

    public function is_configured()
    {
        return get_option('wp_smsn1_ru_telegram_enabled') === 'yes'
            && '' !== (string) get_option('wp_smsn1_ru_telegram_bot')
            && '' !== (string) get_option('wp_smsn1_ru_telegram_token');
    }

    public function get_button_html($callback_url)
    {
        return sprintf(
            '<div class="wp-smsn1-ru-oauth wp-smsn1-ru-oauth-telegram">'
            . '<script async src="%1$s" data-telegram-login="%2$s" data-size="large" '
            . 'data-auth-url="%3$s" data-request-access="write"></script></div>',
            esc_url(self::WIDGET_URL),
            esc_attr((string) get_option('wp_smsn1_ru_telegram_bot')),
            esc_url($callback_url)
        );
    }

    public function verify_callback($request)
    {
        $hash = isset($request['hash']) ? (string) $request['hash'] : '';

        if ($hash === '' || empty($request['id'])) {
            return new WP_Error('wp_smsn1_ru_telegram_signature', 'Telegram не передал подпись или идентификатор.');
        }

        $token = (string) get_option('wp_smsn1_ru_telegram_token');

        if ($token === '') {
            return new WP_Error('wp_smsn1_ru_telegram_token', 'В настройках не задан токен бота.');
        }

        $check = array();

        foreach ($request as $key => $value) {
            if ($key === 'hash' || is_array($value)) {
                continue;
            }

            $check[$key] = (string) $value;
        }

        ksort($check);

        $lines = array();

        foreach ($check as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $secret   = hash('sha256', $token, true);
        $expected = hash_hmac('sha256', implode("\n", $lines), $secret);

        if (!hash_equals($expected, $hash)) {
            return new WP_Error('wp_smsn1_ru_telegram_signature', 'Подпись Telegram не совпала.');
        }

        $max_age = max(60, (int) apply_filters('wp_smsn1_ru_telegram_max_age', 900));

        if (empty($request['auth_date']) || (time() - (int) $request['auth_date']) > $max_age) {
            return new WP_Error('wp_smsn1_ru_telegram_date', 'Ссылка входа устарела. Нажмите «Войти через Telegram» ещё раз.');
        }

        $first = isset($request['first_name']) ? (string) $request['first_name'] : '';
        $last  = isset($request['last_name']) ? (string) $request['last_name'] : '';

        return array(
            'uid'   => (string) $request['id'],
            'email' => '',
            'phone' => '',
            'name'  => trim($first . ' ' . $last),
        );
    }
}
