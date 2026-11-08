<?php
/**
 * Тесты проверки подписи Telegram Login Widget (без WordPress).
 *
 * Запуск: php tests/telegram-test.php
 */

define('ABSPATH', __DIR__ . '/');

class WP_Error
{
    public $code;
    public $message;

    public function __construct($code = '', $message = '')
    {
        $this->code    = $code;
        $this->message = $message;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

function apply_filters($tag, $value)
{
    return $value;
}

function get_option($name, $default = false)
{
    $options = array(
        'wp_smsn1_ru_telegram_enabled' => 'yes',
        'wp_smsn1_ru_telegram_bot'     => 'wp_smsn1_ru_bot',
        'wp_smsn1_ru_telegram_token'   => '9048658102:TEST_TOKEN',
    );

    return isset($options[$name]) ? $options[$name] : $default;
}

require dirname(__DIR__) . '/includes/auth/interface-wp-smsn1-ru-auth-provider-widget.php';
require dirname(__DIR__) . '/includes/auth/class-wp-smsn1-ru-provider-telegram.php';

$GLOBALS['tg_failures'] = 0;
$GLOBALS['tg_tests']    = 0;

function check($condition, $label)
{
    $GLOBALS['tg_tests']++;

    if ($condition) {
        echo "ok   {$label}\n";
        return;
    }

    $GLOBALS['tg_failures']++;
    echo "FAIL {$label}\n";
}

/**
 * Независимая реализация подписи по документации Telegram.
 */
function tg_sign(array $data, $token)
{
    ksort($data);

    $lines = array();
    foreach ($data as $key => $value) {
        $lines[] = $key . '=' . $value;
    }

    return hash_hmac('sha256', implode("\n", $lines), hash('sha256', $token, true));
}

$token    = '9048658102:TEST_TOKEN';
$provider = new WP_SMSN1_RU_Provider_Telegram();

$data = array(
    'id'         => '123456789',
    'first_name' => 'Иван',
    'last_name'  => 'Петров',
    'username'   => 'ivan_petrov',
    'auth_date'  => (string) time(),
);

$request        = $data;
$request['hash'] = tg_sign($data, $token);

$profile = $provider->verify_callback($request);
check(!is_wp_error($profile) && $profile['uid'] === '123456789', 'валидная подпись принимается');
check(!is_wp_error($profile) && $profile['name'] === 'Иван Петров', 'имя собирается из профиля');

$tampered         = $request;
$tampered['id']   = '999999999';
check(is_wp_error($provider->verify_callback($tampered)), 'подмена id отклоняется');

$no_hash = $data;
check(is_wp_error($provider->verify_callback($no_hash)), 'запрос без подписи отклоняется');

$expired            = $data;
$expired['auth_date'] = (string) (time() - 3 * 3600);
$expired['hash']    = tg_sign($expired, $token);
check(is_wp_error($provider->verify_callback($expired)), 'устаревший auth_date отклоняется');

$wrong_token     = $data;
$wrong_token['hash'] = tg_sign($data, '9048658102:OTHER_TOKEN');
check(is_wp_error($provider->verify_callback($wrong_token)), 'подпись чужим токеном отклоняется');

echo "\nПроверок: " . $GLOBALS['tg_tests'] . ", ошибок: " . $GLOBALS['tg_failures'] . "\n";

exit($GLOBALS['tg_failures'] === 0 ? 0 : 1);
