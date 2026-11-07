<?php
/*
 * Plugin Name: WP.SMSn1.RU(OTP&Verification by SMS): Одноразовый пароль и верификация по СМС
 * Description: Интеграция СМС уведомлений, верификация, регистрация и авторизация по номеру телефона с использованием одноразового кода из СМС. СМС уведомления в том числе для WooCommerce. Пока только для сотовых операторов Российской Федерации.
 * Plugin URI:  https://wp.smsn1.ru/
 * Author URI:  https://docs-group.ru/
 * Support URI: https://wp.smsn1.ru/контакты-службы-поддержки/
 * Version: 1.3.0
 * Author: Vasilev Mikhail Y.
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа к файлу
}

if (!defined('WP_SMSN1_RU_VERSION')) {
    define('WP_SMSN1_RU_VERSION', '1.3.0');
}

// Подключение необходимых файлов
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-functions.php'; //Функции необходимые для работы с wp.smsn1.ru(если быть точнее direct.docs-group.ru)
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-otp.php'; // OTP: генерация, лимит попыток, защита от SMS-бомбинга
require_once plugin_dir_path(__FILE__) . 'includes/auth/interface-wp-smsn1-ru-auth-provider.php';
require_once plugin_dir_path(__FILE__) . 'includes/auth/functions-pkce.php';
require_once plugin_dir_path(__FILE__) . 'includes/auth/class-wp-smsn1-ru-provider-vk.php';
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-oauth.php';
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-admin.php'; //Этот файл будет содержать код для добавления настроек плагина в административной панели.
// Проверка работоспособности плагина и активирован ли WooCommerce
if (  wp_smsn1_ru_get_balance() >= 10 && wp_smsn1_ru_get_balance() != "Проблема с соединением" && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
	require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-woo.php'; //Функции необходимые для работы с smsn1.ru
}
//Проверяем и при необходимости создаем необходимые страницы и настройки плагина во время активации
function wp_smsn1_ru_create_pages_on_activation() {
    // Группа опций
    $option_group = 'wp_smsn1_ru_settings_group';

    // 1. Проверяем и устанавливаем опцию wp_smsn1_ru_use_custom_login_form_redirect_url
    if (!get_option('wp_smsn1_ru_use_custom_login_form_redirect_url')) {
        update_option('wp_smsn1_ru_use_custom_login_form_redirect_url', 'login-by-wp-smsn1-ru');
    }
    // 2. Проверяем и устанавливаем опцию wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url
    if (!get_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url')) {
        update_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url', 'login-by-wp-smsn1-ru');
    }
	/*
    // 2. Проверяем и устанавливаем опцию wp_smsn1_ru_use_custom_login_redirect_url
    $login_redirect_url = get_option('wp_smsn1_ru_use_custom_login_redirect_url');
    if (!$login_redirect_url) {
        // Проверяем, активирован ли WooCommerce
        if (class_exists('WooCommerce')) {
            update_option('wp_smsn1_ru_use_custom_login_redirect_url', 'my-account');
        } else {
            update_option('wp_smsn1_ru_use_custom_login_redirect_url', 'wp-admin');
        }
    }	*/
    // Проверяем, существует ли страница с указанным alias
    if (!get_page_by_path('login-by-wp-smsn1-ru')) {
        // Создаем страницу "Войти на сайт"
        wp_insert_post(array(
            'post_title'    => 'Войти на сайт',
            'post_content'  => '[wp_smsn1_ru_login_form]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'login-by-wp-smsn1-ru'
        ));
    }

    if (!get_page_by_path('registation-by-wp-smsn1-ru')) {
        // Создаем страницу "Регистрация"
        wp_insert_post(array(
            'post_title'    => 'Регистрация',
            'post_content'  => '[wp_smsn1_ru_register_form]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'registation-by-wp-smsn1-ru'
        ));
    }

    if (!get_page_by_path('privacy-policy')) {
        // Создаем страницу "Политика обработки персональных данных"
        wp_insert_post(array(
            'post_title'    => 'Политика обработки персональных данных',
            'post_content'  => 'Политика обработки персональных данных',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'privacy-policy'
        ));
    }
}
// Регистрируем функцию создания страниц при активации плагина
register_activation_hook(__FILE__, 'wp_smsn1_ru_create_pages_on_activation');


// Инициализация плагина
add_action('init', 'wp_smsn1_ru_init');

