<?php
/**
 * Блок «вы уже авторизованы».
 * Шаблон можно переопределить в теме: wp-smsn1-ru/exit-form.php
 */
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title">Вы уже авторизованы</h2>
    <p class="wp-smsn1-ru-form__intro">Чтобы войти под другим аккаунтом, сначала выйдите.</p>
    <p class="wp-smsn1-ru-actions">
        <a class="wp-smsn1-ru-button wp-smsn1-ru-button--secondary" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Выйти</a>
    </p>
</div>
