<?php
/**
 * Провайдер входа VK ID (OAuth 2.1 + PKCE).
 *
 * Документация: https://id.vk.ru/about/business/go/docs/ru/vkid/latest/vk-id/connection/api-description
 *
 * Порядок: authorize (code_challenge) → VK возвращает code + device_id + state →
 * POST oauth2/auth (code_verifier, device_id, state) → access_token → oauth2/user_info.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SMSN1_RU_Provider_VK implements WP_SMSN1_RU_Auth_Provider
{
    const AUTHORIZE_URL  = 'https://id.vk.ru/authorize';
    const TOKEN_URL      = 'https://id.vk.ru/oauth2/auth';
    const USER_INFO_URL  = 'https://id.vk.ru/oauth2/user_info';
    const API_URL        = 'https://api.vk.com/method/';
    const API_VERSION    = '5.199';

    public function get_id()
    {
        return 'vk';
    }

    public function get_label()
    {
        return 'VK ID';
    }

    public function is_configured()
    {
        return get_option('wp_smsn1_ru_vk_enabled') === 'yes'
            && '' !== (string) get_option('wp_smsn1_ru_vk_client_id');
    }

    /**
     * scope по умолчанию — vkid.personal_info (имя, фамилия, пол, фото, дата рождения).
     * Для e-mail нужно включить доступ в настройках приложения и задать scope=email
     * через фильтр wp_smsn1_ru_vk_scope.
     */
    public function get_scope()
    {
        $scope = trim((string) get_option('wp_smsn1_ru_vk_scope', ''));

        if ($scope === '') {
            $scope = (string) apply_filters('wp_smsn1_ru_vk_scope', '');
        }

        return trim($scope);
    }

    public function get_authorize_url($state, $code_verifier, $redirect_uri)
    {
        $params = array(
            'response_type'         => 'code',
            'client_id'             => get_option('wp_smsn1_ru_vk_client_id'),
            'redirect_uri'          => $redirect_uri,
            'state'                 => $state,
            'code_challenge'        => wp_smsn1_ru_pkce_challenge($code_verifier),
            'code_challenge_method' => 'S256',
        );

        $scope = $this->get_scope();
        if ($scope !== '') {
            $params['scope'] = $scope;
        }

        return add_query_arg($params, self::AUTHORIZE_URL);
    }

    public function exchange_code($code, $code_verifier, $redirect_uri, $device_id, $state)
    {
        $body = array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $code_verifier,
            'redirect_uri'  => $redirect_uri,
            'client_id'     => get_option('wp_smsn1_ru_vk_client_id'),
            'device_id'     => $device_id,
            'state'         => $state,
        );

        // Для конфиденциальных приложений вместо client_secret используется service_token,
        // но у нас публичное приложение с PKCE — секрет не нужен.
        $secret = (string) get_option('wp_smsn1_ru_vk_client_secret');
        if ($secret !== '') {
            $body['client_secret'] = $secret;
        }

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 15,
                'body'    => $body,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['access_token'])) {
            $error = 'VK ID не вернул access_token';

            if (is_array($data)) {
                if (!empty($data['error_description'])) {
                    $error = $data['error_description'];
                } elseif (!empty($data['error'])) {
                    $error = $data['error'];
                }
            }

            return new WP_Error('wp_smsn1_ru_vk_token', $error);
        }

        return $data;
    }

    public function get_profile($token)
    {
        $user_id = isset($token['user_id']) ? (string) $token['user_id'] : '';

        if ($user_id === '') {
            return new WP_Error('wp_smsn1_ru_vk_uid', 'VK ID не вернул идентификатор пользователя');
        }

        $profile = array(
            'uid'   => $user_id,
            'email' => '',
            'phone' => '',
            'name'  => '',
        );

        $profile = $this->fill_from_user_info($profile, $token);

        if ($profile['name'] === '' || $profile['email'] === '') {
            $profile = $this->fill_from_api($profile, $token);
        }

        return $profile;
    }

    /**
     * id.vk.ru/oauth2/user_info — рекомендованный способ получить данные пользователя.
     */
    private function fill_from_user_info($profile, $token)
    {
        $response = wp_remote_post(
            self::USER_INFO_URL,
            array(
                'timeout' => 15,
                'body'    => array(
                    'client_id'    => get_option('wp_smsn1_ru_vk_client_id'),
                    'access_token' => $token['access_token'],
                ),
            )
        );

        if (is_wp_error($response)) {
            return $profile;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            return $profile;
        }

        $user = array();

        if (!empty($data['user']) && is_array($data['user'])) {
            $user = $data['user'];
        } elseif (!empty($data['response'][0]) && is_array($data['response'][0])) {
            $user = $data['response'][0];
        }

        return $this->merge_user($profile, $user);
    }

    /**
     * Резервный вариант: users.get — имя и, если разрешено, e-mail.
     */
    private function fill_from_api($profile, $token)
    {
        $url = add_query_arg(
            array(
                'user_ids'     => $profile['uid'],
                'fields'       => 'photo_200,email',
                'access_token' => $token['access_token'],
                'v'            => self::API_VERSION,
            ),
            self::API_URL . 'users.get'
        );

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return $profile;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['response'][0]) && is_array($data['response'][0])) {
            $profile = $this->merge_user($profile, $data['response'][0]);
        }

        return $profile;
    }

    /**
     * Переносит имя и e-mail из ответа VK в профиль.
     */
    private function merge_user($profile, $user)
    {
        if (!is_array($user)) {
            return $profile;
        }

        if ($profile['name'] === '') {
            $name = trim(
                (isset($user['first_name']) ? $user['first_name'] : '') . ' ' .
                (isset($user['last_name']) ? $user['last_name'] : '')
            );

            if ($name !== '') {
                $profile['name'] = $name;
            }
        }

        if ($profile['email'] === '' && !empty($user['email']) && is_email($user['email'])) {
            $profile['email'] = $user['email'];
        }

        if ($profile['phone'] === '' && !empty($user['phone']) && is_string($user['phone'])) {
            $profile['phone'] = $user['phone'];
        }

        return $profile;
    }
}
