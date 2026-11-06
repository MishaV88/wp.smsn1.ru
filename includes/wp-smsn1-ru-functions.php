<?php
//Этот файл будет содержать код для функций необходимых для работы 
// Запрет прямого доступа к файлу
//echo wp_smsn1_ru_send_code_function(9857679792, 1234);
if (!defined('ABSPATH')) {
    exit;
}
//обработка подписи от провайдера СМС
function wp_smsn1_ru_Signature_md5( $params, $api_key )
{
    ksort( $params );
    reset( $params );
 
    return md5( implode( $params ) . $api_key );
}
/**
 * Отправляет SMS на указанный номер телефона.
 *
 * @param string $phone_number Номер телефона в формате 79991234567.
 * @param string $sms_text Текст SMS-сообщения.
 * @return bool|string Возвращает true в случае успеха или текст ошибки.
 */
function wp_smsn1_ru_send_function($phone_number, $sms_text) {
	if(iconv_strlen($phone_number) == 12) //Проверяем может с +
		$phone_number = mb_substr( $phone_number, 1);
    // Здесь мы должны реализовать отправку СМС через API провайдера.
	$login_for_smsn1 = (get_option('wp_smsn1_ru_login'));
	$api_key = (get_option('wp_smsn1_ru_api_key'));
	$sender = get_option('wp_smsn1_ru_sender');

	// Проверка: отправитель выбран
	if (empty($sender) || $sender === 'Не выбран!') {
		return 'Не выбран отправитель SMS. Зайдите в настройки плагина WP.SMSn1.RU и выберите отправителя.';
	}

	$wp_smsn1_ru_timestamp = file_get_contents("http://direct.docs-group.ru/get/timestamp.php");

	$params = array(
    'timestamp' => $wp_smsn1_ru_timestamp,
    'login'     => $login_for_smsn1,
    'phone'     => $phone_number,
    'text'      => $sms_text,
	'sender'    => $sender
	);

	$signature = wp_smsn1_ru_Signature_md5($params, $api_key);

	$url = "http://direct.docs-group.ru/get/send.php?login=" . urlencode($login_for_smsn1)
     . "&signature=" . urlencode($signature)
     . "&phone=" . urlencode($phone_number)
     . "&text=" . urlencode($sms_text)
     . "&sender=" . urlencode($sender)
     . "&timestamp=" . urlencode($wp_smsn1_ru_timestamp);
	// Очистка от возможных HTML-сущностей
	$url = html_entity_decode($url);
	$inputJSON = file_get_contents($url);
	//return $inputJSON;
	$input = json_decode($inputJSON, TRUE); //convert JSON into array
	if(!empty($input["error"]))
		return $input["error"];
	else
		return true;

}
// функция отправки СМС с кодом
function wp_smsn1_ru_send_code_function($phone_number, $sms_code) {
	if(iconv_strlen($phone_number) == 12) //Проверяем может с +
		$phone_number = mb_substr( $phone_number, 1);
    // Здесь мы должны реализовать отправку СМС через API провайдера.
	$login_for_smsn1 = (get_option('wp_smsn1_ru_login'));
	$api_key = (get_option('wp_smsn1_ru_api_key'));
	$sender = get_option('wp_smsn1_ru_sender');

	// Проверка: отправитель выбран
	if (empty($sender) || $sender === 'Не выбран!') {
		return 'Не выбран отправитель SMS. Зайдите в настройки плагина WP.SMSn1.RU и выберите отправителя.';
	}

	$wp_smsn1_ru_timestamp = file_get_contents("http://direct.docs-group.ru/get/timestamp.php");

	$text = 'Код ' . $sms_code . ', для сайта ' . $_SERVER['HTTP_HOST'];
	$params = array(
    'timestamp' => $wp_smsn1_ru_timestamp,
    'login'     => $login_for_smsn1,
    'phone'     => $phone_number,
    'text'      => $text,
	'sender'    => $sender
	);

	$signature = wp_smsn1_ru_Signature_md5($params, $api_key);
	$url = "http://direct.docs-group.ru/get/send.php?login=" . urlencode($login_for_smsn1)
     . "&signature=" . urlencode($signature)
     . "&phone=" . urlencode($phone_number)
     . "&text=" . urlencode($text)
     . "&sender=" . urlencode($sender)
     . "&timestamp=" . urlencode($wp_smsn1_ru_timestamp);
	// Очистка от возможных HTML-сущностей
	$url = html_entity_decode($url);
	$inputJSON = file_get_contents($url);
	//return $inputJSON;
	$input = json_decode($inputJSON, TRUE); //convert JSON into array
	if(!empty($input["error"]))
		return $input["error"];
	else
		return true;

}
/**
 * Получает текущий баланс аккаунта в сервисе smsn1.ru( личный кабинет direct.docs-group.ru).
 *
 * @return float|string Возвращает баланс или сообщение об ошибке.
 */
