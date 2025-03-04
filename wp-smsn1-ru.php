<?php
/*
 * Plugin Name: WP.SMSn1.RU(OTP&Verification by SMS): Одноразовый пароль и верификация по СМС
 * Description: Интеграция СМС уведомлений, верификация, регистрация и авторизация по номеру телефона с использованием одноразового кода из СМС. СМС уведомления в том числе для WooCommerce. Пока только для сотовых операторов Российской Федерации.
 * Plugin URI:  https://wp.smsn1.ru/
 * Author URI:  https://docs-group.ru/
 * Support URI: https://wp.smsn1.ru/контакты-службы-поддержки/
 * Version: 1.0
 * Author: Vasilev Mikhail Y.
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа к файлу
}

// Подключение необходимых файлов
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-functions.php'; //Функции необходимые для работы с wp.smsn1.ru(если быть точнее direct.docs-group.ru)
require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-admin.php'; //Этот файл будет содержать код для добавления настроек плагина в административной панели.
// Проверка работоспособности плагина и активирован ли WooCommerce
if (  wp_smsn1_ru_get_balance() >= 10 && wp_smsn1_ru_get_balance() != "Проблема с соединением" && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
	require_once plugin_dir_path(__FILE__) . 'includes/wp-smsn1-ru-woo.php'; //Функции необходимые для работы с smsn1.ru
}
//Проверяем и при необходимости создаем необходимые страницы и настройки плагина во время активации
function wp_smsn1_ru_create_pages_on_activation() {
    // Группа опций
    /*$option_group = 'wp_smsn1_ru_settings_group';

    // 1. Проверяем и устанавливаем опцию wp_smsn1_ru_use_custom_register_form_url
    $register_form_url = get_option('wp_smsn1_ru_use_custom_register_form_url');
    if (!$register_form_url) {
        update_option('wp_smsn1_ru_use_custom_register_form_url', 'registation-by-wp-smsn1-ru');
    }

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
    wp_enqueue_style('wp-smsn1-ru-style', plugins_url('/assets/css/style.css', __FILE__));
    wp_enqueue_script('wp-smsn1-ru-script', plugins_url('/assets/js/script.js', __FILE__), array('jquery'), null, true);
    
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
// Обработка отправки СМС кода
function handle_send_sms_code() {
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');
    $phone_number = sanitize_my_phone_for_field($_POST['phone_number']);

	if($phone_number == "not_russian_num")
		wp_send_json_error('Проверьте номер телефона, на текущий момент доставка СМС осуществляется только на номера Российской Федерации');
    // Генерация кода
    $sms_code = wp_rand(1000, 9999);
    $type_of_form = $_POST['type_of_form']; // Получение e-mail
	if($type_of_form == "login") {
		$user_id = wp_smsn1_ru_get_user_by_phone($phone_number);
		if(!$user_id)
			wp_send_json_error('Телефонный номер не найден, просьба <a href="#">зарегестрироваться(30 секунд)</a><style>.wp-smsn1-ru-form button[type="submit"]{background-color: #bbb;} .wp-smsn1-ru-form button[type="submit"] ~ button{    background-color: #0073aa; } </style>');
	}	
    // Сохранение кода в transient (временное хранилище)
    set_transient('wp_smsn1_ru_code_' . $phone_number, $sms_code, 5 * MINUTE_IN_SECONDS);
    
    // Вызов вашей функции отправки СМС
    $sms_sent = wp_smsn1_ru_send_code_function($phone_number, $sms_code);
    if ($sms_sent === true) {
        wp_send_json_success('Код отправлен на ваш номер: '.$phone_number );
    } else {
        wp_send_json_error('Ошибка при отправке СМС: #' . $sms_sent);
    }
}

// Обработка проверки кода
function handle_verify_sms_code() {
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');
    $phone_number = sanitize_my_phone_for_field($_POST['phone_number']);
	if($phone_number == "phone_is_too_short")
		wp_send_json_error("Проверьте номер телефона, на текущий момент доставка СМС осуществляется только на номера Российской Федерации");
    $user_code = sanitize_text_field($_POST['sms_code']);
	$email = sanitize_email($_POST['reg_email']); // Получение e-mail
    $type_of_form = sanitize_email($_POST['type_of_form']); // Получение e-mail
															  
    
    // Получение сохраненного кода
    $saved_code = get_transient('wp_smsn1_ru_code_' . $phone_number);
    
    if ($user_code == $saved_code &&  strlen($user_code) > 2) {
        
        
        // Регистрация или авторизация пользователя
        $user_id = wp_smsn1_ru_get_user_by_phone($phone_number);
        
        if (!$user_id) {
            // Создание нового пользователя
			// Проверка, что e-mail уникален	
			if (email_exists($email)) {
				wp_send_json_error('Этот e-mail уже зарегистрирован.');
			} else {		
				$user_id = wp_smsn1_ru_create_user($phone_number, $email);
				wp_set_auth_cookie($user_id, true);
				wp_send_json_success('Успешная регистрация, Вы авторизованы.');
			}
        }
        
        if ($user_id) {
			// Авторизация пользователя				   
            wp_set_auth_cookie($user_id, true);
            wp_send_json_success('Успешная авторизация.');
        } else {
            wp_send_json_error('Ошибка при создании пользователя.');
        }
    
		// Успешная проверка код больше не нужен
        delete_transient('wp_smsn1_ru_code_' . $phone_number);
	
	} else {
        wp_send_json_error('Неверный код.');
    }
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
function wp_smsn1_ru_create_user($phone_number, $email) {
    $username = 'user_' . mb_substr( $phone_number, 2);
    $password = wp_generate_password();
    
	    // Создание пользователя с e-mail
    $user_id = wp_create_user($username, $password, $email);
    
    if (!is_wp_error($user_id)) {
        // Сохранение номера телефона в user meta
        //update_user_meta($user_id, 'phone_number', $phone_number); // Для внутреннего использования
        update_user_meta($user_id, 'billing_phone', $phone_number); // Для WooCommerce или других плагинов
        
        return $user_id;
    }
    return false;
}

	