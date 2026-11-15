<?php
/**
 * Экран, когда вход по коду временно недоступен (нет баланса или нет связи с сервисом СМС).
 *
 * СМС не работают, но вход по логину/паролю и через VK/Telegram остаются доступны.
 * Шаблон можно переопределить в теме: wp-smsn1-ru/error-form.php
 */

$wp_smsn1_ru_reason = isset($wp_smsn1_ru_sms_unavailable) ? $wp_smsn1_ru_sms_unavailable : 'balance';

$wp_smsn1_ru_intro = ($wp_smsn1_ru_reason === 'connection')
    ? __('Сервис отправки СМС сейчас недоступен, поэтому вход по коду временно не работает. Остальные способы входа ниже.', 'wp-smsn1-ru')
    : __('На балансе сервиса СМС недостаточно средств, поэтому вход по коду временно не работает. Остальные способы входа ниже.', 'wp-smsn1-ru');
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title"><?php esc_html_e('Вход по коду временно недоступен', 'wp-smsn1-ru'); ?></h2>
    <p class="wp-smsn1-ru-form__intro"><?php echo esc_html($wp_smsn1_ru_intro); ?></p>

    <p class="wp-smsn1-ru-actions">
        <a class="wp-smsn1-ru-button wp-smsn1-ru-button--primary" href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Войти по логину и паролю', 'wp-smsn1-ru'); ?></a>
    </p>

    <?php if (function_exists('wp_smsn1_ru_oauth_buttons_html')) : ?>
        <?php $oauth_buttons = wp_smsn1_ru_oauth_buttons_html(); ?>
        <?php if ($oauth_buttons !== '') : ?>
    <div class="wp-smsn1-ru-divider" aria-hidden="true">
        <span class="wp-smsn1-ru-divider__line"></span>
        <span class="wp-smsn1-ru-divider__text"><?php esc_html_e('или', 'wp-smsn1-ru'); ?></span>
        <span class="wp-smsn1-ru-divider__line"></span>
    </div>
    <?php echo $oauth_buttons; ?>
        <?php endif; ?>
    <?php endif; ?>

    <p class="wp-smsn1-ru-links">
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Забыли пароль?', 'wp-smsn1-ru'); ?></a>
    </p>
</div>
