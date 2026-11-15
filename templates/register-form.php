<?php
/**
 * Форма регистрации по номеру телефона.
 * Шаблон можно переопределить в теме: wp-smsn1-ru/register-form.php
 */
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title"><?php esc_html_e('Регистрация', 'wp-smsn1-ru'); ?></h2>
    <p class="wp-smsn1-ru-form__intro"><?php esc_html_e('Если номер уже зарегистрирован, вы просто войдёте в аккаунт.', 'wp-smsn1-ru'); ?></p>

    <form id="wp-smsn1-ru-register-form" class="wp-smsn1-ru-form__body">
        <div class="wp-smsn1-ru-field">
            <label for="reg_email"><?php esc_html_e('E-mail', 'wp-smsn1-ru'); ?></label>
            <input type="email" id="reg_email" name="reg_email" placeholder="<?php esc_attr_e('myemail@domain.ru', 'wp-smsn1-ru'); ?>" autocomplete="email" required>
            <p class="wp-smsn1-ru-hint"><?php esc_html_e('Нужен для восстановления доступа, если возникнут проблемы с СМС.', 'wp-smsn1-ru'); ?></p>
        </div>

        <div class="wp-smsn1-ru-field">
            <label for="phone_number"><?php esc_html_e('Номер телефона', 'wp-smsn1-ru'); ?></label>
            <input type="tel" id="phone_number" name="phone_number" placeholder="<?php esc_attr_e('+7 999 123-45-67', 'wp-smsn1-ru'); ?>" autocomplete="tel" inputmode="tel" required>
        </div>

        <?php if (wp_smsn1_ru_auth_mode() === 'password_otp') : ?>
        <div class="wp-smsn1-ru-field">
            <label for="user_password"><?php esc_html_e('Пароль', 'wp-smsn1-ru'); ?></label>
            <input type="password" id="user_password" name="user_password" autocomplete="new-password" placeholder="<?php esc_attr_e('Не короче 8 символов', 'wp-smsn1-ru'); ?>">
        </div>
        <div class="wp-smsn1-ru-field">
            <label for="user_password_confirm"><?php esc_html_e('Повторите пароль', 'wp-smsn1-ru'); ?></label>
            <input type="password" id="user_password_confirm" name="user_password_confirm" autocomplete="new-password" placeholder="<?php esc_attr_e('Ещё раз', 'wp-smsn1-ru'); ?>">
        </div>
        <?php endif; ?>

        <div class="wp-smsn1-ru-field wp-smsn1-ru-field--code">
            <label for="sms_code"><?php esc_html_e('Код из СМС', 'wp-smsn1-ru'); ?></label>
            <input type="text" id="sms_code" name="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" placeholder="<?php esc_attr_e('5 цифр', 'wp-smsn1-ru'); ?>">
        </div>

        <div class="wp-smsn1-ru-actions">
            <button type="button" id="send-sms-code" class="wp-smsn1-ru-button wp-smsn1-ru-button--secondary"><?php esc_html_e('Отправить код', 'wp-smsn1-ru'); ?></button>
            <button type="submit" class="wp-smsn1-ru-button wp-smsn1-ru-button--primary"><?php esc_html_e('Зарегистрироваться', 'wp-smsn1-ru'); ?></button>
        </div>

        <p class="wp-smsn1-ru-links">
            <a href="<?php echo esc_url(wp_smsn1_ru_page_url('wp_smsn1_ru_use_custom_login_form_url', 'login-by-wp-smsn1-ru')); ?>"><?php esc_html_e('У меня уже есть аккаунт', 'wp-smsn1-ru'); ?></a>
        </p>

        <input type="hidden" id="type_of_form" name="type_of_form" value="registration">
        <input type="hidden" id="wp_smsn1_ru_use_custom_login_redirect_url" name="wp_smsn1_ru_use_custom_login_redirect_url" value="<?php echo esc_attr(wp_smsn1_ru_page_url('wp_smsn1_ru_use_custom_login_redirect_url', 'wp-admin')); ?>">
    </form>

    <div id="wp-smsn1-ru-message" class="wp-smsn1-ru-message" role="status" aria-live="polite"></div>

    <?php $oauth_buttons = function_exists('wp_smsn1_ru_oauth_buttons_html') ? wp_smsn1_ru_oauth_buttons_html() : ''; ?>
    <?php if ($oauth_buttons !== '') : ?>
    <div class="wp-smsn1-ru-divider" aria-hidden="true">
        <span class="wp-smsn1-ru-divider__line"></span>
        <span class="wp-smsn1-ru-divider__text"><?php esc_html_e('или', 'wp-smsn1-ru'); ?></span>
        <span class="wp-smsn1-ru-divider__line"></span>
    </div>
    <?php echo $oauth_buttons; ?>
    <?php endif; ?>

    <p class="wp-smsn1-ru-privacy">
        <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Продолжая, вы соглашаетесь с политикой обработки персональных данных.', 'wp-smsn1-ru'); ?></a>
    </p>
</div>
