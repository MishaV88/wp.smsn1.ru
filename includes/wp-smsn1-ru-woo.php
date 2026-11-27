<?php
/**
 * WooCommerce: подтверждение номера телефона при оформлении заказа.
 *
 * Работает для гостей (неавторизованных покупателей): в форму оформления добавляется
 * поле «Код из СМС», код проверяется тем же модулем OTP, что и при входе.
 *
 * Поле добавляется через `woocommerce_checkout_fields`, поэтому рендерится самим
 * WooCommerce и не зависит от того, какие хуки вызывает тема.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Включено ли подтверждение номера на оформлении заказа.
 *
 * По умолчанию включено: если опция ещё не сохранялась (новая установка), считаем «да».
 * Отключить можно снятой галочкой в настройках или фильтром.
 *
 * @return bool
 */
function wp_smsn1_ru_checkout_verification_enabled()
{
    $option = get_option('wp_smsn1_ru_verphone_on_checkout_mode', null);

    if ($option === null) {
        $enabled = true;
    } else {
        $enabled = ($option === 'yes');
    }

    return (bool) apply_filters('wp_smsn1_ru_checkout_verification_enabled', $enabled);
}

/**
 * Нужно ли показывать поля подтверждения текущему покупателю.
 *
 * @return bool
 */
function wp_smsn1_ru_checkout_needs_verification()
{
    return !is_user_logged_in() && wp_smsn1_ru_checkout_verification_enabled();
}

/**
 * Поле «Код из СМС» в блоке оплаты.
 *
 * @param array $fields Поля checkout.
 * @return array
 */
function wp_smsn1_ru_add_checkout_code_field($fields)
{
    if (!wp_smsn1_ru_checkout_needs_verification()) {
        return $fields;
    }

    $fields['billing']['billing_sms_code'] = array(
        'type'              => 'text',
        'label'             => __('Код из СМС', 'wp-smsn1-ru'),
        'placeholder'       => __('5 цифр', 'wp-smsn1-ru'),
        'required'          => true,
        'class'             => array('form-row-wide', 'wp-smsn1-ru-checkout-code'),
        'priority'          => 105,
        'maxlength'         => 5,
        // Кнопка и область уведомлений внутри описания поля: так они рендерятся
        // вместе с полем в любой теме, без зависимости от фильтров оформления.
        'description'       => '<button type="button" id="send-sms-code-checkout" class="button wp-smsn1-ru-checkout-send">'
            . esc_html__('Отправить код', 'wp-smsn1-ru')
            . '</button>'
            . '<div id="wp-smsn1-ru-message" class="wp-smsn1-ru-message" role="status" aria-live="polite"></div>',
        'custom_attributes' => array(
            'inputmode'    => 'numeric',
            'autocomplete' => 'one-time-code',
        ),
    );

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'wp_smsn1_ru_add_checkout_code_field');

/**
 * Подсказка над полем кода.
 */
function wp_smsn1_ru_checkout_code_hint()
{
    if (!wp_smsn1_ru_checkout_needs_verification()) {
        return;
    }

    echo '<p class="wp-smsn1-ru-checkout-hint">'
        . esc_html__('Подтвердите номер телефона: нажмите «Отправить код» и введите код из СМС.', 'wp-smsn1-ru')
        . '</p>';
}
add_action('woocommerce_review_order_before_submit', 'wp_smsn1_ru_checkout_code_hint', 5);

/**
 * Проверка кода перед созданием заказа.
 */
