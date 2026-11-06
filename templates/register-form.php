<div class="wp-smsn1-ru-form">
    <h2>Регистрация</h2>
    <form id="wp-smsn1-ru-register-form">
        <label for="reg_email">E-mail(укажите корректный адрес электронной почты, он потребуется для восстановления пароля в случае возникновения проблем с отправкой СМС):</label>
        <input type="email" id="reg_email" name="reg_email" placeholder="myemail@domain.test" required>
        <label for="phone_number">Номер телефона(если номер уже зарегестрирован, Вы будете автоматически авторизованы):</label>
        <input type="text" id="phone_number" name="phone_number" placeholder="+79991234567" required>            
        <button type="button" id="send-sms-code">Отправить код</button>       
        <label for="sms_code">Код из СМС:</label>
        <input type="text" id="sms_code" name="sms_code">
		<input type="hidden" id="type_of_form" name="type_of_form" value="registration" />
		<input type="hidden" id="wp_smsn1_ru_use_custom_login_redirect_url" name="wp_smsn1_ru_use_custom_login_redirect_url" value="<?= get_option('wp_smsn1_ru_use_custom_login_redirect_url');  ?>" />
        <button type="submit">Войти/Зарегистрироваться</button>
    </form>
    <div id="wp-smsn1-ru-message"></div>
	<p class="privacy-policy-url" ><a href="/privacy-policy/" target="_blank">Продолжая, Вы соглашаетесь с политикой обработки персональных данных.</a></p>
</div>