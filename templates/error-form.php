<?php
/**
 * Заглушка, если плагин временно не может отправить СМС (нет баланса или нет связи с сервисом).
 * Шаблон можно переопределить в теме: wp-smsn1-ru/error-form.php
 */
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title">Форма входа временно недоступна</h2>
    <p class="wp-smsn1-ru-form__intro">
        Не удалось отправить код подтверждения. Попробуйте стандартную форму входа WordPress
        или обратитесь к администратору сайта.
    </p>
    <p class="wp-smsn1-ru-actions">
        <a class="wp-smsn1-ru-button wp-smsn1-ru-button--primary" href="<?php echo esc_url(wp_login_url()); ?>">Стандартный вход</a>
    </p>
</div>