function wp_smsn1_ru_validate_checkout_sms_code()
{
    if (!wp_smsn1_ru_checkout_needs_verification()) {
        return;
    }

    $phone = isset($_POST['billing_phone'])
        ? sanitize_my_phone_for_field(wp_unslash($_POST['billing_phone']))
        : '';
    $code = isset($_POST['billing_sms_code'])
        ? sanitize_text_field(wp_unslash($_POST['billing_sms_code']))
        : '';

    if ($phone === '' || $phone === 'not_russian_num') {
        wc_add_notice(__('Укажите корректный номер телефона в формате +7XXXXXXXXXX.', 'wp-smsn1-ru'), 'error');
        return;
    }

    if ($code === '') {
        wc_add_notice(__('Введите код из СМС, чтобы подтвердить номер телефона.', 'wp-smsn1-ru'), 'error');
        return;
    }

    // consume = false: код остаётся действительным, если заказ не удалось оформить
    // с первой попытки (ошибка оплаты, повторная валидация).
    $checked = wp_smsn1_ru_otp_check($phone, $code, false);

    if ($checked !== true) {
        wc_add_notice($checked, 'error');
        return;
    }

    if (wp_smsn1_ru_get_user_by_phone($phone)) {
        $login_url = wp_smsn1_ru_page_url('wp_smsn1_ru_use_custom_login_form_url', 'login-by-wp-smsn1-ru');

        wc_add_notice(
            sprintf(
                /* translators: %s: ссылка на страницу входа */
                __('Этот номер уже привязан к аккаунту. <a href="%s">Войдите в систему</a> — так вы сможете видеть историю заказов.', 'wp-smsn1-ru'),
                esc_url($login_url)
            ),
            'error'
        );
    }
}
add_action('woocommerce_checkout_process', 'wp_smsn1_ru_validate_checkout_sms_code');

/**
 * Сохраняет телефон в заказе в едином формате +7XXXXXXXXXX.
 *
 * @param WC_Order $order Заказ.
 * @param array    $data  Данные checkout.
 */
function wp_smsn1_ru_normalize_order_phone($order, $data)
{
    $phone = isset($_POST['billing_phone'])
        ? sanitize_my_phone_for_field(wp_unslash($_POST['billing_phone']))
        : '';

    if ($phone !== '' && $phone !== 'not_russian_num') {
        $order->set_billing_phone($phone);

        if ($order->get_shipping_phone() !== '') {
            $order->set_shipping_phone($phone);
        }
    }
}
add_action('woocommerce_checkout_create_order', 'wp_smsn1_ru_normalize_order_phone', 20, 2);

/**
 * Шорткод для ручного вывода блока подтверждения (только для гостей).
 *
 * @return string
 */
function wp_smsn1_ru_phone_verification_shortcode()
{
    if (!wp_smsn1_ru_checkout_needs_verification()) {
        return '';
    }

    ob_start();
    ?>
    <div id="wp_smsn1_ru_custom_phone_field">
        <h3><?php esc_html_e('Подтверждение номера телефона', 'wp-smsn1-ru'); ?></h3>
        <p class="form-row form-row-wide">
            <label for="custom_phone"><?php esc_html_e('Номер телефона', 'wp-smsn1-ru'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="custom_phone" id="custom_phone" autocomplete="tel" placeholder="+7 999 123-45-67" />
        </p>
        <p class="form-row form-row-wide wp-smsn1-ru-field--code">
            <label for="sms_code"><?php esc_html_e('Код из СМС', 'wp-smsn1-ru'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="sms_code" id="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" placeholder="<?php esc_attr_e('5 цифр', 'wp-smsn1-ru'); ?>" />
        </p>
        <button type="button" id="send-sms-code-checkout" class="button"><?php esc_html_e('Отправить код', 'wp-smsn1-ru'); ?></button>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wp_smsn1_ru_phone_verification', 'wp_smsn1_ru_phone_verification_shortcode');

/**
 * AJAX: существует ли пользователь с таким номером.
 */
function wp_smsn1_ru_check_user_by_phone()
{
    check_ajax_referer('wp_smsn1_ru_nonce', 'nonce');

    $phone_number = isset($_POST['phone_number'])
        ? sanitize_my_phone_for_field(wp_unslash($_POST['phone_number']))
        : '';

    if ($phone_number === '' || $phone_number === 'not_russian_num') {
        wp_send_json_error(__('Укажите корректный номер телефона.', 'wp-smsn1-ru'));
    }

    $user_id = wp_smsn1_ru_get_user_by_phone($phone_number);

    if ($user_id) {
        wp_send_json_success(
            array(
                'exists'  => true,
                'res_url' => wp_smsn1_ru_page_url('wp_smsn1_ru_use_custom_login_form_url', 'login-by-wp-smsn1-ru'),
            )
        );
    }

    wp_send_json_success(array('exists' => false));
}
add_action('wp_ajax_check_user_by_phone', 'wp_smsn1_ru_check_user_by_phone');
add_action('wp_ajax_nopriv_check_user_by_phone', 'wp_smsn1_ru_check_user_by_phone');
