<?php
/**
 * Вход через внешние провайдеры (VK ID, позже Telegram и MAX).
 *
 * Поток: start (init) → редирект на провайдера → callback (admin-post) → обмен кода
 * на токен → профиль → вход/создание/привязка аккаунта.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Зарегистрированные провайдеры.
 *
 * @return WP_SMSN1_RU_Auth_Provider[]
 */
function wp_smsn1_ru_oauth_providers()
{
    static $providers = null;

    if ($providers !== null) {
        return $providers;
    }

    $providers = array();

    if (class_exists('WP_SMSN1_RU_Provider_VK')) {
        $providers['vk'] = new WP_SMSN1_RU_Provider_VK();
    }

    /**
     * Позволяет добавить провайдера (например, Telegram).
     *
     * @param WP_SMSN1_RU_Auth_Provider[] $providers Провайдеры по id.
     */
    $providers = apply_filters('wp_smsn1_ru_auth_providers', $providers);

    return $providers;
}

/**
 * @param string $id Идентификатор провайдера.
 * @return WP_SMSN1_RU_Auth_Provider|null
 */
function wp_smsn1_ru_oauth_get_provider($id)
{
    $providers = wp_smsn1_ru_oauth_providers();

    return isset($providers[$id]) ? $providers[$id] : null;
}

/**
 * Точный redirect_uri — его же нужно указать в настройках приложения провайдера.
 *
 * VK ID (как и многие провайдеры) не разрешает query-параметры в redirect URL,
 * поэтому используется чистый путь /wp-smsn1-ru-oauth/ через rewrite-правило.
 *
 * @return string
 */
function wp_smsn1_ru_oauth_redirect_uri()
{
    return home_url('/wp-smsn1-ru-oauth/');
}

/**
 * Rewrite-правило для чистого redirect URL без query-параметров.
 */
function wp_smsn1_ru_oauth_add_rewrite()
{
    add_rewrite_rule('^wp-smsn1-ru-oauth/?$', 'index.php?wp_smsn1_ru_oauth_callback=1', 'top');
}
add_action('init', 'wp_smsn1_ru_oauth_add_rewrite', 5);

/**
 * Регистрирует query-переменную rewrite-правила.
 */
function wp_smsn1_ru_oauth_query_vars($vars)
{
    $vars[] = 'wp_smsn1_ru_oauth_callback';

    return $vars;
}
add_filter('query_vars', 'wp_smsn1_ru_oauth_query_vars');

/**
 * Обработка callback по rewrite-адресу, а также защита от «голых» code+state
 * на любой странице (если правила перезаписи ещё не обновились).
 */
function wp_smsn1_ru_oauth_maybe_callback()
{
    $is_callback = (bool) get_query_var('wp_smsn1_ru_oauth_callback');

    // Запасной вариант: code+state пришли на любую страницу, а state нам известен.
    if (!$is_callback && !empty($_GET['code']) && !empty($_GET['state'])) {
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        $is_callback = (bool) get_transient('wp_smsn1_ru_oauth_' . $state);
    }

    if ($is_callback) {
        wp_smsn1_ru_oauth_callback();
    }
}
add_action('template_redirect', 'wp_smsn1_ru_oauth_maybe_callback', 1);

/**
 * Сбрасывает правила перезаписи после обновления плагина.
 */
function wp_smsn1_ru_oauth_maybe_flush_rewrites()
{
    if (get_option('wp_smsn1_ru_rewrite_version') !== WP_SMSN1_RU_VERSION) {
        flush_rewrite_rules(false);
        update_option('wp_smsn1_ru_rewrite_version', WP_SMSN1_RU_VERSION);
    }
}
add_action('init', 'wp_smsn1_ru_oauth_maybe_flush_rewrites', 99);

/**
 * Старт входа: генерирует state и PKCE, уводит на провайдера.
 */
function wp_smsn1_ru_oauth_start()
{
    if (empty($_GET['wp_smsn1_ru_oauth'])) {
        return;
    }

    $provider_id = sanitize_key(wp_unslash($_GET['wp_smsn1_ru_oauth']));
    $provider    = wp_smsn1_ru_oauth_get_provider($provider_id);

    if (!$provider || !$provider->is_configured()) {
        wp_die(
            esc_html('Вход через этот сервис не настроен. Обратитесь к администратору сайта.'),
            esc_html('Вход недоступен'),
            array('response' => 400)
        );
    }

    $link = !empty($_GET['link']) && is_user_logged_in();

    // VK (и другие провайдеры) требуют state не короче 32 символов
    $state    = wp_smsn1_ru_random_hex(16);
    $verifier = wp_smsn1_ru_pkce_verifier();

    $redirect_to = isset($_GET['redirect_to'])
        ? wp_validate_redirect(wp_unslash($_GET['redirect_to']), home_url('/'))
        : home_url('/');

    set_transient(
        'wp_smsn1_ru_oauth_' . $state,
        array(
            'provider'    => $provider_id,
            'verifier'    => $verifier,
            'link'        => $link ? get_current_user_id() : 0,
            'redirect_to' => $redirect_to,
        ),
        10 * MINUTE_IN_SECONDS
    );

    $url = $provider->get_authorize_url($state, $verifier, wp_smsn1_ru_oauth_redirect_uri());

    wp_redirect($url);
    exit;
}
add_action('init', 'wp_smsn1_ru_oauth_start', 20);

