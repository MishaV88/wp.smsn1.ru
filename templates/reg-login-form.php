<?php
/**
 * Форма «Вход или регистрация» для быстрого режима (без e-mail).
 * Шаблон можно переопределить в теме: wp-smsn1-ru/reg-login-form.php
 */
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title">Вход или регистрация</h2>
    <p class="wp-smsn1-ru-form__intro">Если аккаунта ещё нет, он будет создан автоматически.</p>

    <form id="wp-smsn1-ru-login-form" class="wp-smsn1-ru-form__body">
        <div class="wp-smsn1-ru-field">
            <label for="phone_number">Номер телефона</label>
            <input type="tel" id="phone_number" name="phone_number" placeholder="+7 999 123-45-67" autocomplete="tel" inputmode="tel" required>
        </div>

        <?php if (wp_smsn1_ru_auth_mode() === 'password_otp') : ?>
        <div class="wp-smsn1-ru-field">
            <label for="user_password">Пароль</label>
            <input type="password" id="user_password" name="user_password" autocomplete="current-password" placeholder="Если пароля ещё нет — придумайте его">
        </div>
        <?php endif; ?>

        <div class="wp-smsn1-ru-field wp-smsn1-ru-field--code">
            <label for="sms_code">Код из СМС</label>
            <input type="text" id="sms_code" name="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" placeholder="5 цифр">
        </div>

        <div class="wp-smsn1-ru-actions">
            <button type="button" id="send-sms-code" class="wp-smsn1-ru-button wp-smsn1-ru-button--secondary">Отправить код</button>
            <button type="submit" class="wp-smsn1-ru-button wp-smsn1-ru-button--primary">Войти</button>
        </div>

        <input type="hidden" id="type_of_form" name="type_of_form" value="reg-login">
        <input type="hidden" id="wp_smsn1_ru_use_custom_login_redirect_url" name="wp_smsn1_ru_use_custom_login_redirect_url" value="<?php echo esc_attr(wp_smsn1_ru_page_url('wp_smsn1_ru_use_custom_login_redirect_url', 'wp-admin')); ?>">
    </form>

    <div id="wp-smsn1-ru-message" class="wp-smsn1-ru-message" role="status" aria-live="polite"></div>

    <?php $oauth_buttons = function_exists('wp_smsn1_ru_oauth_buttons_html') ? wp_smsn1_ru_oauth_buttons_html() : ''; ?>
    <?php if ($oauth_buttons !== '') : ?>
    <div class="wp-smsn1-ru-divider"><span>или</span></div>
    <?php echo $oauth_buttons; ?>
    <?php endif; ?>

    <p class="wp-smsn1-ru-privacy">
        <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener">Продолжая, вы соглашаетесь с политикой обработки персональных данных.</a>
    </p>
</div>
