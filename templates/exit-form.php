<?php
/**
 * Блок «вы уже авторизованы».
 * Шаблон можно переопределить в теме: wp-smsn1-ru/exit-form.php
 */
?>
<div class="wp-smsn1-ru-form">
    <h2 class="wp-smsn1-ru-form__title"><?php esc_html_e('Вы уже авторизованы', 'wp-smsn1-ru'); ?></h2>
    <p class="wp-smsn1-ru-form__intro"><?php esc_html_e('Чтобы войти под другим аккаунтом, сначала выйдите.', 'wp-smsn1-ru'); ?></p>
    <p class="wp-smsn1-ru-actions">
        <a class="wp-smsn1-ru-button wp-smsn1-ru-button--secondary" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Выйти', 'wp-smsn1-ru'); ?></a>
    </p>
</div>