/**
 * Callback провайдера.
 */
function wp_smsn1_ru_oauth_callback()
{
    if (isset($_GET['error'])) {
        $error = sanitize_text_field(wp_unslash($_GET['error']));
        wp_die(
            esc_html('Провайдер вернул ошибку входа: ' . $error),
            esc_html('Вход не выполнен'),
            array('response' => 400)
        );
    }

    $state     = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    $code      = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
    // device_id возвращает сам провайдер вместе с кодом подтверждения
    $device_id = isset($_GET['device_id']) ? sanitize_text_field(wp_unslash($_GET['device_id'])) : '';
    $key   = 'wp_smsn1_ru_oauth_' . $state;
    $data  = get_transient($key);

    // state одноразовый
    delete_transient($key);

    if (!$data || !is_array($data) || $code === '') {
        wp_die(
            esc_html('Сессия входа истекла. Попробуйте войти заново.'),
            esc_html('Вход не выполнен'),
            array('response' => 400)
        );
    }

    $provider = wp_smsn1_ru_oauth_get_provider($data['provider']);

    if (!$provider || !$provider->is_configured()) {
        wp_die(esc_html('Провайдер входа недоступен.'), esc_html('Вход не выполнен'), array('response' => 400));
    }

    $token = $provider->exchange_code($code, $data['verifier'], wp_smsn1_ru_oauth_redirect_uri(), $device_id, $state);

    if (is_wp_error($token)) {
        wp_die(
            esc_html('Не удалось получить токен: ' . $token->get_error_message()),
            esc_html('Вход не выполнен'),
            array('response' => 502)
        );
    }

    $profile = $provider->get_profile($token);

    if (is_wp_error($profile)) {
        wp_die(
            esc_html('Не удалось получить профиль: ' . $profile->get_error_message()),
            esc_html('Вход не выполнен'),
            array('response' => 502)
        );
    }

    // Привязка к текущему аккаунту
    if (!empty($data['link'])) {
        $user_id = (int) $data['link'];

        if (get_current_user_id() !== $user_id) {
            wp_die(esc_html('Сессия истекла, войдите и попробуйте снова.'), esc_html('Ошибка'), array('response' => 400));
        }

        update_user_meta($user_id, wp_smsn1_ru_oauth_meta_key($provider->get_id()), $profile['uid']);

        wp_safe_redirect(add_query_arg('wp_smsn1_ru_linked', $provider->get_id(), admin_url('profile.php')));
        exit;
    }

    $user_id = wp_smsn1_ru_oauth_find_user($provider->get_id(), $profile['uid']);

    if (!$user_id) {
        // Автомерж по e-mail запрещён: предлагаем войти в аккаунт и привязать вручную
        if (!empty($profile['email']) && email_exists($profile['email'])) {
            wp_safe_redirect(add_query_arg('wp_smsn1_ru_notice', 'email_exists', wp_login_url()));
            exit;
        }

        $user_id = wp_smsn1_ru_oauth_create_user($provider, $profile);

        if (is_wp_error($user_id)) {
            wp_die(
                esc_html('Не удалось создать аккаунт: ' . $user_id->get_error_message()),
                esc_html('Вход не выполнен'),
                array('response' => 500)
            );
        }
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        wp_die(esc_html('Аккаунт не найден.'), esc_html('Вход не выполнен'), array('response' => 500));
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    do_action('wp_login', $user->user_login, $user);

    wp_safe_redirect($data['redirect_to']);
    exit;
}
add_action('admin_post_nopriv_wp_smsn1_ru_oauth_callback', 'wp_smsn1_ru_oauth_callback');
add_action('admin_post_wp_smsn1_ru_oauth_callback', 'wp_smsn1_ru_oauth_callback');

/**
 * Мета-ключ провайдера.
 *
 * @param string $provider_id Провайдер.
 * @return string
 */
function wp_smsn1_ru_oauth_meta_key($provider_id)
{
    return '_wp_smsn1_ru_' . sanitize_key($provider_id) . '_id';
}

/**
 * Поиск пользователя по идентификатору у провайдера.
 *
 * @param string $provider_id Провайдер.
 * @param string $uid         Идентификатор у провайдера.
 * @return int
 */
function wp_smsn1_ru_oauth_find_user($provider_id, $uid)
{
    $users = get_users(
        array(
            'meta_key'   => wp_smsn1_ru_oauth_meta_key($provider_id),
            'meta_value' => (string) $uid,
            'number'     => 1,
            'fields'     => 'ID',
        )
    );

    return !empty($users) ? (int) $users[0] : 0;
}

/**
 * Создание аккаунта по профилю провайдера.
 *
 * @param WP_SMSN1_RU_Auth_Provider $provider Провайдер.
 * @param array                     $profile  Профиль.
 * @return int|WP_Error
 */
function wp_smsn1_ru_oauth_create_user($provider, $profile)
{
    $email = '';

    if (!empty($profile['email']) && is_email($profile['email']) && !email_exists($profile['email'])) {
        $email = $profile['email'];
    }

    $username = $provider->get_id() . '_' . preg_replace('/[^0-9A-Za-z_]/', '', (string) $profile['uid']);

    if (username_exists($username)) {
        $username .= '_' . wp_rand(1000, 9999);
    }

    $user_id = wp_create_user($username, wp_generate_password(24, true, true), $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    update_user_meta($user_id, wp_smsn1_ru_oauth_meta_key($provider->get_id()), $profile['uid']);

    if (!empty($profile['name'])) {
        wp_update_user(
            array(
                'ID'           => $user_id,
                'display_name' => sanitize_text_field($profile['name']),
            )
        );
    }

    return (int) $user_id;
}

/**
 * Кнопки входа для форм.
 *
 * @return string
 */
function wp_smsn1_ru_oauth_buttons_html()
{
    $buttons = array();

    foreach (wp_smsn1_ru_oauth_providers() as $id => $provider) {
        if (!$provider->is_configured()) {
            continue;
        }

        $url = add_query_arg('wp_smsn1_ru_oauth', $id, home_url('/'));

        $buttons[] = sprintf(
            '<a class="wp-smsn1-ru-oauth-button wp-smsn1-ru-oauth-%1$s" href="%2$s" rel="nofollow">Войти через %3$s</a>',
            esc_attr($id),
            esc_url($url),
            esc_html($provider->get_label())
        );
    }

    if (empty($buttons)) {
        return '';
    }

    return '<div class="wp-smsn1-ru-oauth">' . implode('', $buttons) . '</div>';
}

/**
 * Сообщение на wp-login.php, если e-mail уже занят.
 */
add_filter('login_message', 'wp_smsn1_ru_oauth_login_message');
function wp_smsn1_ru_oauth_login_message($message)
{
    if (isset($_GET['wp_smsn1_ru_notice']) && 'email_exists' === $_GET['wp_smsn1_ru_notice']) {
        $message .= '<p class="message">Аккаунт с таким e-mail уже существует. Войдите в него, '
            . 'а затем привяжите VK ID в профиле — мы не объединяем аккаунты автоматически.</p>';
    }

    return $message;
}

/**
 * Блок «Способы входа» в профиле: привязка и отвязка провайдера.
 */
add_action('show_user_profile', 'wp_smsn1_ru_oauth_profile_section');
add_action('edit_user_profile', 'wp_smsn1_ru_oauth_profile_section');
function wp_smsn1_ru_oauth_profile_section($user)
{
    $rows = array();

    foreach (wp_smsn1_ru_oauth_providers() as $id => $provider) {
        if (!$provider->is_configured()) {
            continue;
        }

        $linked = get_user_meta($user->ID, wp_smsn1_ru_oauth_meta_key($id), true);

        if ($linked) {
            $rows[] = sprintf(
                '<li><strong>%1$s:</strong> привязан (ID %2$s)</li>',
                esc_html($provider->get_label()),
                esc_html($linked)
            );
        } else {
            $url = add_query_arg(
                array(
                    'wp_smsn1_ru_oauth' => $id,
                    'link'              => '1',
                    'redirect_to'       => admin_url('profile.php'),
                ),
                home_url('/')
            );

            $rows[] = sprintf(
                '<li><strong>%1$s:</strong> не привязан. <a class="button" href="%2$s">Привязать</a></li>',
                esc_html($provider->get_label()),
                esc_url($url)
            );
        }
    }

    if (empty($rows)) {
        return;
    }

    echo '<h2>Способы входа</h2>';
    echo '<table class="form-table"><tr><th>Внешние сервисы</th><td><ul style="margin:0;">';
    echo implode('', $rows);
    echo '</ul><p class="description">Привязанный сервис позволяет входить в этот аккаунт без СМС.</p></td></tr></table>';
}

/**
 * Уведомление об успешной привязке.
 */
add_action('admin_notices', 'wp_smsn1_ru_oauth_linked_notice');
function wp_smsn1_ru_oauth_linked_notice()
{
    if (!empty($_GET['wp_smsn1_ru_linked'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Сервис привязан к аккаунту.</p></div>';
    }
}
