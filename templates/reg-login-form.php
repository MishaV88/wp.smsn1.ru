<div class="wp-smsn1-ru-form">
    <h2>Войти на сайт</h2>
	<p>Если у Вас еще нет учетной записи, она будет создана</p>
    <form id="wp-smsn1-ru-login-form">
        <label for="phone_number">Номер телефона:</label>
        <input type="text" id="phone_number" name="phone_number"  placeholder="+79991234567" required>
        <button type="button" id="send-sms-code">Отправить код</button>        
        <label for="sms_code">Код из СМС:</label>
        <input type="text" id="sms_code" name="sms_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" placeholder="5 цифр">
		<input type="hidden" id="type_of_form" name="type_of_form" value="reg-login" />
		<input type="hidden" id="wp_smsn1_ru_use_custom_login_redirect_url" name="wp_smsn1_ru_use_custom_login_redirect_url" value="<?= get_option('wp_smsn1_ru_use_custom_login_redirect_url');  ?>" />
        <button type="submit">Войти/Зарегестрироваться</button>
    </form>
    <div id="wp-smsn1-ru-message"></div>
	<p class="privacy-policy-url" ><a href="/privacy-policy/" target="_blank">Продолжая, Вы соглашаетесь с политикой обработки персональных данных.</a></p>
</div>