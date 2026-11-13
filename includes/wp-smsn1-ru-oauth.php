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

    if (class_exists('WP_SMSN1_RU_Provider_Telegram')) {
        $providers['telegram'] = new WP_SMSN1_RU_Provider_Telegram();
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
    add_rewrite_rule('^wp-smsn1-ru-oauth/([a-z0-9_-]+)/link/([a-f0-9]+)/?$', 'index.php?wp_smsn1_ru_oauth_widget=$matches[1]&wp_smsn1_ru_link_token=$matches[2]', 'top');
    add_rewrite_rule('^wp-smsn1-ru-oauth/([a-z0-9_-]+)/?$', 'index.php?wp_smsn1_ru_oauth_widget=$matches[1]', 'top');
}
add_action('init', 'wp_smsn1_ru_oauth_add_rewrite', 5);

/**
 * Регистрирует query-переменную rewrite-правила.
 */
function wp_smsn1_ru_oauth_query_vars($vars)
{
    $vars[] = 'wp_smsn1_ru_oauth_callback';
    $vars[] = 'wp_smsn1_ru_oauth_widget';
    $vars[] = 'wp_smsn1_ru_link_token';

    return $vars;
}
add_filter('query_vars', 'wp_smsn1_ru_oauth_query_vars');

/**
 * Обработка callback по rewrite-адресу, а также защита от «голых» code+state
 * на любой странице (если правила перезаписи ещё не обновились).
 */