function wp_smsn1_ru_get_balance() {
	$input = get_transient('wp_smsn1_ru_get_balance_temp');
	if($input == false) {
		$login_for_smsn1 = (get_option('wp_smsn1_ru_login'));
		$api_key = (get_option('wp_smsn1_ru_api_key'));
		
		$wp_smsn1_ru_timestamp = file_get_contents("http://direct.docs-group.ru/get/timestamp.php");
		$params = array(
		'timestamp' => $wp_smsn1_ru_timestamp,
		'login'     => $login_for_smsn1
		);
		
		$signature = wp_smsn1_ru_Signature_md5($params, $api_key);
		$inputJSON = file_get_contents("http://direct.docs-group.ru/get/balance.php?login=".$login_for_smsn1."&signature=".$signature."&timestamp=" . $wp_smsn1_ru_timestamp);
		$input = json_decode($inputJSON, TRUE); //convert JSON into array
		
		if(!isset($input['error']) && (!empty($input['money']) || $input['money'] == 0)) 	
			$input = $input['money']; 			
		else 
			$input = "Проблема с соединением, ошибка №" . $input['error'];
		
		set_transient('wp_smsn1_ru_get_balance_temp', $input, 2 * MINUTE_IN_SECONDS );	
		
	} 
	return $input;
}
/**
 * Получает список одобренных отправителей SMS.
 *
 * @return array Ассоциативный массив отправителей.
 */
function wp_smsn1_ru_get_senders() {
	$input = get_transient('wp_smsn1_ru_get_senders_temp'); 
	if($input == false){
		$login_for_smsn1 = (get_option('wp_smsn1_ru_login'));
		$api_key = (get_option('wp_smsn1_ru_api_key'));
		
		$wp_smsn1_ru_timestamp = file_get_contents("http://direct.docs-group.ru/get/timestamp.php");
		$params = array(
		'timestamp' => $wp_smsn1_ru_timestamp,
		'login'     => $login_for_smsn1
		);
		
		$signature = wp_smsn1_ru_Signature_md5($params, $api_key);
		$inputJSON = file_get_contents("http://direct.docs-group.ru/get/senders.php?login=".$login_for_smsn1."&signature=".$signature."&timestamp=" . $wp_smsn1_ru_timestamp);
		$input = json_decode($inputJSON, TRUE); //convert JSON into array
		if(!empty($input["error"]))
			$input = array("Ошибка №" . $input["error"] => 0); // Заглушка для примера
		else {
			foreach ($input as $key => $val) {
				if ($val == 'completed')	{
					$new_input_array[$key] = $val; //Записывем в новый массив ключ старого как значение;
				}
			}
			$input = $new_input_array;
		}
		set_transient('wp_smsn1_ru_get_senders_temp', $input, 4 * MINUTE_IN_SECONDS );
	}
	return $input;
}
// Регистрируем функции как публичные
function wp_smsn1_ru_register_public_functions() {
    // Делаем функции доступными через хуки
    add_action('wp_smsn1_ru_send_function', 'wp_smsn1_ru_send_function', 10, 2);
    add_action('wp_smsn1_ru_get_balance', 'wp_smsn1_ru_get_balance');
    add_action('wp_smsn1_ru_get_senders', 'wp_smsn1_ru_get_senders');
}
add_action('plugins_loaded', 'wp_smsn1_ru_register_public_functions');
// Обертки для вызова функций
function wp_smsn1_ru_do_send_function($phone_number, $sms_text) {
    do_action('wp_smsn1_ru_send_function', $phone_number, $sms_text);
}

function wp_smsn1_ru_do_get_balance() {
    return do_action('wp_smsn1_ru_get_balance');
}

function wp_smsn1_ru_do_get_senders() {
    return do_action('wp_smsn1_ru_get_senders');
}