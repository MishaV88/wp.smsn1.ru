<?php
//Этот файл будет содержать код для добавления настроек плагина в административной панели.
// Запрет прямого доступа к файлу
if (!defined('ABSPATH')) {
    exit;
}

// Добавление страницы настроек плагина
function wp_smsn1_ru_add_admin_menu() {
    add_menu_page(
        'WP.SMSn1.RU Настройки',
        'WP.SMSn1.RU',
        'manage_options',
        'wp-smsn1-ru-settings',
        'wp_smsn1_ru_settings_page',
        'dashicons-privacy',
        100
    );
}
add_action('admin_menu', 'wp_smsn1_ru_add_admin_menu');
function wp_smsn1_ru_admin_styles() {
    wp_enqueue_style(
        'wp-smsn1-ru-admin-style',
        plugins_url('../assets/css/admin-style.css', __FILE__)
    );
}
add_action('admin_enqueue_scripts', 'wp_smsn1_ru_admin_styles');
// Функция для отображения страницы настроек
function wp_smsn1_ru_settings_page() {
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
	 // Получаем URL плагина для доступа к иконкам
    $plugin_url = plugin_dir_url(__FILE__);$plugin_url = plugin_dir_url(dirname(__FILE__)); // Переход на уровень выше
    ?>
    <div class="wrap">
        <h1>Настройки плагина WP.SMSn1.RU(OTP) Одноразовый пароль и верификация по СМС</h1>
		<h2>Баланс в сервисе smsn1.ru: <?= wp_smsn1_ru_get_balance(); ?>₽<span style="color:#999;">(синхронизация раз в 2 минуты)</span></h2>
        <!-- Блок с ссылками на поддержку -->
        <div class="wp-smsn1-ru-support-block">
            <h2>Поддержка</h2>
            <p>Если у Вас возникли вопросы или проблемы, в том числе <strong>как пополнить баланс</strong>, свяжитесь с нами:</p>
            <div class="support-links">
                <a href="https://t.me/sup_smsn1_ru" target="_blank" class="support-link telegram">
                    <img src="<?php echo esc_url($plugin_url . '/assets/icons/telegram.svg'); ?>" alt="Телеграм">
                    &nbsp;Телеграм
                </a>
                <a href="https://api.whatsapp.com/send/?phone=9099243900&text&type=phone_number&app_absent=0" target="_blank" class="support-link whatsapp">
                    <img src="<?php echo esc_url($plugin_url . '/assets/icons/whatsapp.svg'); ?>" alt="WhatsApp">
                    &nbsp;WhatsApp
                </a>
				<a href="https://wp.smsn1.ru/инструкция-по-настроике-плагина/" target="_blank" class="support-link whatsapp">
                  Инструкция и дополнительные контакты: wp.smsn1.ru
                </a>
            </div>
        </div>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_smsn1_ru_settings_group');
            do_settings_sections('wp-smsn1-ru-settings');
            submit_button();
            ?>
        </form>

        <!-- Блок с кодами ошибок API -->
        <div class="wp-smsn1-ru-support-block" style="margin-top: 30px;">
            <h2>Коды ошибок API</h2>
            <p>Если при отправке СМС возникает ошибка, в сообщении будет указан её код. Расшифровка:</p>
            <table class="widefat striped" style="max-width: 700px;">
                <tbody>
                    <tr><td style="width: 80px;"><strong>000</strong></td><td>Сервис отключен</td></tr>
                    <tr><td><strong>1</strong></td><td>Не указана подпись</td></tr>
                    <tr><td><strong>2</strong></td><td>Не указан логин</td></tr>
                    <tr><td><strong>3</strong></td><td>Не указан текст</td></tr>
                    <tr><td><strong>4</strong></td><td>Не указан телефон</td></tr>
                    <tr><td><strong>5</strong></td><td>Не указан отправитель</td></tr>
                    <tr><td><strong>6</strong></td><td>Не корректная подпись</td></tr>
                    <tr><td><strong>7</strong></td><td>Не корректный логин</td></tr>
                    <tr><td><strong>8</strong></td><td>Не корректное имя отправителя</td></tr>
                    <tr><td><strong>9</strong></td><td>Не зарегистрированное имя отправителя</td></tr>
                    <tr><td><strong>10</strong></td><td>Не одобренное имя отправителя</td></tr>
                    <tr><td><strong>11</strong></td><td>В тексте содержатся запрещенные слова</td></tr>
                    <tr><td><strong>12</strong></td><td>Ошибка отправки СМС</td></tr>
                    <tr><td><strong>13</strong></td><td>Номер находится в стоп-листе. Отправка на этот номер запрещена</td></tr>
                    <tr><td><strong>14</strong></td><td>В запросе более 50 номеров</td></tr>
                    <tr><td><strong>15</strong></td><td>Не указана база</td></tr>
                    <tr><td><strong>16</strong></td><td>Не корректный номер</td></tr>
                    <tr><td><strong>17</strong></td><td>Не указаны ID СМС</td></tr>
                    <tr><td><strong>18</strong></td><td>Не получен статус</td></tr>
                    <tr><td><strong>19</strong></td><td>Пустой ответ</td></tr>
                    <tr><td><strong>20</strong></td><td>Номер уже существует</td></tr>
                    <tr><td><strong>21</strong></td><td>Отсутствует название</td></tr>
                    <tr><td><strong>22</strong></td><td>Шаблон уже существует</td></tr>
                    <tr><td><strong>23</strong></td><td>Не указан месяц (Формат: YYYY-MM)</td></tr>
                    <tr><td><strong>24</strong></td><td>Не указана временная метка</td></tr>
                    <tr><td><strong>25</strong></td><td>Ошибка доступа к базе</td></tr>
                    <tr><td><strong>26</strong></td><td>База не содержит номеров</td></tr>
                    <tr><td><strong>27</strong></td><td>Нет валидных номеров</td></tr>
                    <tr><td><strong>28</strong></td><td>Не указана начальная дата</td></tr>
                    <tr><td><strong>29</strong></td><td>Не указана конечная дата</td></tr>
                    <tr><td><strong>30</strong></td><td>Не указана дата (Формат: YYYY-MM-DD)</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// Улучшенная функция для регистрации настроек
function wp_smsn1_ru_register_settings() {
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_sender', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_login', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_api_key', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_email_not_need_mode', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_verphone_on_checkout_mode', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_form_url', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_register_form_url', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_redirect_url', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_logout_redirect_url', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_form_redirect', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_form_redirect_url', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_form_my_acc_redirect', 'sanitize_text_field');
    register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url', 'sanitize_text_field');
	register_setting('wp_smsn1_ru_settings_group', 'wp_smsn1_ru_session_expiration_hours', array( 'sanitize_callback' => 'absint' ));

    add_settings_section('wp_smsn1_ru_main_section', 'Настройки отправки СМС', null, 'wp-smsn1-ru-settings');
    add_settings_field('wp_smsn1_ru_sender', 'Отправитель SMS', 'wp_smsn1_ru_sender_callback', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_main_section');
    add_settings_field('wp_smsn1_ru_login', 'Логин для smsn1.ru', 'wp_smsn1_ru_login_callback', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_main_section');
    add_settings_field('wp_smsn1_ru_api_key', 'API ключ для smsn1.ru', 'wp_smsn1_ru_api_key_callback', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_main_section');
	    
	add_settings_section('wp_smsn1_ru_session_section', 'Настройки сессии', null, 'wp-smsn1-ru-settings');
    add_settings_field(
			'wp_smsn1_ru_session_expiration_hours',
			'Время жизни сессии (в часах)',
			'wp_smsn1_ru_session_expiration_hours_callback',
			'wp-smsn1-ru-settings',
			'wp_smsn1_ru_session_section'
	);
	
    add_settings_section('wp_smsn1_ru_operating_modes_section', 'Режим работы', null, 'wp-smsn1-ru-settings');
    add_settings_field('wp_smsn1_ru_email_not_need_mode', 'Разрешить быструю регистрацию', 'wp_smsn1_ru_email_not_need_mode', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_operating_modes_section');
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_settings_field('wp_smsn1_ru_verphone_on_checkout_mode', 'Включить верификацию номера телефона на странице оформления заказа', 'wp_smsn1_ru_verphone_on_checkout_mode', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_operating_modes_section');
    }

    add_settings_section('wp_smsn1_ru_redirects_section', 'Настройки ссылок и перенаправлений(редиректов)', null, 'wp-smsn1-ru-settings');
    add_settings_field('wp_smsn1_ru_use_custom_login_form_url', 'Ссылка на страницу с формой входа на сайт.', 'wp_smsn1_ru_use_custom_login_form_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    add_settings_field('wp_smsn1_ru_use_custom_register_form_url', 'Ссылка на страницу с формой регистрации.', 'wp_smsn1_ru_use_custom_register_form_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    add_settings_field('wp_smsn1_ru_use_custom_login_redirect_url', 'На какую страницу перенапрвлять пользователя, после успешной авторизации или регистрации.', 'wp_smsn1_ru_use_custom_login_redirect_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    add_settings_field('wp_smsn1_ru_logout_redirect_url', 'Страница перенаправления после выхода из аккаунта', 'wp_smsn1_ru_logout_redirect_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    add_settings_field('wp_smsn1_ru_use_custom_login_form_redirect', 'Включить редирект с стандартной страницы входа и регистрации(wp-login.php)*', 'wp_smsn1_ru_use_custom_login_form_redirect', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    add_settings_field('wp_smsn1_ru_use_custom_login_form_redirect_url', 'Куда редирект:', 'wp_smsn1_ru_use_custom_login_form_redirect_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_settings_field('wp_smsn1_ru_use_custom_login_form_my_acc_redirect', 'Включить редирект с страницы входа и регистрации WooCommerce("my-account", только если пользователь не авторизован)*', 'wp_smsn1_ru_use_custom_login_form_my_acc_redirect', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
        add_settings_field('wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url', 'Куда редирект:', 'wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url', 'wp-smsn1-ru-settings', 'wp_smsn1_ru_redirects_section');
    }
}
add_action('admin_init', 'wp_smsn1_ru_register_settings');
//Настройки отправки СМС
// Функция для отображения выбора отправителя
function wp_smsn1_ru_sender_callback() {
    $senders = wp_smsn1_ru_get_senders(); // Получаем массив отправителей
    $selected_sender = get_option('wp_smsn1_ru_sender'); // Получаем выбранного отправителя

    if (!empty($senders) && !isset($senders['Ошибка №2']) && !isset($senders['Ошибка №6'])) {
        echo '<select name="wp_smsn1_ru_sender" class="regular-text">';
        echo '<option value="">-- Выберите отправителя --</option>';
        foreach ($senders as $sender => $status) {
            $selected = selected($selected_sender, $sender, false);
            echo '<option value="' . esc_attr($sender) . '" ' . $selected . '>' . esc_html($sender) . '</option>';
        }
        echo '</select>';
		echo '<p class="description">только согласованные, синхронизация раз в 4 минуты</p>';

    } else {
        echo '<p style="color: #f00; font-weight: 600;">Нет доступных отправителей.</p>';
    }
}
function wp_smsn1_ru_login_callback() {
    $login = get_option('wp_smsn1_ru_login');
    echo '<input type="text" name="wp_smsn1_ru_login" value="' . esc_attr($login) . '" class="regular-text" placeholder="login">';
}
function wp_smsn1_ru_api_key_callback() {
    $api_key = get_option('wp_smsn1_ru_api_key');
    echo '<input type="text" name="wp_smsn1_ru_api_key" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="API key">';
}
// для Настройки режима работы
function wp_smsn1_ru_email_not_need_mode() {
    $checked = get_option('wp_smsn1_ru_email_not_need_mode');
	if($checked == 'yes') $checked = 'checked';
	else $checked = '';
	echo '<input type="checkbox" name="wp_smsn1_ru_email_not_need_mode" id="wp_smsn1_ru_email_not_need_mode" value="yes" '. $checked.' />';
	echo '<p class="description">Система позволяет пользователям авторизоваться без предварительной регистрации(не нужно вводить e-mail). При этом автоматически создаётся учётная запись с указанным номером. На самой форме регистрации необходимость вводить e-mail остается, но ее можно просто в этом случае не использовать.</p>';
}
function wp_smsn1_ru_verphone_on_checkout_mode() {
    $checked = get_option('wp_smsn1_ru_verphone_on_checkout_mode');
	if($checked == 'yes') $checked = 'checked';
	else $checked = '';
	echo '<input type="checkbox" name="wp_smsn1_ru_verphone_on_checkout_mode" id="wp_smsn1_ru_verphone_on_checkout_mode" value="yes" '. $checked.' />';
	echo '<p class="description">Взаимодействет с плагином WooCommerce. В случае если клиент интернет-магазина не прошёл авторизацию, при оформлении заказа ему потребуется подтвердить свой номер телефона. Если клиент с таким номером телефона уже зарегистрирован, заказ будет привязан к его аккаунту.</p>';
}
// Функция для отображения поля ввода часов авторизации сессии пользователя
function wp_smsn1_ru_session_expiration_hours_callback() {
    $expiration_hours = get_option('wp_smsn1_ru_session_expiration_hours', 96); // По умолчанию 96 часов (4 суток)
    echo '<input type="number" name="wp_smsn1_ru_session_expiration_hours" value="' . esc_attr($expiration_hours) . '" min="1" />';
    echo '<p class="description">Укажите количество <strong>часов</strong>, на которое будет запоминаться сессия пользователя («Запомнить меня» в форме от wp.smsn1.ru выбирается по умолчанию).</p>';
}
//Функции для Настройки ссылок и перенаправлений(редиректов)
function wp_smsn1_ru_use_custom_login_form_url() {
    $url = get_option('wp_smsn1_ru_use_custom_login_form_url', 'login-by-wp-smsn1-ru');
    echo 'www.' . $_SERVER['HTTP_HOST'].'/<input type="text" name="wp_smsn1_ru_use_custom_login_form_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="login-by-wp-smsn1-ru(пример)">';
}
function wp_smsn1_ru_use_custom_register_form_url() {
    $url = get_option('wp_smsn1_ru_use_custom_register_form_url', 'registation-by-wp-smsn1-ru');
    echo 'www.' . $_SERVER['HTTP_HOST'].'/<input type="text" name="wp_smsn1_ru_use_custom_register_form_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="registation-by-wp-smsn1-ru(пример)">';
}
function wp_smsn1_ru_use_custom_login_redirect_url() {
    $url = get_option('wp_smsn1_ru_use_custom_login_redirect_url', 'wp-admin');
    echo 'www.' . $_SERVER['HTTP_HOST'].'/<input type="text" name="wp_smsn1_ru_use_custom_login_redirect_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="wp-admin(пример)">';
}
// Функция для отображения поля ввода URL перенаправления после выхода
function wp_smsn1_ru_logout_redirect_url() {
    $redirect_url = get_option('wp_smsn1_ru_logout_redirect_url', 'login-by-wp-smsn1-ru');
    echo 'www.' . $_SERVER['HTTP_HOST'] . '/<input type="text" name="wp_smsn1_ru_logout_redirect_url" value="' . esc_attr($redirect_url) . '" class="regular-text" placeholder="login-by-wp-smsn1-ru(пример)">';
    echo '<p class="description">Введите URL страницы, на которую будет перенаправлен пользователь после выхода.</p>';
}
function wp_smsn1_ru_use_custom_login_form_redirect() {
    $checked = get_option('wp_smsn1_ru_use_custom_login_form_redirect');
	if($checked == 'yes') $checked = 'checked';
	else $checked = '';
	echo '<input type="checkbox" name="wp_smsn1_ru_use_custom_login_form_redirect" id="wp_smsn1_ru_use_custom_login_form_redirect" value="yes" '.$checked.' />';
	echo '<p class="description">*Автоматически отключается если баланс меньше 10р, а так же прочих проблем с СМС</p>';
	?>
	<script>
    jQuery(document).ready(function() {
        jQuery('#wp_smsn1_ru_use_custom_login_form_redirect').change(function() {
            jQuery('#wp_smsn1_ru_use_custom_login_form_redirect_url').prop('disabled', !this.checked);
        });
    });
	</script>
	<?php
}
function wp_smsn1_ru_use_custom_login_form_redirect_url() {
    $url = get_option('wp_smsn1_ru_use_custom_login_form_redirect_url', 'login-by-wp-smsn1-ru');
	$checked = get_option('wp_smsn1_ru_use_custom_login_form_redirect');
	if($checked != 'yes') $disabled = 'disabled';
	else $disabled = '';
    echo 'www.' . $_SERVER['HTTP_HOST'].'/<input type="text" name="wp_smsn1_ru_use_custom_login_form_redirect_url" id="wp_smsn1_ru_use_custom_login_form_redirect_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="login-by-wp-smsn1-ru(пример)" '.$disabled.'>';
}
//Взаимодействует с WooCommerce
function wp_smsn1_ru_use_custom_login_form_my_acc_redirect() {
    $checked = get_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect');
	if($checked == 'yes') $checked = 'checked';
	else $checked = '';
	echo '<input type="checkbox" name="wp_smsn1_ru_use_custom_login_form_my_acc_redirect" id="wp_smsn1_ru_use_custom_login_form_my_acc_redirect" value="yes" '.$checked.' />';
	echo '<p class="description">*Автоматически отключается если баланс меньше 10р, а так же прочих проблем с СМС</p>';
	?>
	<script>
    jQuery(document).ready(function() {
        jQuery('#wp_smsn1_ru_use_custom_login_form_my_acc_redirect').change(function() {
            jQuery('#wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url').prop('disabled', !this.checked);
        });
    });
	</script>
	<?php
}
function wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url() {
    $url = get_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url', 'login-by-wp-smsn1-ru');
	$checked = get_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect');
	if($checked != 'yes') $disabled = 'disabled';
	else $disabled = '';
    echo 'www.' . $_SERVER['HTTP_HOST'].'/<input type="text" name="wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url" id="wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="login-by-wp-smsn1-ru(пример)" '.$disabled.'>';
}
// Перенаправление неавторизованных пользователей с страницы wp-login.php
function wp_smsn1_ru_custom_login_redirect() {
	$balance = wp_smsn1_ru_get_balance();    
    if (!is_user_logged_in() && get_option('wp_smsn1_ru_use_custom_login_form_redirect') == 'yes' && $balance >= 10 && $balance != "Проблема с соединением") { // Проверяем условия для редиректа        
	$redirect_url = home_url('/') . get_option('wp_smsn1_ru_use_custom_login_form_redirect_url');
        // Выполняем редирект
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('login_init', 'wp_smsn1_ru_custom_login_redirect');

// Перенаправление неавторизованных пользователей с страницы my-account
function  wp_smsn1_ru_use_custom_redirect_non_logged_in_users() {
	$balance = wp_smsn1_ru_get_balance();
	if(get_option('wp_smsn1_ru_use_custom_login_form_my_acc_redirect') == 'yes' && $balance >= 10 && $balance != "Проблема с соединением" && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
	if ( !is_user_logged_in() && is_account_page() ) {      
			$redirect_url = get_option( 'wp_smsn1_ru_use_custom_login_form_my_acc_redirect_url' );
			wp_redirect( $redirect_url );
			exit;
		}
	}
		
}
add_action( 'template_redirect', 'wp_smsn1_ru_use_custom_redirect_non_logged_in_users' );

// Проверка баланса и вывод уведомления
function wp_smsn1_ru_check_balance_notice() {
    $balance = wp_smsn1_ru_get_balance();
    if ($balance < 10 && $balance != "Проблема с соединением") {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>Ваш баланс в сервисе smsn1.ru составляет менее 10 рублей. Используются стандартные формы региcтрации и авторизации. <a href="https://wp.smsn1.ru/%d0%ba%d0%be%d0%bd%d1%82%d0%b0%d0%ba%d1%82%d1%8b-%d1%81%d0%bb%d1%83%d0%b6%d0%b1%d1%8b-%d0%bf%d0%be%d0%b4%d0%b4%d0%b5%d1%80%d0%b6%d0%ba%d0%b8/" target="_blank">Пожалуйста, пополните баланс.</a></p>
        </div>
        <?php
    }
	if($balance == "Проблема с соединением") {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>Ошибка подключения к сервису smsn1.ru. Используются стандартные формы регситрации и авторизации. <a href="/wp-admin/admin.php?page=wp-smsn1-ru-settings">Пожалуйста проверьте Логин и API</a></p>
        </div>
        <?php		
	}	
}
add_action('admin_notices', 'wp_smsn1_ru_check_balance_notice');


// Добавляем поле "номер телефона" в таблицу пользователей
function add_phone_column( $columns ) {
	$columns['billing_phone'] = 'Номер телефона';
	return $columns;
}
add_filter( 'manage_users_columns', 'add_phone_column' );
// Отображаем значение поля "номер телефона" в таблице пользователей
function show_phone_column( $value, $column_name, $user_id ) {
	if ( 'billing_phone' == $column_name ) {
		return get_user_meta( $user_id, 'billing_phone', true );
	}
	return $value;
}
add_action( 'manage_users_custom_column', 'show_phone_column', 10, 3 );
// Проверяем, активирован ли WooCommerce, если нет отображаем номер телефона в админке редактирования пользователя
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // Добавляем поле "номер телефона" на страницу редактирования пользователя
    add_action( 'show_user_profile', 'add_phone_field' );
    add_action( 'edit_user_profile', 'add_phone_field' );
    function add_phone_field( $user ) {
        if ( current_user_can( 'manage_options' ) ) {
            ?>
            <h3>Дополнительная информация</h3>
            <table class="form-table">
                <tr>
                    <th><label for="billing_phone">Номер телефона</label></th>
                    <td>
                        <input type="text" name="billing_phone" id="billing_phone" value="<?php echo esc_attr( get_the_author_meta( 'billing_phone', $user->ID ) ); ?>" class="regular-text" /><br />
                        <span class="description">Введите номер телефона в формате: <strong>+79XXXXXXXXXX</strong></span>
                    </td>
                </tr>
            </table>
            <?php
        }
    }

    // Сохраняем значение поля "номер телефона" при редактировании профиля
    add_action( 'personal_options_update', 'save_phone_field' );
    add_action( 'edit_user_profile_update', 'save_phone_field' );
    function save_phone_field( $user_id ) {
        if ( current_user_can( 'manage_options' ) ) {
			$sanitized_phone = sanitize_my_phone_for_field($_POST['billing_phone']);
            update_user_meta( $user_id, 'billing_phone', $sanitized_phone );
        }
    }
}
//функция для перенаправления после выхода
function wp_smsn1_ru_redirect_after_logout() {
    // Получаем URL перенаправления из настроек
    $redirect_url = get_option('wp_smsn1_ru_logout_redirect_url');

    // Если URL задан, перенаправляем пользователя
    if (empty($redirect_url)) {
        $redirect_url = '/';
    }
	wp_redirect(home_url($redirect_url));
    exit();
}
add_action('wp_logout', 'wp_smsn1_ru_redirect_after_logout');

// Изменение времени жизни куки
add_filter('auth_cookie_expiration', 'wp_smsn1_ru_custom_auth_cookie_expiration', 10, 3);
function wp_smsn1_ru_custom_auth_cookie_expiration($expiration, $user_id, $remember) {
    // Получаем значение из настроек плагина
    $expiration_hours = get_option('wp_smsn1_ru_session_expiration_hours', 96); // По умолчанию 96 часов (4 суток)
    
    // Если "Запомнить меня" отмечено, устанавливаем время жизни куки
    if ($remember) {
        return $expiration_hours * 3600; // Преобразуем часы в секунды
    }
    
    // Иначе оставляем стандартное время (48 часов)
    return $expiration;
}
//Код, что бы кэш для имен отправителей сбрасывался
add_action('update_option', 'wp_smsn1_ru_reset_senders_transient_on_option_update', 10, 3);
function wp_smsn1_ru_reset_senders_transient_on_option_update($option_name, $old_value, $new_value) {
    // Проверяем, изменились ли нужные нам опции и действительно ли значение изменилось
    if (($option_name === 'wp_smsn1_ru_login' || $option_name === 'wp_smsn1_ru_api_key') && $old_value !== $new_value) {
        // Удаляем транзиент, если он существует
        delete_transient('wp_smsn1_ru_get_senders_temp');
		delete_transient('wp_smsn1_ru_get_balance_temp');
    }
}