<?php
/**
 * Экран, когда вход по коду временно недоступен (нет баланса или нет связи с сервисом СМС).
 *
 * СМС не работают, но вход по логину/паролю и через VK/Telegram остаются доступны.
 * Шаблон можно переопределить в теме: wp-smsn1-ru/error-form.php
 */

$wp_smsn1_ru_reason = isset($wp_smsn1_ru_sms_unavailable) ? $wp_smsn1_ru_sms_unavailable : 'balance';

$wp_smsn1_ru_intro = ($wp_smsn1_ru_reason === 'connection')
    ? 'Сервис отправки СМС сейчас недоступен, поэтому вход по коду временно не работает. Остальные способы входа ниже.'
    : 'На балансе сервиса СМС недостаточно средств, поэтому вход по коду временно не работает. Остальные способы входа ниже.';
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title">Вход по коду временно недоступен</h2>
    <p class="wp-smsn1-ru-form__intro"><?php echo esc_html($wp_smsn1_ru_intro); ?></p>

    <p class="wp-smsn1-ru-actions">
        <a class="wp-smsn1-ru-button wp-smsn1-ru-button--primary" href="<?php echo esc_url(wp_login_url()); ?>">Войти по логину и паролю</a>
    </p>

    <?php if (function_exists('wp_smsn1_ru_oauth_buttons_html')) : ?>
        <?php $oauth_buttons = wp_smsn1_ru_oauth_buttons_html(); ?>
        <?php if ($oauth_buttons !== '') : ?>
    <div class="wp-smsn1-ru-divider" aria-hidden="true">
        <span class="wp-smsn1-ru-divider__line"></span>
        <span class="wp-smsn1-ru-divider__text">или</span>
        <span class="wp-smsn1-ru-divider__line"></span>
    </div>
    <?php echo $oauth_buttons; ?>
        <?php endif; ?>
    <?php endif; ?>

    <p class="wp-smsn1-ru-links">
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Забыли пароль?</a>
    </p>
</div>