function wp_smsn1_ru_init() {
    // Подключение скриптов и стилей
    add_action('wp_enqueue_scripts', 'wp_smsn1_ru_scripts');
    
    // Регистрация шорткодов для форм
    add_shortcode('wp_smsn1_ru_register_form', 'wp_smsn1_ru_register_form_shortcode');
    add_shortcode('wp_smsn1_ru_login_form', 'wp_smsn1_ru_login_form_shortcode');
    
    // Обработка AJAX-запросов
    add_action('wp_ajax_send_sms_code', 'handle_send_sms_code');
    add_action('wp_ajax_nopriv_send_sms_code', 'handle_send_sms_code');
    
    add_action('wp_ajax_verify_sms_code', 'handle_verify_sms_code');
    add_action('wp_ajax_nopriv_verify_sms_code', 'handle_verify_sms_code');	
}
/**
 * Получает путь к шаблону с учетом переопределения в теме.
 *
 * @param string $template_name Имя файла шаблона (например, 'login-form.php').
 * @return string Полный путь к шаблону.
 */
function wp_smsn1_ru_locate_template($template_name) {
    // Путь к шаблону в теме пользователя
    $theme_template = locate_template('wp-smsn1-ru/' . $template_name);

    // Если шаблон найден в теме, используем его
    if ($theme_template) {
        return $theme_template;
    }

    // Иначе используем шаблон по умолчанию из плагина
    return plugin_dir_path(__FILE__) . 'templates/' . $template_name;
}
// Подключение скриптов и стилей
function wp_smsn1_ru_scripts() {
    wp_enqueue_style('wp-smsn1-ru-style', plugins_url('/assets/css/style.css', __FILE__), array(), WP_SMSN1_RU_VERSION);
    wp_enqueue_script('wp-smsn1-ru-script', plugins_url('/assets/js/script.js', __FILE__), array('jquery'), WP_SMSN1_RU_VERSION, true);
    
    // Локализация скрипта для AJAX
    wp_localize_script('wp-smsn1-ru-script', 'wp_smsn1_ru_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_smsn1_ru_nonce')
    ));
}
// Шорткод для формы авторизации
function wp_smsn1_ru_login_form_shortcode() {
    ob_start();
	$balance = wp_smsn1_ru_get_balance();
	if (is_user_logged_in()) 
		include wp_smsn1_ru_locate_template('exit-form.php');
	else	{
	    //проверяем, может проблемы с плагином
		if($balance >= 10 && $balance != "Проблема с соединением") {
			if(get_option('wp_smsn1_ru_email_not_need_mode') != 'yes') include wp_smsn1_ru_locate_template('login-form.php');
			else include wp_smsn1_ru_locate_template('reg-login-form.php');
		}
		else 	{
			include wp_smsn1_ru_locate_template('error-form.php');	
		}	
	}	
    return ob_get_clean();
}
// Шорткод для формы регистрации
function wp_smsn1_ru_register_form_shortcode() {
    ob_start();
	$balance = wp_smsn1_ru_get_balance();
	if (is_user_logged_in()) 
		include plugin_dir_path(__FILE__) . 'templates/exit-form.php';
	else {
		if($balance >= 10 && $balance != "Проблема с соединением") 
			include wp_smsn1_ru_locate_template('register-form.php');
		else 	
			include wp_smsn1_ru_locate_template('error-form.php');	
	}	
    return ob_get_clean();
}
//Функция привода номера к единому виду и страховка от взлома
function sanitize_my_phone_for_field($phone_number) {
	$phone_number = preg_replace('/[^0-9]+/', '', sanitize_text_field($phone_number));
	if(iconv_strlen($phone_number) == 11) {
		$phone_number = mb_substr( $phone_number, 1);		
	}	
	if(iconv_strlen($phone_number) == 10) 
		$phone_number = "+7" . $phone_number;
	else 
		$phone_number = "not_russian_num";

	return $phone_number;	
}	
/**
 * Режим входа: 'otp' — только код из СМС, 'password_otp' — пароль + код (2FA).
 */
function wp_smsn1_ru_auth_mode() {
    $mode = get_option('wp_smsn1_ru_auth_mode', 'otp');

    return $mode === 'password_otp' ? 'password_otp' : 'otp';
}

