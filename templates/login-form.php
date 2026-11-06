<div class="wp-smsn1-ru-form">
    <h2>Войти на сайт</h2>
    <form id="wp-smsn1-ru-login-form">
        <label for="phone_number">Номер телефона:</label>
        <input type="text" id="phone_number" name="phone_number"  placeholder="+79991234567" required>
        <button type="button" id="send-sms-code">Отправить код</button>        
        <label for="sms_code">Код из СМС:</label>
        <input type="text" id="sms_code" name="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 цифр">
		<input type="hidden" id="type_of_form" name="type_of_form" value="login" />
		<input type="hidden" id="wp_smsn1_ru_use_custom_login_redirect_url" name="wp_smsn1_ru_use_custom_login_redirect_url" value="<?= get_option('wp_smsn1_ru_use_custom_login_redirect_url');  ?>" />
        <button type="submit">Войти</button> <button type="button" class="grey" onclick="location.href='<?= get_option('wp_smsn1_ru_use_custom_register_form_url');  ?>';" >Зарегестрироваться</button>
    </form>
    <div id="wp-smsn1-ru-message"></div>
	<p class="privacy-policy-url" ><a href="/privacy-policy/" target="_blank">Продолжая, Вы соглашаетесь с политикой обработки персональных данных.</a></p>
</div>