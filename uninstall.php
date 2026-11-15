<?php
/**
 * Удаление данных плагина при деинсталляции.
 *
 * Удаляются настройки, транзиенты и служебные мета-поля плагина.
 * Телефон в billing_phone не удаляется: это данные WooCommerce и заказов.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wp_smsn1_ru_options = array(
    'wp_smsn1_ru_login',
    'wp_smsn1_ru_api_key',
    'wp_smsn1_ru_sender',
    'wp_smsn1_ru_session_expiration_hours',
    'wp_smsn1_ru_email_not_need_mode',
    'wp_smsn1_ru_verphone_on_checkout_mode',
    'wp_smsn1_ru_auth_mode',
    'wp_smsn1_ru_use_custom_login_form_url',
    'wp_smsn1_ru_use_custom_register_form_url',
    'wp_smsn1_ru_use_custom_login_redirect_url',
    'wp_smsn1_ru_logout_redirect_url',
    'wp_smsn1_ru_use_custom_login_form_redirect',
    'wp_smsn1_ru_use_custom_login_form_redirect_url',
    'wp_smsn1_ru_use_custom_login_form_my_acc_redirect',
    'wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url',
    'wp_smsn1_ru_use_custom_redirect_non_logged_in_users',
    'wp_smsn1_ru_vk_enabled',
    'wp_smsn1_ru_vk_client_id',
    'wp_smsn1_ru_vk_client_secret',
    'wp_smsn1_ru_vk_scope',
    'wp_smsn1_ru_telegram_enabled',
    'wp_smsn1_ru_telegram_bot',
    'wp_smsn1_ru_telegram_token',
    'wp_smsn1_ru_rewrite_version',
);

foreach ($wp_smsn1_ru_options as $wp_smsn1_ru_option) {
    delete_option($wp_smsn1_ru_option);
}

// Транзиенты плагина: коды, state OAuth, токены привязки, счётчики лимитов, кэш баланса.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wp_smsn1_ru_%'
        OR option_name LIKE '_transient_timeout_wp_smsn1_ru_%'"
);

// Служебные мета-поля пользователей (номер в billing_phone не трогаем).
$wp_smsn1_ru_meta = array(
    '_wp_smsn1_ru_vk_id',
    '_wp_smsn1_ru_telegram_id',
    '_wp_smsn1_ru_vk_phone',
    'wp_smsn1_ru_password_set',
);

foreach ($wp_smsn1_ru_meta as $wp_smsn1_ru_key) {
    delete_metadata('user', 0, $wp_smsn1_ru_key, '', true);
}
