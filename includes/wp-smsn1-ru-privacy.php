<?php
/**
 * Интеграция с инструментами приватности WordPress (экспорт и удаление данных).
 *
 * Плагин хранит номер телефона и идентификаторы внешних сервисов — по запросу
 * пользователя их можно выгрузить или удалить.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Список мета-полей плагина.
 *
 * @return array
 */
function wp_smsn1_ru_privacy_meta_keys()
{
    return array(
        'billing_phone'                => 'Телефон',
        '_wp_smsn1_ru_vk_id'           => 'VK ID',
        '_wp_smsn1_ru_vk_phone'        => 'Телефон из VK',
        '_wp_smsn1_ru_telegram_id'     => 'Telegram ID',
        'wp_smsn1_ru_password_set'     => 'Пароль задан (0/1)',
    );
}

/**
 * Экспорт данных пользователя.
 *
 * @param string $email Адрес.
 * @param int    $page  Страница.
 * @return array
 */
function wp_smsn1_ru_privacy_exporter($email, $page = 1)
{
    $user = get_user_by('email', $email);

    if (!$user) {
        return array(
            'data' => array(),
            'done' => true,
        );
    }

    $items = array();

    foreach (wp_smsn1_ru_privacy_meta_keys() as $key => $label) {
        $value = get_user_meta($user->ID, $key, true);

        if ($value === '' || $value === false) {
            continue;
        }

        $items[] = array(
            'name'  => $label,
            'value' => is_scalar($value) ? (string) $value : '',
        );
    }

    return array(
        'data' => array(
            array(
                'group_id'    => 'wp-smsn1-ru',
                'group_label' => 'Вход по номеру телефона',
                'item_id'     => 'wp-smsn1-ru-' . $user->ID,
                'data'        => $items,
            ),
        ),
        'done' => true,
    );
}

/**
 * Удаление данных пользователя.
 *
 * @param string $email Адрес.
 * @param int    $page  Страница.
 * @return array
 */
function wp_smsn1_ru_privacy_eraser($email, $page = 1)
{
    $user = get_user_by('email', $email);

    if (!$user) {
        return array(
            'items_removed'  => false,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => true,
        );
    }

    $removed = 0;

    foreach (array_keys(wp_smsn1_ru_privacy_meta_keys()) as $key) {
        if (delete_user_meta($user->ID, $key)) {
            $removed++;
        }
    }

    // Незавершённые коды и токены привязки, привязанные к номеру, удаляются по TTL.

    return array(
        'items_removed'  => $removed > 0,
        'items_retained' => false,
        'messages'       => array(),
        'done'           => true,
    );
}

add_filter('wp_privacy_personal_data_exporters', 'wp_smsn1_ru_register_exporter');
/**
 * Регистрирует экспортёр.
 *
 * @param array $exporters Экспортёры.
 * @return array
 */
function wp_smsn1_ru_register_exporter($exporters)
{
    $exporters['wp-smsn1-ru'] = array(
        'exporter_friendly_name' => 'WP.SMSn1.RU',
        'callback'               => 'wp_smsn1_ru_privacy_exporter',
    );

    return $exporters;
}

add_filter('wp_privacy_personal_data_erasers', 'wp_smsn1_ru_register_eraser');
/**
 * Регистрирует эрейзер.
 *
 * @param array $erasers Эрейзеры.
 * @return array
 */
function wp_smsn1_ru_register_eraser($erasers)
{
    $erasers['wp-smsn1-ru'] = array(
        'eraser_friendly_name' => 'WP.SMSn1.RU',
        'callback'             => 'wp_smsn1_ru_privacy_eraser',
    );

    return $erasers;
}