// Обработка отправки СМС кода
function handle_send_sms_code() {
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');

    $phone_number = isset($_POST['phone_number']) ? sanitize_my_phone_for_field(wp_unslash($_POST['phone_number'])) : 'not_russian_num';

    if ($phone_number === 'not_russian_num') {
        wp_send_json_error('Проверьте номер телефона, на текущий момент доставка СМС осуществляется только на номера Российской Федерации');
    }

    $type_of_form = isset($_POST['type_of_form']) ? sanitize_text_field(wp_unslash($_POST['type_of_form'])) : '';

    if ($type_of_form === 'login' && !wp_smsn1_ru_get_user_by_phone($phone_number)) {
        $register_url = home_url('/') . get_option('wp_smsn1_ru_use_custom_register_form_url', 'registation-by-wp-smsn1-ru');
        wp_send_json_error('Телефонный номер не найден, просьба <a href="' . esc_url($register_url) . '">зарегестрироваться(30 секунд)</a><style>.wp-smsn1-ru-form button[type="submit"]{background-color: #bbb;} .wp-smsn1-ru-form button[type="submit"] ~ button{    background-color: #0073aa; } </style>');
    }

    // Защита от SMS-бомбинга: пауза между отправками и часовые лимиты на номер и IP
    $can_send = wp_smsn1_ru_otp_can_send($phone_number);

    if ($can_send !== true) {
        wp_send_json_error($can_send);
    }

    $sms_code = wp_smsn1_ru_otp_generate();

    // Сначала отправляем и только после успеха сохраняем код: если СМС не ушла,
    // пользователь не должен ждать истечения кода, которого он не получил.
    $sms_sent = wp_smsn1_ru_send_code_function($phone_number, $sms_code);

    if ($sms_sent !== true) {
        $message = is_numeric($sms_sent) ? '#' . $sms_sent : $sms_sent;
        wp_send_json_error('Ошибка при отправке СМС: ' . $message);
    }

    wp_smsn1_ru_otp_store($phone_number, $sms_code);
    wp_smsn1_ru_otp_mark_sent($phone_number);

    wp_send_json_success('Код отправлен на ваш номер: ' . $phone_number);
}