function wp_smsn1_ru_oauth_maybe_callback()
{
    // Callback виджетного провайдера (Telegram): /wp-smsn1-ru-oauth/telegram/
    $widget_provider = get_query_var('wp_smsn1_ru_oauth_widget');

    if (!empty($widget_provider)) {
        $link_token = get_query_var('wp_smsn1_ru_link_token');

        wp_smsn1_ru_oauth_widget_callback(
            sanitize_key($widget_provider),
            $link_token ? sanitize_key($link_token) : ''
        );
        return;
    }

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

    if ($provider instanceof WP_SMSN1_RU_Auth_Provider_Widget) {
        wp_die(esc_html('Для этого сервиса вход выполняется через кнопку на форме.'), esc_html('Вход'), array('response' => 400));
    }

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

    if (!empty($profile['phone'])) {
        update_user_meta($user_id, '_wp_smsn1_ru_vk_phone', sanitize_text_field($profile['phone']));
    }

        $back = !empty($data['redirect_to']) ? $data['redirect_to'] : admin_url('profile.php');

        wp_safe_redirect(add_query_arg('wp_smsn1_ru_linked', $provider->get_id(), $back));
        exit;
    }

    $user_id = wp_smsn1_ru_oauth_find_user($provider->get_id(), $profile['uid']);

    if (!$user_id) {
        // Автомерж по e-mail запрещён: предлагаем войти в аккаунт и привязать вручную
        if (!empty($profile['email']) && email_exists($profile['email'])) {
            wp_safe_redirect(add_query_arg('wp_smsn1_ru_notice', 'email_exists', wp_smsn1_ru_oauth_login_url()));
            exit;
        }

        // Телефон, подтверждённый VK, тоже может принадлежать существующему аккаунту
        if (!empty($profile['phone']) && function_exists('wp_smsn1_ru_get_user_by_phone')) {
            $vk_phone = sanitize_my_phone_for_field($profile['phone']);

            if ($vk_phone !== 'not_russian_num' && wp_smsn1_ru_get_user_by_phone($vk_phone)) {
                wp_safe_redirect(add_query_arg('wp_smsn1_ru_notice', 'phone_exists', wp_smsn1_ru_oauth_login_url()));
                exit;
            }
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

        // Виджетные провайдеры (Telegram) рендерят свою кнопку через JS провайдера
        if ($provider instanceof WP_SMSN1_RU_Auth_Provider_Widget) {
            $buttons[] = $provider->get_button_html(home_url('/wp-smsn1-ru-oauth/' . $id . '/'));
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

    return '<div class="wp-smsn1-ru-oauth-block">'
        . '<p class="wp-smsn1-ru-oauth-title">Войти другим способом</p>'
        . implode('', $buttons)
        . '</div>';
}

/**
 * Адрес формы входа плагина: именно туда ведём с сообщением о найденном аккаунте.
 *
 * @return string
 */
function wp_smsn1_ru_oauth_login_url()
{
    $slug = (string) get_option('wp_smsn1_ru_use_custom_login_form_url', 'login-by-wp-smsn1-ru');

    if ($slug !== '' && get_page_by_path($slug)) {
        return home_url('/' . trim($slug, '/') . '/');
    }

    return wp_login_url();
}

/**
 * Текст уведомления по коду.
 *
 * @param string $code Код из query-параметра.
 * @return string
 */
function wp_smsn1_ru_oauth_notice_text($code)
{
    $messages = array(
        'email_exists' => 'Аккаунт с таким e-mail уже существует. Войдите в него, а затем привяжите VK ID в профиле — мы не объединяем аккаунты автоматически.',
        'phone_exists' => 'Аккаунт с таким номером телефона уже существует. Войдите в него, а затем привяжите VK ID в профиле.',
    );

    return isset($messages[$code]) ? $messages[$code] : '';
}

/**
 * HTML-блок уведомления для формы входа.
 *
 * @return string
 */
function wp_smsn1_ru_oauth_notice_html()
{
    if (empty($_GET['wp_smsn1_ru_notice'])) {
        return '';
    }

    $text = wp_smsn1_ru_oauth_notice_text(sanitize_key(wp_unslash($_GET['wp_smsn1_ru_notice'])));

    if ($text === '') {
        return '';
    }

    return '<div class="wp-smsn1-ru-notice wp-smsn1-ru-notice--warning" role="status">' . esc_html($text) . '</div>';
}

/**
 * Сообщение на wp-login.php, если e-mail уже занят.
 */
add_filter('login_message', 'wp_smsn1_ru_oauth_login_message');
function wp_smsn1_ru_oauth_login_message($message)
{
    if (!empty($_GET['wp_smsn1_ru_notice'])) {
        $text = wp_smsn1_ru_oauth_notice_text(sanitize_key(wp_unslash($_GET['wp_smsn1_ru_notice'])));

        if ($text !== '') {
            $message .= '<p class="message">' . esc_html($text) . '</p>';
        }
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
    echo wp_smsn1_ru_oauth_profile_section_html($user->ID, admin_url('profile.php'));
}

/**
 * HTML блока «Способы входа» с привязкой провайдеров.
 *
 * @param int    $user_id     Пользователь.
 * @param string $redirect_to Куда вернуть после привязки.
 * @return string
 */
function wp_smsn1_ru_oauth_profile_section_html($user_id, $redirect_to = '')
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return '';
    }

    $redirect_to = $redirect_to !== '' ? $redirect_to : admin_url('profile.php');
    $rows        = array();

    foreach (wp_smsn1_ru_oauth_providers() as $id => $provider) {
        if (!$provider->is_configured()) {
            continue;
        }

        $linked = get_user_meta($user_id, wp_smsn1_ru_oauth_meta_key($id), true);

        // Виджетные провайдеры (Telegram): привязка через одноразовый токен в auth-url
        if ($provider instanceof WP_SMSN1_RU_Auth_Provider_Widget) {
            if ($linked) {
                $rows[] = sprintf(
                    '<li class="wp-smsn1-ru-linked__item"><span class="wp-smsn1-ru-linked__name">%1$s</span>'
                    . '<span class="wp-smsn1-ru-linked__state wp-smsn1-ru-linked__state--ok">привязан</span></li>',
                    esc_html($provider->get_label())
                );
            } else {
                $token = wp_smsn1_ru_oauth_create_link_token($user_id, $id, $redirect_to);
                $url   = home_url('/wp-smsn1-ru-oauth/' . $id . '/link/' . $token . '/');

                $rows[] = sprintf(
                    '<li class="wp-smsn1-ru-linked__item wp-smsn1-ru-linked__item--widget">'
                    . '<span class="wp-smsn1-ru-linked__name">%1$s</span>%2$s</li>',
                    esc_html($provider->get_label()),
                    $provider->get_button_html($url)
                );
            }

            continue;
        }

        if ($linked) {
            $rows[] = sprintf(
                '<li class="wp-smsn1-ru-linked__item"><span class="wp-smsn1-ru-linked__name">%1$s</span>'
                . '<span class="wp-smsn1-ru-linked__state wp-smsn1-ru-linked__state--ok">привязан</span></li>',
                esc_html($provider->get_label())
            );
            continue;
        }

        $url = add_query_arg(
            array(
                'wp_smsn1_ru_oauth' => $id,
                'link'              => '1',
                'redirect_to'       => $redirect_to,
            ),
            home_url('/')
        );

        $rows[] = sprintf(
            '<li class="wp-smsn1-ru-linked__item"><span class="wp-smsn1-ru-linked__name">%1$s</span>'
            . '<a class="wp-smsn1-ru-button wp-smsn1-ru-button--secondary" href="%2$s">Привязать</a></li>',
            esc_html($provider->get_label()),
            esc_url($url)
        );
    }

    if (empty($rows)) {
        return '';
    }

    $notice = '';

    if (!empty($_GET['wp_smsn1_ru_linked'])) {
        $notice = '<div class="wp-smsn1-ru-notice wp-smsn1-ru-notice--success" role="status">'
            . 'Сервис привязан к аккаунту — теперь можно входить через него.'
            . '</div>';
    }

    return $notice . '<div class="wp-smsn1-ru-linked">'
        . '<h2 class="wp-smsn1-ru-linked__title">Способы входа</h2>'
        . '<p class="wp-smsn1-ru-linked__intro">Привяжите сервис — и сможете входить в этот аккаунт без СМС.</p>'
        . '<ul class="wp-smsn1-ru-linked__list">' . implode('', $rows) . '</ul>'
        . '</div>';
}

/**
 * Одноразовый токен привязки для виджетного провайдера.
 *
 * @param int    $user_id     Пользователь.
 * @param string $provider_id Провайдер.
 * @param string $redirect_to Куда вернуть после привязки.
 * @return string
 */
function wp_smsn1_ru_oauth_create_link_token($user_id, $provider_id, $redirect_to = '')
{
    $token = wp_smsn1_ru_random_hex(16);

    set_transient(
        'wp_smsn1_ru_link_' . $token,
        array(
            'user_id'     => (int) $user_id,
            'provider'    => (string) $provider_id,
            'redirect_to' => $redirect_to,
        ),
        10 * MINUTE_IN_SECONDS
    );

    return $token;
}

/**
 * Тот же блок в личном кабинете WooCommerce (фронтенд).
 */
add_action('woocommerce_account_dashboard', 'wp_smsn1_ru_oauth_woo_account_section');
add_action('woocommerce_edit_account_form', 'wp_smsn1_ru_oauth_woo_account_section');
function wp_smsn1_ru_oauth_woo_account_section()
{
    if (!is_user_logged_in()) {
        return;
    }

    $redirect = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/');

    echo wp_smsn1_ru_oauth_profile_section_html(get_current_user_id(), $redirect);
}

/**
 * Шорткод для вывода блока в любом месте: [wp_smsn1_ru_login_methods]
 */
add_shortcode('wp_smsn1_ru_login_methods', 'wp_smsn1_ru_oauth_login_methods_shortcode');
function wp_smsn1_ru_oauth_login_methods_shortcode()
{
    if (!is_user_logged_in()) {
        return '';
    }

    $url = get_permalink();

    return wp_smsn1_ru_oauth_profile_section_html(get_current_user_id(), $url ? $url : home_url('/'));
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


/**
 * Callback виджетного провайдера: провайдер сам присылает подписанные данные.
 *
 * @param string $provider_id Идентификатор провайдера.
 */
function wp_smsn1_ru_oauth_widget_callback($provider_id, $link_token = '')
{
    $provider = wp_smsn1_ru_oauth_get_provider($provider_id);

    if (!$provider || !($provider instanceof WP_SMSN1_RU_Auth_Provider_Widget) || !$provider->is_configured()) {
        wp_die(esc_html('Вход через этот сервис не настроен.'), esc_html('Вход недоступен'), array('response' => 400));
    }

    $profile = $provider->verify_callback($_GET);

    if (is_wp_error($profile)) {
        wp_die(
            esc_html('Не удалось выполнить вход: ' . $profile->get_error_message()),
            esc_html('Вход не выполнен'),
            array('response' => 400)
        );
    }

    // Привязка к текущему аккаунту по одноразовому токену из личного кабинета
    if ($link_token !== '') {
        $link = get_transient('wp_smsn1_ru_link_' . $link_token);
        delete_transient('wp_smsn1_ru_link_' . $link_token);

        if (!is_array($link) || (int) $link['user_id'] !== get_current_user_id() || $link['provider'] !== $provider->get_id()) {
            wp_die(
                esc_html('Ссылка привязки истекла или открыта не в том аккаунте. Войдите и попробуйте снова.'),
                esc_html('Не удалось привязать'),
                array('response' => 400)
            );
        }

        update_user_meta((int) $link['user_id'], wp_smsn1_ru_oauth_meta_key($provider->get_id()), $profile['uid']);

        $back = !empty($link['redirect_to']) ? $link['redirect_to'] : home_url('/');

        wp_safe_redirect(add_query_arg('wp_smsn1_ru_linked', $provider->get_id(), $back));
        exit;
    }

    $user_id = wp_smsn1_ru_oauth_find_user($provider->get_id(), $profile['uid']);
    $created = false;

    if (!$user_id) {
        $user_id = wp_smsn1_ru_oauth_create_user($provider, $profile);

        if (is_wp_error($user_id)) {
            wp_die(esc_html('Не удалось создать аккаунт: ' . $user_id->get_error_message()), esc_html('Вход не выполнен'), array('response' => 500));
        }

        $created = true;
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        wp_die(esc_html('Аккаунт не найден.'), esc_html('Вход не выполнен'), array('response' => 500));
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    do_action('wp_login', $user->user_login, $user);

    // Telegram не отдаёт e-mail — предлагаем добавить его в профиле
    if ($created && $user->user_email === '') {
        wp_safe_redirect(add_query_arg('wp_smsn1_ru_add_email', $provider->get_id(), admin_url('profile.php')));
        exit;
    }

    wp_safe_redirect(home_url('/'));
    exit;
}

/**
 * Подсказка «добавьте e-mail» для аккаунтов из Telegram.
 */
add_action('admin_notices', 'wp_smsn1_ru_oauth_add_email_notice');
function wp_smsn1_ru_oauth_add_email_notice()
{
    if (!empty($_GET['wp_smsn1_ru_add_email'])) {
        echo '<div class="notice notice-warning"><p>Аккаунт создан через '
            . esc_html(sanitize_text_field(wp_unslash($_GET['wp_smsn1_ru_add_email'])))
            . ', у него нет e-mail. Укажите адрес ниже — он нужен для восстановления доступа.</p></div>';
    }
}
