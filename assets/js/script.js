jQuery(document).ready(function($) {
    let countdown = 0; // Переменная для хранения оставшегося времени
    let countdownInterval = null; // Интервал для обновления таймера
	function sanitizeMyPhoneForField(phoneNumber) {
		// Удаляем все нечисловые символы
		let cleaned = phoneNumber.replace(/[^0-9]+/g, '');

		// Проверяем длину номера
		if (cleaned.length === 11) {
			// Если номер из 11 цифр, убираем первую цифру (предполагаем, что это 8)
			cleaned = cleaned.substring(1);
		}

		// Если номер из 10 цифр, добавляем префикс +7
		if (cleaned.length === 10) {
			cleaned = "+7" + cleaned;
		} else {
			// Если номер не соответствует формату, возвращаем "not_russian_num"
			cleaned = "not_russian_num";
		}

		return cleaned;
	}
    // Функция для обновления текста кнопки
    function updateButtonText(seconds) {
        $('#send-sms-code').text(`Отправить код (${seconds} сек)`);
		$('#send-sms-code-checkout').text(`Отправить код (${seconds} сек)`);
    }

    // Функция для запуска таймера
    function startCountdown() {
        countdown = 60; // Устанавливаем начальное значение таймера
        $('#send-sms-code').prop('disabled', true); // Блокируем кнопку
		$('#send-sms-code').css({ "background-color" : "#bbb"});
        $('#send-sms-code-checkout').prop('disabled', true); // Блокируем кнопку
		$('#send-sms-code-checkout').css({ "background-color" : "#bbb"});		
        // Обновляем текст кнопки каждую секунду
        countdownInterval = setInterval(function() {
            countdown--;
            updateButtonText(countdown);

            // Если время вышло, восстанавливаем кнопку
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                $('#send-sms-code').prop('disabled', false).text('Отправить код');
				$('#send-sms-code').css({ "background-color" : "#bbb"});
                $('#send-sms-code-checkout').prop('disabled', false).text('Отправить код');
				$('#send-sms-code-checkout').css({ "background-color" : "#bbb"});
            }
        }, 1000);
    }
	
    // Отправка СМС
    $('#send-sms-code').on('click', function() {
        var phoneNumber = $('#phone_number').val();
		var type_of_form = $('#type_of_form').val();
        
        // Проверяем, что таймер не активен
        if (countdown > 0) {
            return;
        }										   
        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {				
                action: 'send_sms_code',
                phone_number: phoneNumber,
				type_of_form: type_of_form,
                nonce: wp_smsn1_ru_ajax.nonce	
            },
            success: function(response) {	
				if (response.success) {			
					$('#wp-smsn1-ru-message').html(response.data);
					$('label[for=sms_code], #sms_code').css({ "display":"block", "font-size":"14px"});
					$('.wp-smsn1-ru-form button[type="submit"]').css({ "background-color": "#0073aa"});
					startCountdown();   // Запускаем таймер 
				}
				else {
					$('#wp-smsn1-ru-message').html(response.data);
				}
            },
            error: function(xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Ошибка при отправке запроса.';
                $('#wp-smsn1-ru-message').html(msg);
            }
        });
    });
    
    // Проверка кода и авторизация
    $('#wp-smsn1-ru-register-form, #wp-smsn1-ru-login-form').on('submit', function(e) {
        e.preventDefault(); // Отменяем стандартное поведение формы
        if($('#sms_code').css('display') === 'none') {	//Проверяем код ввобще введен
			$('#wp-smsn1-ru-message').html('Получите и введите код из СМС.');
			return;
		}	
        var phoneNumber = $('#phone_number').val();
        var smsCode = $('#sms_code').val();
		var reg_email = $('#reg_email').val();
		var type_of_form = $('#type_of_form').val();
		var wp_smsn1_ru_use_custom_login_redirect_url = $('#wp_smsn1_ru_use_custom_login_redirect_url').val();
        
        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_sms_code',
                phone_number: phoneNumber,
				reg_email: reg_email,
				type_of_form: type_of_form,
                sms_code: smsCode,
                nonce: wp_smsn1_ru_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Успешная авторизация
                    $('#wp-smsn1-ru-message').html(response.data);
                    // Перенаправление на другую страницу (опционально)
                    window.location.href = '/' + wp_smsn1_ru_use_custom_login_redirect_url; // Замените на нужный URL
                } else {
                    // Ошибка авторизации
                    $('#wp-smsn1-ru-message').html(response.data);
                }
            },
            error: function(xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Ошибка при отправке запроса.';
                $('#wp-smsn1-ru-message').html(msg);
            }
        });
    });

	jQuery(document).ready(function($) {
		// Отправка SMS-кода на странице оформления заказа
		$('#send-sms-code-checkout').on('click', function() {
			var phoneNumber = $('#custom_phone').val(); // Используем кастомное поле телефона
			phoneNumber = sanitizeMyPhoneForField(phoneNumber);
			$('#billing_phone').val(phoneNumber);
			$('#shipping_phone').val(phoneNumber);
			// Проверяем, что таймер не активен
			if (countdown > 0) {
				return;
			}	        
			$.ajax({
				url: wp_smsn1_ru_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'send_sms_code',
					phone_number: phoneNumber,
					type_of_form: 'checkout',
					nonce: wp_smsn1_ru_ajax.nonce
				},
			   success: function(response) {	
					if (response.success) {			
						$('#wp-smsn1-ru-message').html(response.data);
						$('label[for=sms_code], #sms_code').css({ "display": "block"});					
						startCountdown();   // Запускаем таймер 
					}
					else {
						$('#wp-smsn1-ru-message').html(response.data);
					}
				},
				error: function(xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Ошибка при отправке запроса.';
                $('#wp-smsn1-ru-message').html(msg);
            }
			});
		});
	});

	jQuery(document).ready(function($) {
    // Обработка изменения состояния чекбокса "Создать аккаунт"
    $('#createaccount').on('change', function() {
        if ($(this).is(':checked')) {
            var phoneNumber = $('#custom_phone').val(); // Получаем значение кастомного поля телефона

            // Проверяем, существует ли пользователь с таким номером телефона
            $.ajax({
                url: wp_smsn1_ru_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_user_by_phone',
                    phone_number: phoneNumber,
                    nonce: wp_smsn1_ru_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.exists) {
                            // Пользователь существует, выводим сообщение
                            $('#wp-smsn1-ru-message').html('Пользователь с таким номером телефона уже существует. <a href="'+response.data.res_url+'">Войдите в систему</a>.');
                            $('#createaccount').prop('checked', false); // Снимаем выбор чекбокса
                        }
                    } else {
                        alert('Ошибка при проверке номера телефона.');
                    }
                },
                error: function(xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Ошибка при отправке запроса.';
                alert(msg);
            }
            });
        }
    });
});

});