// Обработка проверки кода
function handle_verify_sms_code() {
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');

    $phone_number = isset($_POST['phone_number']) ? sanitize_my_phone_for_field(wp_unslash($_POST['phone_number'])) : 'not_russian_num';

    if ($phone_number === 'not_russian_num') {
        wp_send_json_error('Проверьте номер телефона, на текущий момент доставка СМС осуществляется только на номера Российской Федерации');
    }

    $user_code  = isset($_POST['sms_code']) ? sanitize_text_field(wp_unslash($_POST['sms_code'])) : '';
    $email      = isset($_POST['reg_email']) ? sanitize_email(wp_unslash($_POST['reg_email'])) : '';
    $password   = isset($_POST['user_password']) ? (string) wp_unslash($_POST['user_password']) : '';
    $password2  = isset($_POST['user_password_confirm']) ? (string) wp_unslash($_POST['user_password_confirm']) : '';
    $two_factor = (wp_smsn1_ru_auth_mode() === 'password_otp');

    // Пользователь и e-mail проверяются до расхода кода: иначе ошибка в форме
    // «съедала» бы верный код, и его пришлось бы запрашивать заново.
    $user_id = wp_smsn1_ru_get_user_by_phone($phone_number);
    $user    = $user_id ? get_user_by('id', $user_id) : false;

    if (!$user_id) {
        $email_required = get_option('wp_smsn1_ru_email_not_need_mode') !== 'yes';

        if ($email_required && !is_email($email)) {
            wp_send_json_error('Укажите корректный e-mail: он потребуется для восстановления доступа.');
        }

        if ($email !== '' && email_exists($email)) {
            wp_send_json_error('Этот e-mail уже зарегистрирован.');
        }
    }

    if ($two_factor) {
        $has_password = $user && get_user_meta($user->ID, 'wp_smsn1_ru_password_set', true);

        if ($password === '') {
            wp_send_json_error('Введите пароль. Если вы ещё не задавали пароль, придумайте новый — он сохранится после подтверждения кода.');
        }

        if ($password2 !== '' && $password !== $password2) {
            wp_send_json_error('Пароли не совпадают.');
        }

        if ($has_password) {
            // Ограничение попыток, чтобы пароль нельзя было перебрать
            $fail_key = 'wp_smsn1_ru_pwd_fail_' . $user->ID;
            $fails    = (int) get_transient($fail_key);

            if ($fails >= 5) {
                wp_send_json_error('Слишком много неверных попыток ввода пароля. Попробуйте через 15 минут.');
            }

            if (!wp_check_password($password, $user->user_pass, $user->ID)) {
                set_transient($fail_key, $fails + 1, 15 * MINUTE_IN_SECONDS);
                wp_send_json_error('Неверный пароль.');
            }

            delete_transient($fail_key);
        } else {
            $problem = wp_smsn1_ru_password_problem($password);

            if ($problem !== false) {
                wp_send_json_error($problem);
            }
        }
    }

    // Не более 5 неверных попыток, после чего код аннулируется
    $checked = wp_smsn1_ru_otp_check($phone_number, $user_code);

    if ($checked !== true) {
        wp_send_json_error($checked);
    }

    $message = 'Успешная авторизация.';

    if (!$user_id) {
        $user_id = wp_smsn1_ru_create_user($phone_number, $email, $two_factor ? $password : null);

        if (is_wp_error($user_id)) {
            wp_send_json_error('Ошибка при создании пользователя: ' . $user_id->get_error_message());
        }

        if (!$user_id) {
            wp_send_json_error('Ошибка при создании пользователя.');
        }

        $message = 'Успешная регистрация, Вы авторизованы.';
    } elseif ($two_factor && !get_user_meta($user_id, 'wp_smsn1_ru_password_set', true)) {
        // Аккаунт создан в режиме «только OTP»: пароль, придуманный сейчас, сохраняем
        wp_set_password($password, $user_id);
        update_user_meta($user_id, 'wp_smsn1_ru_password_set', 1);
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        wp_send_json_error('Не удалось загрузить учётную запись.');
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // Хук нужен плагинам 2FA, аудита входов и WooCommerce
    do_action('wp_login', $user->user_login, $user);

    wp_send_json_success($message);
}

// Поиск пользователя по номеру телефона
function wp_smsn1_ru_get_user_by_phone($phone_number) {
    $user = get_users(array(
        'meta_key' => 'billing_phone',
        'meta_value' => $phone_number,
        'number' => 1,
        'fields' => 'ID'
    ));
    
    return !empty($user) ? $user[0] : false;
}

// Создание нового пользователя
function wp_smsn1_ru_create_user($phone_number, $email, $password = null) {
    $username = 'user_' . mb_substr($phone_number, 2);

    // Логин должен быть уникальным: при совпадении добавляем случайный суффикс
    if (username_exists($username)) {
        $username .= '_' . wp_rand(1000, 9999);
    }

    $explicit_password = ($password !== null && $password !== '');
    $final_password    = $explicit_password ? $password : wp_generate_password(20, true, true);

    $user_id = wp_create_user($username, $final_password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Номер хранится в billing_phone — так его видят WooCommerce и другие плагины
    update_user_meta($user_id, 'billing_phone', $phone_number);

    // Метка «пользователь знает свой пароль»: нужна для режима «Пароль + OTP»
    if ($explicit_password) {
        update_user_meta($user_id, 'wp_smsn1_ru_password_set', 1);
    }

    return $user_id;
}

/* Удаление всех опций из БД после удаления плагина 
// Функция для удаления всех опций плагина
function wp_smsn1_ru_uninstall() {
    // Удаляем все опции, которые были созданы плагином
    delete_option('wp_smsn1_ru_sender');
    delete_option('wp_smsn1_ru_login');
    delete_option('wp_smsn1_ru_api_key');
    delete_option('wp_smsn1_ru_email_not_need_mode');
    delete_option('wp_smsn1_ru_verphone_on_checkout_mode');
    delete_option('wp_smsn1_ru_use_custom_login_form_url');
    delete_option('wp_smsn1_ru_use_custom_register_form_url');
    delete_option('wp_smsn1_ru_use_custom_login_redirect_url');
    delete_option('wp_smsn1_ru_logout_redirect_url');
    delete_option('wp_smsn1_ru_use_custom_login_form_redirect');
    delete_option('wp_smsn1_ru_use_custom_login_form_redirect_url');
    delete_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect');
    delete_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url');
    delete_option('wp_smsn1_ru_session_expiration_hours');

    // Удаляем транзиенты, если они используются
    delete_transient('wp_smsn1_ru_get_senders_temp');
    delete_transient('wp_smsn1_ru_get_balance_temp');
}

// Регистрируем функцию удаления при удалении плагина
register_uninstall_hook(__FILE__, 'wp_smsn1_ru_uninstall');
*/