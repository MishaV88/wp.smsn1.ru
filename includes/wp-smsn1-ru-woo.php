<?php
// Добавление кастомного поля для телефона и SMS-кода (только для не авторизованных пользователей)
add_action('woocommerce_after_order_notes', 'wp_smsn1_ru_add_custom_phone_field');

function wp_smsn1_ru_add_custom_phone_field($checkout) {

    // Проверяем, авторизован ли пользователь или опция валидации номера не включена
    if (is_user_logged_in() || get_option('wp_smsn1_ru_verphone_on_checkout_mode') != 'yes') {
        return; // Если авторизован, выходим из функции
    }
	echo '<div id="wp_smsn1_ru_custom_phone_field"><h3>' . __('Подтверждение номера телефона') . '</h3>';

	// Поле для ввода номера телефона
	woocommerce_form_field('custom_phone', array(
		'type' => 'text',
		'class' => array('form-row-wide'),
		'label' => __('Номер телефона'),
		'required' => true,
	), $checkout->get_value('billing_phone'));

	// Поле для ввода SMS-кода
	woocommerce_form_field('sms_code', array(
		'type' => 'text',
		'class' => array('form-row-wide'),
		'label' => __('Код подтверждения'),
		'required' => true,
	), $checkout->get_value('sms_code'));

	// Кнопка для отправки SMS-кода
	echo '<button type="button" id="send-sms-code-checkout" class="button">' . __('Отправить код') . '</button>';
	echo '<div id="wp-smsn1-ru-message"></div>';
	echo '</div>';
}

// Добавление CSS для скрытия стандартных полей телефона
add_action('wp_head', 'wp_smsn1_ru_hide_default_phone_fields');
function wp_smsn1_ru_hide_default_phone_fields() {
    if (is_checkout()) {
        echo '<style>
            #billing_phone_field, #shipping_phone_field {
                display: none !important;
            }
        </style>';
    }
}
// Валидация SMS-кода перед завершением заказа
add_action('woocommerce_checkout_process', 'wp_smsn1_ru_validate_checkout_sms_code');
function wp_smsn1_ru_validate_checkout_sms_code() {
    // Проверка нужна только гостям и только при включённой опции:
    // иначе авторизованный покупатель не может оформить заказ (поля кода в его форме нет).
    if (get_option('wp_smsn1_ru_verphone_on_checkout_mode') !== 'yes' || is_user_logged_in()) {
        return;
    }

    $phone_number = isset($_POST['custom_phone']) ? sanitize_my_phone_for_field(wp_unslash($_POST['custom_phone'])) : '';
    $user_code    = isset($_POST['sms_code']) ? sanitize_text_field(wp_unslash($_POST['sms_code'])) : '';

    if ($phone_number === '' || $phone_number === 'not_russian_num') {
        wc_add_notice(__('Укажите корректный номер телефона.'), 'error');
        return;
    }

    if ($user_code === '') {
        wc_add_notice(__('Пожалуйста, введите код подтверждения.'), 'error');
        return;
    }

    // consume = false: код остаётся действительным, если заказ не удалось оформить
    // с первой попытки (ошибка оплаты, повторная валидация).
    $checked = wp_smsn1_ru_otp_check($phone_number, $user_code, false);

    if ($checked !== true) {
        wc_add_notice($checked, 'error');
        return;
    }

    if (wp_smsn1_ru_get_user_by_phone($phone_number)) {
        wc_add_notice(__('Пользователь с таким номером телефона уже существует. <a href="' . esc_url(home_url('/') . get_option('wp_smsn1_ru_use_custom_login_form_url')) . '">Войдите в систему</a>.'), 'error');
    }
}

/*
// Сохранение кастомного телефона в заказе
add_action('woocommerce_checkout_create_order', 'wp_smsn1_ru_save_custom_phone', 20, 2);
function wp_smsn1_ru_save_custom_phone($order, $data) {
	if (!empty($_POST['custom_phone'])) {
		$phone_number = sanitize_my_phone_for_field($_POST['custom_phone']);
		$order->update_meta_data( '_billing_phone', $phone_number );
		$order->update_meta_data( '_shipping_phone', $phone_number );
    }
}
add_filter( 'woocommerce_checkout_customer_id', 'wp_smsn1_ru_woocommerce_checkout_customer_id_filter' );
function wp_smsn1_ru_woocommerce_checkout_customer_id_filter( $current_user_id ){
	$phone_number = sanitize_my_phone_for_field($_POST['custom_phone']);
	 update_user_meta($current_user_id, 'billing_phone', $phone_number);
	 update_user_meta($current_user_id, 'shipping_phone', $phone_number);
	// filter...
	return $current_user_id;
} */
// Шорткод для вывода блока с телефоном и SMS-кодом (только для не авторизованных пользователей)
add_shortcode('wp_smsn1_ru_phone_verification', 'wp_smsn1_ru_phone_verification_shortcode');

function wp_smsn1_ru_phone_verification_shortcode() {
    // Проверяем, авторизован ли пользователь
    if (is_user_logged_in()) {
        return ''; // Если авторизован, возвращаем пустую строку
    }

    ob_start();
    ?>
    <div id="wp_smsn1_ru_custom_phone_field">
        <h3><?php _e('Подтверждение номера телефона'); ?></h3>
        <p class="form-row form-row-wide">
            <label for="custom_phone"><?php _e('Номер телефона'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="custom_phone" id="custom_phone" placeholder="9991234567" />
        </p>
        <p class="form-row form-row-wide" >
            <label  for="sms_code"><?php _e('Код подтверждения'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="sms_code" id="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" placeholder="<?php esc_attr_e('Введите код из СМС'); ?>" />
        </p>
        <button type="button" id="send-sms-code-checkout" class="button"><?php _e('Отправить код'); ?></button>
    </div>
    <?php
    return ob_get_clean();
}

// Проверка существования пользователя по номеру телефона
add_action('wp_ajax_check_user_by_phone', 'wp_smsn1_ru_check_user_by_phone');
add_action('wp_ajax_nopriv_check_user_by_phone', 'wp_smsn1_ru_check_user_by_phone');

function wp_smsn1_ru_check_user_by_phone() {
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');

    $phone_number = sanitize_my_phone_for_field($_POST['phone_number']);
    $user_id = wp_smsn1_ru_get_user_by_phone($phone_number);

    if ($user_id) {
        wp_send_json_success(array('exists' => true, 'res_url' => get_option('wp_smsn1_ru_use_custom_login_form_url')));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}
